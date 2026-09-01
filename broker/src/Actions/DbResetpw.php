<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;
use LacmpPanel\Broker\Validator;

final class DbResetpw
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $user = Validator::userName($args[0] ?? ($input['user'] ?? ''));
        $password = Validator::password((string) ($input['password'] ?? ''));

        foreach (['localhost', '127.0.0.1'] as $host) {
            $runtime->dbExec(
                'ALTER USER `' . DbAdd::ident($user) . '`@`' . DbAdd::ident($host) . '` IDENTIFIED BY ?',
                [$password]
            );
        }
        $runtime->dbExec('FLUSH PRIVILEGES');

        return ['user' => $user, 'reset' => true];
    }
}
