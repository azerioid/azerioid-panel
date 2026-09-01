<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;

final class UpdatesList
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $security = 0;
        $total = 0;
        $fromNotifier = false;
        if ($runtime->fileExists('/usr/lib/update-notifier/apt-check')) {
            $check = $runtime->exec(['/usr/lib/update-notifier/apt-check'], null, 30);
            $raw = trim($check->stderr !== '' ? $check->stderr : $check->stdout);
            if (preg_match('/^(\d+);(\d+)$/', $raw, $m)) {
                $total = (int) $m[1];
                $security = (int) $m[2];
                $fromNotifier = true;
            }
        }

        $packages = [];
        $sim = $runtime->exec(['/usr/bin/apt-get', '-s', '-o', 'Debug::NoLocking=true', 'upgrade'], null, 60);
        foreach (explode("\n", $sim->stdout) as $line) {
            if (!preg_match('/^Inst\s+(\S+)\s+/', $line, $m)) {
                continue;
            }
            $isSecurity = str_contains(strtolower($line), '-security');
            $packages[] = [
                'name' => $m[1],
                'security' => $isSecurity,
                'raw' => $line,
            ];
        }
        if (!$fromNotifier) {
            $total = count($packages);
            $security = count(array_filter($packages, static fn ($p) => $p['security']));
        }

        return [
            'total' => $total,
            'security' => $security,
            'packages' => array_slice($packages, 0, 200),
            'source' => $fromNotifier ? 'apt-check' : 'apt-get -s',
        ];
    }
}
