<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Validator;
use AzerioidPanel\Broker\Web\WebServers;

final class TlsCerts
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $seen = [];
        $certs = [];
        foreach (WebServers::for($config)->listVhosts($runtime, $config) as $parsed) {
            if (!($parsed['tls'] ?? false)) {
                continue;
            }
            foreach ($parsed['domains'] ?? [] as $domain) {
                try {
                    $domain = Validator::domain((string) $domain);
                } catch (\Throwable) {
                    continue;
                }
                if (isset($seen[$domain])) {
                    continue;
                }
                $seen[$domain] = true;
                $certs[] = $this->probe($runtime, $domain);
            }
        }
        usort($certs, static fn ($a, $b) => ($a['days_remaining'] ?? 9999) <=> ($b['days_remaining'] ?? 9999));
        return ['certs' => $certs];
    }

    private function probe(Runtime $runtime, string $domain): array
    {
        $raw = $runtime->exec([
            '/usr/bin/openssl',
            's_client',
            '-connect',
            '127.0.0.1:443',
            '-servername',
            $domain,
        ], "\n", 8);
        $pem = '';
        if (preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $raw->stdout . $raw->stderr, $m)) {
            $pem = $m[0];
        }
        if ($pem === '') {
            return [
                'domain' => $domain,
                'ok' => false,
                'error' => 'No certificate captured.',
                'days_remaining' => null,
            ];
        }
        $info = $runtime->exec([
            '/usr/bin/openssl',
            'x509',
            '-noout',
            '-issuer',
            '-dates',
            '-enddate',
        ], $pem . "\n", 5);
        $issuer = $notBefore = $notAfter = null;
        foreach (explode("\n", $info->stdout) as $line) {
            if (str_starts_with($line, 'issuer=')) {
                $issuer = substr($line, 7);
            } elseif (str_starts_with($line, 'notBefore=')) {
                $notBefore = substr($line, 10);
            } elseif (str_starts_with($line, 'notAfter=')) {
                $notAfter = substr($line, 9);
            }
        }
        $days = null;
        if ($notAfter !== null) {
            $ts = strtotime($notAfter);
            if ($ts !== false) {
                $days = (int) floor(($ts - time()) / 86400);
            }
        }
        return [
            'domain' => $domain,
            'ok' => true,
            'issuer' => $issuer,
            'valid_from' => $notBefore,
            'valid_to' => $notAfter,
            'days_remaining' => $days,
            'renewal' => $days === null ? 'unknown' : ($days < 0 ? 'expired' : ($days <= 14 ? 'expiring' : 'ok')),
        ];
    }
}
