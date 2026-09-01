<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\ProcMetrics;
use LacmpPanel\Broker\Runtime;

final class MariadbBindStatus
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $public = ProcMetrics::mariadbBindFromProcNet($runtime);
        $cnfValue = null;
        if ($runtime->fileExists($config->mariadbServerCnf)) {
            $cnf = $runtime->readFile($config->mariadbServerCnf);
            if (preg_match('/^\s*bind-address\s*=\s*(\S+)/mi', $cnf, $m)) {
                $cnfValue = $m[1];
            }
        }
        return [
            'listening_public' => $public,
            'bind_address_config' => $cnfValue,
            'config_path' => $config->mariadbServerCnf,
        ];
    }
}
