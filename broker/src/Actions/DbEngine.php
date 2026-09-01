<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Database\DatabaseManager;
use AzerioidPanel\Broker\Runtime;

final class DbEngine
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $manager = new DatabaseManager($config, $runtime);

        return [
            'active' => $config->databaseEngine !== '' ? $config->databaseEngine : null,
            'engines' => $manager->engines(),
        ];
    }
}
