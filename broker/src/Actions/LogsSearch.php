<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;
use LacmpPanel\Broker\Validator;

final class LogsSearch
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $key = Validator::logKey($args[0] ?? ($input['key'] ?? ''), $config->logPaths);
        $needle = Validator::searchNeedle((string) ($args[1] ?? $input['needle'] ?? ''));
        $path = $config->logPaths[$key];
        if (!$runtime->fileExists($path)) {
            return ['key' => $key, 'path' => $path, 'missing' => true, 'lines' => []];
        }
        $result = $runtime->exec(['/usr/bin/grep', '-F', '-n', '-m', '200', '--', $needle, $path], null, 15);
        $lines = $result->stdout === '' ? [] : explode("\n", rtrim($result->stdout, "\n"));
        return [
            'key' => $key,
            'path' => $path,
            'missing' => false,
            'needle' => $needle,
            'lines' => $lines,
        ];
    }
}
