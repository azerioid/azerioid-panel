<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\BrokerException;
use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;
use LacmpPanel\Broker\Validator;

final class DbAdd
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $name = Validator::dbName($args[0] ?? ($input['name'] ?? ''));
        $user = Validator::userName($args[1] ?? ($input['user'] ?? $name));
        $password = Validator::password((string) ($input['password'] ?? ''));

        if (in_array($name, $config->protectedDatabases, true)) {
            throw new BrokerException('Refusing to mutate a protected system database.', 3);
        }

        $existing = $runtime->dbQuery('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$name]);
        if ($existing !== []) {
            throw new BrokerException("Database {$name} already exists.", 3);
        }

        $users = $runtime->dbQuery('SELECT User, Host FROM mysql.user WHERE User = ?', [$user]);
        if ($users !== []) {
            throw new BrokerException("Database user {$user} already exists.", 3);
        }

        $createdDb = false;
        $createdHosts = [];
        try {
            $runtime->dbExec('CREATE DATABASE `' . self::ident($name) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $createdDb = true;
            foreach (['localhost', '127.0.0.1'] as $host) {
                $runtime->dbExec(
                    'CREATE USER `' . self::ident($user) . '`@`' . self::ident($host) . '` IDENTIFIED BY ?',
                    [$password]
                );
                $createdHosts[] = $host;
                $runtime->dbExec(
                    'GRANT ALL PRIVILEGES ON `' . self::ident($name) . '`.* TO `' . self::ident($user) . '`@`' . self::ident($host) . '`'
                );
            }
            $runtime->dbExec('FLUSH PRIVILEGES');
        } catch (\Throwable $e) {
            $this->rollback($runtime, $name, $user, $createdDb, $createdHosts);
            if ($e instanceof BrokerException) {
                throw $e;
            }
            throw new BrokerException($e->getMessage(), 1);
        }

        return [
            'name' => $name,
            'user' => $user,
            'hosts' => ['localhost', '127.0.0.1'],
        ];
    }

    /**
     * @param  list<string>  $createdHosts
     */
    private function rollback(Runtime $runtime, string $name, string $user, bool $createdDb, array $createdHosts): void
    {
        try {
            if ($createdDb) {
                $runtime->dbExec('DROP DATABASE IF EXISTS `' . self::ident($name) . '`');
            }
            foreach ($createdHosts as $host) {
                $runtime->dbExec('DROP USER IF EXISTS `' . self::ident($user) . '`@`' . self::ident($host) . '`');
            }
            $runtime->dbExec('FLUSH PRIVILEGES');
        } catch (\Throwable) {
        }
    }

    /**
     * Identifiers are already allowlisted to [A-Za-z0-9_]{1,32}.
     * We still refuse anything that would not match, then quote with backticks.
     */
    public static function ident(string $name): string
    {
        if (!preg_match(Validator::DB_NAME_PATTERN, $name) && $name !== 'localhost' && $name !== '127.0.0.1') {
            throw new BrokerException('Unsafe SQL identifier.', 2);
        }
        return $name;
    }
}
