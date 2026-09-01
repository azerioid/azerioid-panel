<?php

namespace App\Services\Broker;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use LacmpPanel\Broker\AuditLog as BrokerAudit;
use LacmpPanel\Broker\Validator;
use Symfony\Component\Process\Process;

/**
 * Dual-tier client: re-validates the action name, then invokes the broker
 * via an argument array (never a shell string). Secrets travel on stdin JSON.
 */
final class BrokerClient
{
    public function __construct(private readonly FakeBroker $fake)
    {
    }

    public function call(string $action, array $args = [], array $stdin = [], ?int $timeout = null, bool $audit = true): BrokerResponse
    {
        Validator::action($action);
        $this->assertSafeArgs($args);

        $driver = (string) config('lacmp.broker.driver', 'fake');
        try {
            $response = match ($driver) {
                'sudo' => $this->viaSudo($action, $args, $stdin, $timeout),
                'in-process' => $this->viaInProcess($action, $args, $stdin),
                default => $this->fake->handle($action, $args, $stdin),
            };
        } catch (\Throwable $e) {
            $response = new BrokerResponse(false, null, $e->getMessage(), 1);
        }

        if ($audit) {
            AuditLog::query()->create([
                'user_id' => Auth::id(),
                'action' => $action,
                'args' => BrokerAudit::redact(array_merge(
                    ['argv' => $args],
                    $stdin
                )),
                'ok' => $response->ok,
                'code' => $response->code,
                'error' => $response->error,
                'ip' => request()?->ip(),
            ]);
        }

        return $response;
    }

    private function assertSafeArgs(array $args): void
    {
        foreach ($args as $arg) {
            if (! is_scalar($arg)) {
                throw new BrokerCallException('Broker arguments must be scalars.', 2);
            }
            $s = (string) $arg;
            if (str_contains($s, "\0") || str_contains($s, "\n")) {
                throw new BrokerCallException('Broker argument contains invalid characters.', 2);
            }
        }
    }

    private function viaSudo(string $action, array $args, array $stdin, ?int $timeout = null): BrokerResponse
    {
        $broker = (string) config('lacmp.broker.path');
        $cmd = [];
        if (config('lacmp.broker.use_sudo')) {
            $cmd[] = (string) config('lacmp.broker.sudo_path');
            $cmd[] = '-n';
        }
        $cmd[] = $broker;
        $cmd[] = $action;
        foreach ($args as $arg) {
            $cmd[] = (string) $arg;
        }

        $process = new Process($cmd);
        $process->setTimeout($timeout ?? (int) config('lacmp.broker.timeout', 45));
        if ($stdin !== []) {
            $process->setInput(json_encode($stdin, JSON_UNESCAPED_SLASHES));
        }
        $process->run();

        $decoded = json_decode(trim($process->getOutput()), true);
        if (! is_array($decoded)) {
            return new BrokerResponse(false, null, 'Broker returned non-JSON output.', 1);
        }
        return BrokerResponse::fromArray($decoded);
    }

    private function viaInProcess(string $action, array $args, array $stdin): BrokerResponse
    {
        $configPath = getenv('LACMP_PANEL_CONFIG') ?: '/etc/lacmp-panel/broker.json';
        $runtime = new \LacmpPanel\Broker\PosixRuntime();
        $config = \LacmpPanel\Broker\Config::load($configPath, $runtime);
        $kernel = new \LacmpPanel\Broker\Kernel($config, $runtime);
        ob_start();
        $kernel->run(array_merge(['broker', $action], $args), $stdin);
        $out = ob_get_clean();
        $decoded = json_decode(trim((string) $out), true);
        if (! is_array($decoded)) {
            return new BrokerResponse(false, null, 'In-process broker returned non-JSON output.', 1);
        }
        return BrokerResponse::fromArray($decoded);
    }
}
