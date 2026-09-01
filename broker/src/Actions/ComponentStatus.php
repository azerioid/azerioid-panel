<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Component\ComponentCatalog;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;

final class ComponentStatus
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $id = (string) ($args[0] ?? '');
        return (new ComponentCatalog($config, $runtime))->status($id);
    }
}
