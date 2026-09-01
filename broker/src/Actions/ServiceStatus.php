<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Systemd;
use AzerioidPanel\Broker\Validator;

final class ServiceStatus
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $unit = Validator::service($args[0] ?? '', $config->controllableServiceList($runtime));
        return [
            'parsed' => Systemd::show($runtime, $unit),
            'raw' => Systemd::statusRaw($runtime, $unit),
            'journal' => Systemd::journal($runtime, $unit),
        ];
    }
}
