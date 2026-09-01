<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Systemd;

final class PhpVersions
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $versions = [];
        foreach ($runtime->phpVersions() as $ver) {
            $unit = $config->phpFpmService($ver);
            $versions[] = [
                'version' => $ver,
                'fpm_service' => $unit,
                'socket' => $config->phpFpmSocket($ver),
                'ini' => $config->phpIniPath($ver),
                'status' => Systemd::show($runtime, $unit),
            ];
        }
        return ['versions' => $versions];
    }
}
