<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;
use LacmpPanel\Broker\Validator;
use LacmpPanel\Broker\Web\WebServers;

final class VhostDel
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $domain = Validator::domain($args[0] ?? ($input['domain'] ?? ''));
        return WebServers::for($config)->removeVhost($runtime, $config, $domain);
    }
}
