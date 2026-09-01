<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;

final class VersionAll
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $web = \LacmpPanel\Broker\Web\WebServers::for($config)->version($runtime, $config);
        return [
            'web' => $web,
            'caddy' => ['version' => $web['version'], 'raw' => $web['raw']],
            'mariadb' => self::mariadb($runtime),
            'php' => self::php($runtime),
        ];
    }

    private static function mariadb(Runtime $runtime): array
    {
        $bin = $runtime->fileExists('/usr/bin/mariadb') ? '/usr/bin/mariadb' : '/usr/bin/mysql';
        $r = $runtime->exec([$bin, '--version']);
        $raw = trim($r->stdout);
        $version = $raw;
        if (preg_match('/(?:mariadb|mysql).*?\s([0-9]+\.[0-9]+\.[0-9]+)/i', $raw, $m)) {
            $version = $m[1];
        } elseif (preg_match('/Distrib ([0-9.]+)/', $raw, $m)) {
            $version = $m[1];
        }
        return ['version' => $version, 'raw' => $raw];
    }

    private static function php(Runtime $runtime): array
    {
        $r = $runtime->exec(['/usr/bin/php', '-v']);
        $raw = trim($r->stdout);
        $version = '';
        if (preg_match('/^PHP\s+([0-9]+\.[0-9]+\.[0-9]+)/', $raw, $m)) {
            $version = $m[1];
        }
        $installed = $runtime->phpVersions();
        return ['version' => $version, 'raw' => explode("\n", $raw)[0] ?? $raw, 'installed' => $installed];
    }
}
