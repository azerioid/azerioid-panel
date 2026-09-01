<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Database;

use AzerioidPanel\Broker\BrokerException;
use AzerioidPanel\Broker\Component\OperationLogger;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Secrets;
use AzerioidPanel\Broker\Systemd;

final class BrokerConfigWriter
{
    /** @param array<string, mixed> $patch */
    public static function merge(Runtime $runtime, string $path, array $patch): void
    {
        $data = [];
        if ($runtime->fileExists($path)) {
            $decoded = json_decode($runtime->readFile($path), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        $data = self::replaceRecursive($data, $patch);
        $runtime->writeFile(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            0600
        );
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $patch */
    private static function replaceRecursive(array $base, array $patch): array
    {
        foreach ($patch as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::replaceRecursive($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}

final class DatabaseProvisioner
{
    private const ADMIN_USER = 'azerioid_panel_admin';

    public function __construct(
        private readonly Config $config,
        private readonly Runtime $runtime,
    ) {
    }

    public function provision(string $componentId, OperationLogger $log): void
    {
        $password = Secrets::generatePassword();
        $configPath = getenv('AZERIOID_PANEL_CONFIG') ?: getenv('LACMP_PANEL_CONFIG') ?: '/etc/azerioid-panel/broker.json';

        if ($componentId === 'mariadb') {
            $this->secureMariaDbBind($log);
            Systemd::control($this->runtime, 'restart', 'mariadb');
            $this->provisionMariaDbAdmin($password, $log);
            $socket = $this->detectMariaSocket();
            BrokerConfigWriter::merge($this->runtime, $configPath, [
                'database' => [
                    'engine' => 'mariadb',
                    'mariadb' => [
                        'socket' => $socket,
                        'user' => self::ADMIN_USER,
                        'password' => $password,
                    ],
                ],
            ]);
            $log->info('Wrote MariaDB admin credentials to broker.json.');
            return;
        }

        if ($componentId === 'postgresql') {
            $this->securePostgreSqlListen($log);
            Systemd::control($this->runtime, 'restart', 'postgresql');
            $this->provisionPostgreSqlAdmin($password, $log);
            BrokerConfigWriter::merge($this->runtime, $configPath, [
                'database' => [
                    'engine' => 'postgresql',
                    'postgresql' => [
                        'host' => '127.0.0.1',
                        'port' => 5432,
                        'user' => self::ADMIN_USER,
                        'password' => $password,
                    ],
                ],
            ]);
            $log->info('Wrote PostgreSQL admin credentials to broker.json.');
            return;
        }

        throw new BrokerException('Not a database engine component.', 2);
    }

    public function adopt(string $componentId): void
    {
        $configPath = getenv('AZERIOID_PANEL_CONFIG') ?: getenv('LACMP_PANEL_CONFIG') ?: '/etc/azerioid-panel/broker.json';
        if ($componentId === 'mariadb' && $this->config->mysqlPassword !== '') {
            return;
        }
        if ($componentId === 'postgresql' && $this->config->postgresqlPassword !== '') {
            return;
        }

        $password = Secrets::generatePassword();
        $logPath = rtrim($this->config->stagingDir, '/') . '/adopt-' . $componentId . '.log';
        $log = new OperationLogger($this->runtime, $logPath);

        if ($componentId === 'mariadb') {
            $this->provisionMariaDbAdmin($password, $log);
            $socket = $this->detectMariaSocket();
            BrokerConfigWriter::merge($this->runtime, $configPath, [
                'database' => [
                    'engine' => 'mariadb',
                    'mariadb' => [
                        'socket' => $socket,
                        'user' => self::ADMIN_USER,
                        'password' => $password,
                    ],
                ],
            ]);
            $log->info('Adopted MariaDB; wrote panel admin credentials to broker.json.');

            return;
        }

        if ($componentId === 'postgresql') {
            $this->provisionPostgreSqlAdmin($password, $log);
            BrokerConfigWriter::merge($this->runtime, $configPath, [
                'database' => [
                    'engine' => 'postgresql',
                    'postgresql' => [
                        'host' => '127.0.0.1',
                        'port' => 5432,
                        'user' => self::ADMIN_USER,
                        'password' => $password,
                    ],
                ],
            ]);
            $log->info('Adopted PostgreSQL; wrote panel admin credentials to broker.json.');

            return;
        }

        throw new BrokerException('Not a database engine component.', 2);
    }

    private function secureMariaDbBind(OperationLogger $log): void
    {
        $paths = [
            '/etc/mysql/mariadb.conf.d/50-server.cnf',
            '/etc/my.cnf.d/server.cnf',
        ];
        foreach ($paths as $path) {
            if (!$this->runtime->fileExists($path)) {
                continue;
            }
            $log->info("Setting bind-address=127.0.0.1 in {$path}");
            $content = $this->runtime->readFile($path);
            if (preg_match('/^bind-address\s*=/m', $content) === 1) {
                $content = preg_replace('/^bind-address\s*=.*/m', 'bind-address = 127.0.0.1', $content) ?? $content;
            } elseif (preg_match('/^\[mysqld\]/m', $content) === 1) {
                $content = preg_replace(
                    '/^(\[mysqld\][^\[]*)/m',
                    "$1\nbind-address = 127.0.0.1",
                    $content,
                    1
                ) ?? $content;
            } else {
                $content .= "\n[mysqld]\nbind-address = 127.0.0.1\n";
            }
            $this->runtime->writeFile($path, $content, 0644);
        }
    }

    private function securePostgreSqlListen(OperationLogger $log): void
    {
        $candidates = array_merge(
            $this->runtime->glob('/etc/postgresql/*/main/postgresql.conf'),
            $this->runtime->glob('/var/lib/pgsql/*/data/postgresql.conf'),
            ['/var/lib/pgsql/data/postgresql.conf']
        );
        foreach ($candidates as $path) {
            if (!$this->runtime->fileExists($path)) {
                continue;
            }
            $log->info("Setting listen_addresses=localhost in {$path}");
            $content = $this->runtime->readFile($path);
            if (preg_match('/^#?listen_addresses\s*=/m', $content) === 1) {
                $content = preg_replace(
                    '/^#?listen_addresses\s*=.*/m',
                    "listen_addresses = 'localhost'",
                    $content
                ) ?? $content;
            } else {
                $content .= "\nlisten_addresses = 'localhost'\n";
            }
            $this->runtime->writeFile($path, $content, 0644);
        }
    }

    private function provisionMariaDbAdmin(string $password, OperationLogger $log): void
    {
        $escaped = SqlIdent::escapeLiteral($password);
        $statements = [
            "CREATE USER IF NOT EXISTS '" . self::ADMIN_USER . "'@'localhost' IDENTIFIED BY '{$escaped}';",
            "CREATE USER IF NOT EXISTS '" . self::ADMIN_USER . "'@'127.0.0.1' IDENTIFIED BY '{$escaped}';",
            "GRANT ALL PRIVILEGES ON *.* TO '" . self::ADMIN_USER . "'@'localhost' WITH GRANT OPTION;",
            "GRANT ALL PRIVILEGES ON *.* TO '" . self::ADMIN_USER . "'@'127.0.0.1' WITH GRANT OPTION;",
            'FLUSH PRIVILEGES;',
        ];
        foreach ($statements as $sql) {
            $log->info('MariaDB admin setup');
            $result = $this->runtime->exec(['/usr/bin/mariadb', '-e', $sql], null, 60);
            if (!$result->ok()) {
                $result = $this->runtime->exec(['/usr/bin/mysql', '-e', $sql], null, 60);
            }
            if (!$result->ok()) {
                throw new BrokerException('Failed to create MariaDB panel admin user.', 1);
            }
        }
    }

    private function provisionPostgreSqlAdmin(string $password, OperationLogger $log): void
    {
        $wrapper = $this->runAsWrapper('postgres');
        if ($wrapper === null) {
            throw new BrokerException('Cannot run commands as postgres user.', 1);
        }

        $escaped = SqlIdent::escapeLiteral($password);
        $sql = "DO \$\$ BEGIN IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = '"
            . self::ADMIN_USER . "') THEN CREATE ROLE " . self::ADMIN_USER
            . " WITH LOGIN PASSWORD '{$escaped}' CREATEDB CREATEROLE; END IF; END \$\$;";
        $log->info('PostgreSQL admin setup');
        $result = $this->runtime->exec([
            ...$wrapper,
            '/usr/bin/psql',
            '-v',
            'ON_ERROR_STOP=1',
            '-c',
            $sql,
        ], null, 60);
        if (!$result->ok()) {
            throw new BrokerException('Failed to create PostgreSQL panel admin user.', 1);
        }
    }

    /** @return list<string>|null */
    private function runAsWrapper(string $user): ?array
    {
        foreach (['/usr/sbin/runuser', '/sbin/runuser', '/usr/bin/runuser'] as $bin) {
            if ($this->runtime->fileExists($bin)) {
                return [$bin, '-u', $user, '--'];
            }
        }
        if ($this->runtime->fileExists('/usr/bin/sudo')) {
            return ['/usr/bin/sudo', '-n', '-u', $user, '--'];
        }

        return null;
    }

    private function detectMariaSocket(): string
    {
        foreach (['/run/mysqld/mysqld.sock', '/var/lib/mysql/mysql.sock', '/tmp/mysql.sock'] as $path) {
            if ($this->runtime->fileExists($path)) {
                return $path;
            }
        }

        return $this->config->mysqlSocket;
    }
}
