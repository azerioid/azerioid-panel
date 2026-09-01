<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;
use LacmpPanel\Broker\SpacesClient;

final class SpacesTest
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        return SpacesClient::fromInput($input['spaces'] ?? [])->test();
    }
}
