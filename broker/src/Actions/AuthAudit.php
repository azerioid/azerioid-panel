<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Validator;

final class AuthAudit
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $path = null;
        foreach (['auth', 'auth-syslog'] as $key) {
            $candidate = $config->logPaths[$key] ?? null;
            if (is_string($candidate) && $candidate !== '' && $runtime->fileExists($candidate)) {
                $path = $candidate;
                break;
            }
        }
        if ($path === null) {
            return ['path' => $config->logPaths['auth'] ?? '/var/log/auth.log', 'missing' => true, 'success' => [], 'failed' => [], 'failed_count' => 0, 'new_root_ips' => []];
        }
        $lines = Validator::lineCount($args[0] ?? 400, 1000);
        $result = $runtime->exec(['/usr/bin/tail', '-n', (string) $lines, $path]);
        $body = $result->ok() ? $result->stdout : $runtime->readFile($path);
        $success = [];
        $failed = [];
        $rootIps = [];
        foreach (preg_split("/\r?\n/", $body) ?: [] as $line) {
            if (preg_match('/sshd\[\d+\]:\s+Accepted\s+(\S+)\s+for\s+(\S+)\s+from\s+(\S+)/', $line, $m)) {
                $row = ['user' => $m[2], 'ip' => $m[3], 'method' => $m[1], 'line' => $line];
                $success[] = $row;
                if ($m[2] === 'root') {
                    $rootIps[$m[3]] = ($rootIps[$m[3]] ?? 0) + 1;
                }
            } elseif (preg_match('/sshd\[\d+\]:\s+Failed\s+(\S+)\s+for\s+(?:invalid user\s+)?(\S+)\s+from\s+(\S+)/', $line, $m)) {
                $failed[] = ['user' => $m[2], 'ip' => $m[3], 'method' => $m[1], 'line' => $line];
            }
        }
        $known = [];
        $newRoot = [];
        foreach (array_reverse($success) as $row) {
            if ($row['user'] !== 'root') {
                continue;
            }
            if (!isset($known[$row['ip']])) {
                if (count($known) > 0) {
                    $newRoot[] = $row;
                }
                $known[$row['ip']] = true;
            }
        }
        return [
            'path' => $path,
            'missing' => false,
            'success' => array_slice(array_reverse($success), 0, 50),
            'failed' => array_slice(array_reverse($failed), 0, 50),
            'failed_count' => count($failed),
            'new_root_ips' => array_slice($newRoot, 0, 20),
        ];
    }
}
