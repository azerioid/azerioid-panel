<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Component\SitePortReleaser;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;

final class WebReleaseSitePorts
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        return (new SitePortReleaser($config, $runtime))->release();
    }
}
