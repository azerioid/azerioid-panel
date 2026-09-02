<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Terminal;

use AzerioidPanel\Broker\BrokerException;
use AzerioidPanel\Broker\CaddyApply;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Validator;
use AzerioidPanel\Broker\Vhost\VhostUser;
use AzerioidPanel\Broker\Web\WebServers;

final class TerminalManager
{
    private const SESSION_ID_PATTERN = '/^[a-f0-9]{32}$/';

    public function __construct(
        private readonly Config $config,
        private readonly Runtime $runtime,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function start(string $domain, array $input): array
    {
        $this->assertTtydInstalled();
        $this->cleanupExpired();

        $vhost = $this->eligibleVhost($domain);
        $root = (string) $vhost['root'];
        $identity = VhostUser::ensure($this->runtime, $this->config, $domain, $root);

        $adminId = trim((string) ($input['admin_user_id'] ?? ''));
        $sourceIp = trim((string) ($input['source_ip'] ?? ''));
        if ($adminId === '') {
            throw new BrokerException('admin_user_id is required for terminal audit.', 2);
        }

        foreach ($this->sessions()['sessions'] as $existing) {
            if (($existing['domain'] ?? '') === $domain && ($existing['admin_user_id'] ?? '') === $adminId) {
                $this->stop((string) $existing['id'], 'replaced');
            }
        }

        $sessionId = bin2hex(random_bytes(16));
        $port = $this->allocatePort();
        $now = time();
        $idle = $this->config->terminalIdleSeconds;
        $pid = $this->spawnTtyd($sessionId, $port, $identity['username'], $root);

        $session = [
            'id' => $sessionId,
            'domain' => $domain,
            'root' => $root,
            'username' => $identity['username'],
            'port' => $port,
            'pid' => $pid,
            'admin_user_id' => $adminId,
            'source_ip' => $sourceIp,
            'started_at' => gmdate('c', $now),
            'last_activity_at' => gmdate('c', $now),
            'expires_at' => gmdate('c', $now + $idle),
        ];

        $store = $this->sessions();
        $store['sessions'][$sessionId] = $session;
        $this->saveSessions($store);
        $this->syncCaddyRoutes($store['sessions']);

        return [
            'session_id' => $sessionId,
            'domain' => $domain,
            'root' => $root,
            'username' => $identity['username'],
            'ws_path' => '/terminal/' . $sessionId,
            'idle_seconds' => $idle,
            'started_at' => $session['started_at'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stop(string $sessionId, string $reason = 'manual'): array
    {
        $sessionId = $this->normalizeSessionId($sessionId);
        $store = $this->sessions();
        if (!isset($store['sessions'][$sessionId])) {
            throw new BrokerException('Terminal session not found.', 2);
        }

        $session = $store['sessions'][$sessionId];
        $this->killProcess((int) ($session['pid'] ?? 0));
        unset($store['sessions'][$sessionId]);
        $this->saveSessions($store);
        $this->syncCaddyRoutes($store['sessions']);

        $started = strtotime((string) ($session['started_at'] ?? '')) ?: time();
        $ended = time();

        return [
            'stopped' => true,
            'session_id' => $sessionId,
            'domain' => $session['domain'] ?? '',
            'reason' => $reason,
            'started_at' => $session['started_at'] ?? null,
            'ended_at' => gmdate('c', $ended),
            'duration_seconds' => max(0, $ended - $started),
            'admin_user_id' => $session['admin_user_id'] ?? null,
            'source_ip' => $session['source_ip'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function heartbeat(string $sessionId): array
    {
        $sessionId = $this->normalizeSessionId($sessionId);
        $store = $this->sessions();
        if (!isset($store['sessions'][$sessionId])) {
            throw new BrokerException('Terminal session not found.', 2);
        }

        $now = time();
        $idle = $this->config->terminalIdleSeconds;
        $store['sessions'][$sessionId]['last_activity_at'] = gmdate('c', $now);
        $store['sessions'][$sessionId]['expires_at'] = gmdate('c', $now + $idle);
        $this->saveSessions($store);

        return [
            'session_id' => $sessionId,
            'expires_at' => $store['sessions'][$sessionId]['expires_at'],
        ];
    }

    /**
     * @return array{sessions: list<array<string, mixed>>}
     */
    public function listSessions(): array
    {
        $this->cleanupExpired();
        $out = array_values($this->sessions()['sessions']);
        usort($out, fn ($a, $b) => strcmp((string) ($a['started_at'] ?? ''), (string) ($b['started_at'] ?? '')));

        return ['sessions' => $out];
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $sessionId): array
    {
        $sessionId = $this->normalizeSessionId($sessionId);
        $store = $this->sessions();
        if (!isset($store['sessions'][$sessionId])) {
            throw new BrokerException('Terminal session not found.', 2);
        }

        return $store['sessions'][$sessionId];
    }

    /**
     * @return array{removed: list<string>}
     */
    public function cleanupExpired(): array
    {
        $store = $this->sessions();
        $removed = [];
        $now = time();
        foreach ($store['sessions'] as $id => $session) {
            $expires = strtotime((string) ($session['expires_at'] ?? '')) ?: 0;
            $pid = (int) ($session['pid'] ?? 0);
            if ($expires > 0 && $expires < $now) {
                $this->killProcess($pid);
                unset($store['sessions'][$id]);
                $removed[] = (string) $id;
                continue;
            }
            if ($pid > 0 && !$this->processAlive($pid)) {
                unset($store['sessions'][$id]);
                $removed[] = (string) $id;
            }
        }
        if ($removed !== []) {
            $this->saveSessions($store);
            $this->syncCaddyRoutes($store['sessions']);
        }

        return ['removed' => $removed];
    }

    public function stopForVhost(string $domain): void
    {
        $domain = Validator::domain($domain);
        $store = $this->sessions();
        foreach ($store['sessions'] as $id => $session) {
            if (($session['domain'] ?? '') === $domain) {
                $this->killProcess((int) ($session['pid'] ?? 0));
                unset($store['sessions'][$id]);
            }
        }
        $this->saveSessions($store);
        $this->syncCaddyRoutes($store['sessions']);
    }

    /**
     * @return array<string, mixed>
     */
    private function eligibleVhost(string $domain): array
    {
        $domain = Validator::domain($domain);
        foreach (WebServers::for($this->config)->listVhosts($this->runtime, $this->config) as $vhost) {
            if (($vhost['domain'] ?? '') !== $domain) {
                continue;
            }
            if (!empty($vhost['readonly'])) {
                throw new BrokerException('Terminal access is not available for read-only or system vhosts.', 3);
            }
            $root = (string) ($vhost['root'] ?? '');
            if ($root === '') {
                throw new BrokerException('Vhost has no document root.', 2);
            }

            return $vhost;
        }

        throw new BrokerException('Vhost not found.', 2);
    }

    private function assertTtydInstalled(): void
    {
        if (!$this->runtime->fileExists($this->config->ttydBin)) {
            throw new BrokerException('ttyd is not installed (panel bootstrap dependency).', 3);
        }
    }

    private function spawnTtyd(string $sessionId, int $port, string $username, string $root): int
    {
        $base = '/terminal/' . $sessionId;
        $log = '/var/log/azerioid-panel/ttyd-' . $sessionId . '.log';
        $cmd = sprintf(
            'nohup /usr/bin/runuser -u %s -- %s -p %d -i 127.0.0.1 -W -b %s -d %s -t disableReconnect=true /bin/bash -l >> %s 2>&1 & echo $!',
            escapeshellarg($username),
            escapeshellarg($this->config->ttydBin),
            $port,
            escapeshellarg($base),
            escapeshellarg($root),
            escapeshellarg($log)
        );
        $result = $this->runtime->exec(['/bin/sh', '-c', $cmd], null, 15);
        if (!$result->ok()) {
            throw new BrokerException('Failed to start ttyd: ' . trim($result->stderr), 1);
        }
        $pid = (int) trim($result->stdout);
        if ($pid < 1) {
            throw new BrokerException('Failed to start ttyd (no pid).', 1);
        }

        return $pid;
    }

    private function allocatePort(): int
    {
        $used = [];
        foreach ($this->sessions()['sessions'] as $session) {
            $used[(int) ($session['port'] ?? 0)] = true;
        }
        for ($port = $this->config->terminalPortMin; $port <= $this->config->terminalPortMax; $port++) {
            if (!isset($used[$port])) {
                return $port;
            }
        }
        throw new BrokerException('No free terminal ports available.', 1);
    }

  /**
     * @param  array<string, array<string, mixed>>  $sessions
     */
    private function syncCaddyRoutes(array $sessions): void
    {
        $path = $this->config->terminalCaddyRoutesPath;
        $this->runtime->mkdir(dirname($path), 0750);
        $this->runtime->writeFile($path, $this->renderCaddyRoutes($sessions), 0644);
        try {
            CaddyApply::run($this->runtime, $this->config, 'auto');
        } catch (BrokerException $e) {
            throw new BrokerException('Caddy could not apply terminal routes: ' . $e->getMessage(), 1);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $sessions
     */
    private function renderCaddyRoutes(array $sessions): string
    {
        if ($sessions === []) {
            return "# Stack Manager — no active terminal sessions\n";
        }
        $auth = '127.0.0.1:' . $this->config->panelPort;
        $lines = ['# Stack Manager — terminal routes (broker-managed; do not edit)'];
        foreach ($sessions as $session) {
            $id = (string) ($session['id'] ?? '');
            $port = (int) ($session['port'] ?? 0);
            if ($id === '' || $port < 1) {
                continue;
            }
            $lines[] = "handle_path /terminal/{$id}/* {";
            $lines[] = "    forward_auth {$auth} {";
            $lines[] = "        uri /internal/terminal/auth/{$id}";
            $lines[] = '    }';
            $lines[] = "    reverse_proxy 127.0.0.1:{$port}";
            $lines[] = '}';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return array{sessions: array<string, array<string, mixed>>}
     */
    private function sessions(): array
    {
        $path = $this->config->terminalSessionsPath;
        if (!$this->runtime->fileExists($path)) {
            return ['sessions' => []];
        }
        $decoded = json_decode($this->runtime->readFile($path), true);
        if (!is_array($decoded) || !isset($decoded['sessions']) || !is_array($decoded['sessions'])) {
            return ['sessions' => []];
        }

        return ['sessions' => $decoded['sessions']];
    }

    /**
     * @param  array{sessions: array<string, array<string, mixed>>}  $store
     */
    private function saveSessions(array $store): void
    {
        $path = $this->config->terminalSessionsPath;
        $this->runtime->mkdir(dirname($path), 0750);
        $this->runtime->writeFile(
            $path,
            json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            0640
        );
    }

    private function normalizeSessionId(string $sessionId): string
    {
        $sessionId = strtolower(trim($sessionId));
        if (!preg_match(self::SESSION_ID_PATTERN, $sessionId)) {
            throw new BrokerException('Invalid terminal session id.', 2);
        }

        return $sessionId;
    }

    private function killProcess(int $pid): void
    {
        if ($pid < 1) {
            return;
        }
        $this->runtime->exec(['/bin/kill', '-TERM', (string) $pid], null, 10);
        usleep(200_000);
        if ($this->processAlive($pid)) {
            $this->runtime->exec(['/bin/kill', '-KILL', (string) $pid], null, 10);
        }
    }

    private function processAlive(int $pid): bool
    {
        if ($pid < 1) {
            return false;
        }

        return $this->runtime->exec(['/bin/kill', '-0', (string) $pid], null, 5)->ok();
    }
}
