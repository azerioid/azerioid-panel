<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Component\ComponentCatalog;
use AzerioidPanel\Broker\Component\ComponentInstaller;
use AzerioidPanel\Broker\Component\ComponentPreflight;
use AzerioidPanel\Broker\Component\ComponentRegistry;
use AzerioidPanel\Broker\Component\OsRelease;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Validator;

final class ComponentPreflightAction
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $id = Validator::componentId((string) ($args[0] ?? ''));
        $registry = new ComponentRegistry($config->registryComponentsPath, $runtime);
        $definition = $registry->get($id);
        $os = OsRelease::detect($runtime);
        return (new ComponentPreflight($config, $runtime, $os))->check($definition);
    }
}
