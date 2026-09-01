<?php

namespace App\Support;

/**
 * Parseable failed-login lines for fail2ban (public-access mode).
 */
final class AuthFailLog
{
    public static function write(?string $ip): void
    {
        $ip = $ip ?: '0.0.0.0';
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '0.0.0.0';
        }
        $line = gmdate('Y-m-d\TH:i:s\Z') . ' LACMP_PANEL_AUTH_FAIL ip=' . $ip . PHP_EOL;
        foreach (['/var/log/lacmp-panel/auth-fail.log', storage_path('logs/auth-fail.log')] as $path) {
            $dir = dirname($path);
            if (! is_dir($dir)) {
                continue;
            }
            @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        }
    }
}
