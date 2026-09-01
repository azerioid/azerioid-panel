<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Database\DatabaseManager;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Validator;

final class DbResetpw
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $manager = new DatabaseManager($config, $runtime);
        $engine = $manager->resolveEngine((string) ($input['engine'] ?? ''));
        $user = Validator::userName($args[0] ?? ($input['user'] ?? ''));
        $password = Validator::password((string) ($input['password'] ?? ''));

        $result = $manager->driver($engine)->resetPassword($user, $password);

        return $result + ['engine' => $engine];
    }
}
