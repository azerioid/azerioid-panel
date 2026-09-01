<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Database\DatabaseManager;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Validator;

final class DbAdd
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $manager = new DatabaseManager($config, $runtime);
        $engine = $manager->resolveEngine((string) ($input['engine'] ?? ''));
        $name = Validator::dbName($args[0] ?? ($input['name'] ?? ''));
        $user = Validator::userName($args[1] ?? ($input['user'] ?? $name));
        $password = Validator::password((string) ($input['password'] ?? ''));

        $result = $manager->driver($engine)->add($name, $user, $password);

        return $result + ['engine' => $engine];
    }
}
