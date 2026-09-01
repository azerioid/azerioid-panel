<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\BrokerException;
use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;
use LacmpPanel\Broker\Validator;

final class DbDel
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $name = Validator::dbName($args[0] ?? ($input['name'] ?? ''));
        $user = Validator::userName((string) ($args[1] ?? ($input['user'] ?? $name)));

        if (in_array($name, $config->protectedDatabases, true)) {
            throw new BrokerException('Refusing to mutate a protected system database.', 3);
        }

        $existing = $runtime->dbQuery('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$name]);
        if ($existing === []) {
            throw new BrokerException('Database does not exist.', 3);
        }

        $runtime->dbExec('DROP DATABASE IF EXISTS `' . DbAdd::ident($name) . '`');
        foreach (['localhost', '127.0.0.1'] as $host) {
            try {
                $runtime->dbExec('DROP USER IF EXISTS `' . DbAdd::ident($user) . '`@`' . DbAdd::ident($host) . '`');
            } catch (\Throwable) {
                // user may only exist on one host
            }
        }
        $runtime->dbExec('FLUSH PRIVILEGES');

        return ['name' => $name, 'user' => $user, 'dropped' => true];
    }
}
