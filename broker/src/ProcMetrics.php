<?php
declare(strict_types=1);

namespace LacmpPanel\Broker;

final class ProcMetrics
{
    public static function collect(Runtime $runtime): array
    {
        $load = [0.0, 0.0, 0.0];
        if ($runtime->fileExists('/proc/loadavg')) {
            $parts = preg_split('/\s+/', trim($runtime->readFile('/proc/loadavg'))) ?: [];
            $load = [(float) ($parts[0] ?? 0), (float) ($parts[1] ?? 0), (float) ($parts[2] ?? 0)];
        }

        $mem = self::meminfo($runtime);
        $uptime = 0.0;
        if ($runtime->fileExists('/proc/uptime')) {
            $uptime = (float) explode(' ', trim($runtime->readFile('/proc/uptime')))[0];
        }

        $disks = self::disks($runtime);
        $mariadbBind = self::mariadbBindFromProcNet($runtime);

        return [
            'loadavg' => ['1' => $load[0], '5' => $load[1], '15' => $load[2]],
            'memory' => $mem,
            'uptime_seconds' => $uptime,
            'disks' => $disks,
            'hostname' => gethostname() ?: 'unknown',
            'mariadb_listening_public' => $mariadbBind,
        ];
    }

    private static function meminfo(Runtime $runtime): array
    {
        $total = $available = $free = 0;
        if (!$runtime->fileExists('/proc/meminfo')) {
            return ['total' => 0, 'available' => 0, 'used' => 0, 'free' => 0];
        }
        foreach (explode("\n", $runtime->readFile('/proc/meminfo')) as $line) {
            if (preg_match('/^MemTotal:\s+(\d+)\s+kB/', $line, $m)) {
                $total = (int) $m[1] * 1024;
            } elseif (preg_match('/^MemAvailable:\s+(\d+)\s+kB/', $line, $m)) {
                $available = (int) $m[1] * 1024;
            } elseif (preg_match('/^MemFree:\s+(\d+)\s+kB/', $line, $m)) {
                $free = (int) $m[1] * 1024;
            }
        }
        return [
            'total' => $total,
            'available' => $available,
            'free' => $free,
            'used' => max(0, $total - $available),
        ];
    }

    private static function disks(Runtime $runtime): array
    {
        $result = $runtime->exec(['/bin/df', '-B1', '-P']);
        $rows = [];
        foreach (explode("\n", trim($result->stdout)) as $i => $line) {
            if ($i === 0 || $line === '') {
                continue;
            }
            $cols = preg_split('/\s+/', $line) ?: [];
            if (count($cols) < 6) {
                continue;
            }
            $mount = $cols[5];
            if (!str_starts_with($mount, '/') || str_starts_with($mount, '/snap') || str_starts_with($mount, '/boot/efi')) {
                continue;
            }
            $rows[] = [
                'filesystem' => $cols[0],
                'size' => (int) $cols[1],
                'used' => (int) $cols[2],
                'available' => (int) $cols[3],
                'use_percent' => $cols[4],
                'mount' => $mount,
            ];
        }
        return $rows;
    }

    /**
     * True when TCP port 3306 is bound to 0.0.0.0 (IPv4 wildcard).
     * 3306 decimal = 0x0CEA.
     */
    public static function mariadbBindFromProcNet(Runtime $runtime): bool
    {
        foreach (['/proc/net/tcp', '/proc/net/tcp6'] as $path) {
            if (!$runtime->fileExists($path)) {
                continue;
            }
            $lines = explode("\n", $runtime->readFile($path));
            array_shift($lines);
            foreach ($lines as $line) {
                $cols = preg_split('/\s+/', trim($line)) ?: [];
                if (count($cols) < 4) {
                    continue;
                }
                $local = $cols[1];
                $state = $cols[3] ?? '';
                if ($state !== '0A') { // TCP_LISTEN
                    continue;
                }
                if (!str_contains($local, ':')) {
                    continue;
                }
                [$addr, $portHex] = explode(':', $local, 2);
                if (strcasecmp($portHex, '0CEA') !== 0) {
                    continue;
                }
                // IPv4 wildcard 00000000 or IPv6 :: (:: is 00000000000000000000000000000000)
                if (preg_match('/^0+$/', $addr)) {
                    return true;
                }
            }
        }
        return false;
    }
}
