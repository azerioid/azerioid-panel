<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\BrokerException;
use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;
use LacmpPanel\Broker\Systemd;

final class MariadbBindFix
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $path = $config->mariadbServerCnf;
        if (!$runtime->fileExists($path)) {
            throw new BrokerException('MariaDB server config not found.', 3);
        }
        $original = $runtime->readFile($path);
        $stamp = preg_replace('/[^0-9TZ]/', '', $runtime->now()) ?: gmdate('YmdHis');
        $backup = $path . '.lacmp-bak-' . $stamp;
        $runtime->writeFile($backup, $original, 0640);

        $cnf = $original;
        if (preg_match('/^\s*bind-address\s*=/mi', $cnf)) {
            $cnf = preg_replace('/^\s*bind-address\s*=.*$/mi', 'bind-address = 127.0.0.1', $cnf, 1) ?? $cnf;
        } elseif (preg_match('/^\[mysqld\]/mi', $cnf)) {
            $cnf = preg_replace('/^\[mysqld\]/mi', "[mysqld]\nbind-address = 127.0.0.1", $cnf, 1) ?? $cnf;
        } else {
            $cnf = "[mysqld]\nbind-address = 127.0.0.1\n\n" . $cnf;
        }
        $runtime->writeFile($path, $cnf, 0644);
        Systemd::control($runtime, 'restart', 'mariadb');

        return [
            'bind_address' => '127.0.0.1',
            'config_path' => $path,
            'backup_path' => $backup,
            'restarted' => true,
        ];
    }
}
