<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\BrokerException;
use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;
use LacmpPanel\Broker\Validator;
use LacmpPanel\Broker\Web\WebServers;

final class VhostAdd
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $domain = Validator::domain($args[0] ?? ($input['domain'] ?? ''));
        $root = Validator::webRoot((string) ($args[1] ?? ($input['root'] ?? '')), $config->wwwRoot, $runtime);
        $type = Validator::vhostType((string) ($args[2] ?? ($input['type'] ?? 'php')));

        $phpVersion = null;
        $upstream = null;
        if ($type === 'php') {
            $phpVersion = Validator::phpVersion((string) ($args[3] ?? ($input['php_version'] ?? '')), $runtime->phpVersions());
        }
        if ($type === 'proxy') {
            $upstream = Validator::localUpstream((string) ($args[3] ?? ($input['upstream'] ?? '')));
        }

        $blocked = array_map('strtolower', $config->readonlyVhosts);
        if (in_array($domain, $blocked, true) || $domain === 'default' || $domain === 'lacmp-panel') {
            throw new BrokerException("{$domain} is managed externally and can't be edited.", 3);
        }

        return WebServers::for($config)->addVhost($runtime, $config, [
            'domain' => $domain,
            'root' => $root,
            'type' => $type,
            'php_version' => $phpVersion,
            'upstream' => $upstream,
        ]);
    }
}
