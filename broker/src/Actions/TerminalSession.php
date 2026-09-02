<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\BrokerException;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Terminal\TerminalManager;
use AzerioidPanel\Broker\Validator;

final class TerminalSession
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $manager = new TerminalManager($config, $runtime);

        return match ($action) {
            'terminal.session.start' => $manager->start(
                Validator::domain($args[0] ?? ($input['domain'] ?? '')),
                $input
            ),
            'terminal.session.stop' => $manager->stop(
                (string) ($args[0] ?? ($input['session_id'] ?? ''))
            ),
            'terminal.session.heartbeat' => $manager->heartbeat(
                (string) ($args[0] ?? ($input['session_id'] ?? ''))
            ),
            'terminal.session.list' => $manager->listSessions(),
            'terminal.session.status' => $manager->status(
                (string) ($args[0] ?? ($input['session_id'] ?? ''))
            ),
            'terminal.session.cleanup' => $manager->cleanupExpired(),
            default => throw new BrokerException('Unknown terminal action.', 2),
        };
    }
}
