<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\BrokerException;
use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;
use LacmpPanel\Broker\Validator;

final class LogsTail
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $key = Validator::logKey($args[0] ?? ($input['key'] ?? ''), $config->logPaths);
        $lines = Validator::lineCount($args[1] ?? ($input['lines'] ?? 200));
        $path = $config->logPaths[$key];

        if (!$runtime->fileExists($path)) {
            return ['key' => $key, 'path' => $path, 'missing' => true, 'lines' => []];
        }

        $real = $runtime->resolveUnderBase($path, dirname($path));
        if ($real === null) {
            throw new BrokerException('Log path failed allowlist resolution.', 3);
        }

        $result = $runtime->exec(['/usr/bin/tail', '-n', (string) $lines, $path]);
        $body = $result->ok() ? $result->stdout : $runtime->readFile($path);
        $split = preg_split("/\r?\n/", rtrim($body, "\n")) ?: [];
        if (count($split) > $lines) {
            $split = array_slice($split, -$lines);
        }

        return [
            'key' => $key,
            'path' => $path,
            'missing' => false,
            'lines' => $split,
        ];
    }
}
