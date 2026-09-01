<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\SpacesClient;

final class SpacesTest
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        return SpacesClient::fromInput($input['spaces'] ?? [])->test();
    }
}
