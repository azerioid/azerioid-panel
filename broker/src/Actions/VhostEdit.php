<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\BrokerException;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Validator;
use AzerioidPanel\Broker\Web\WebServers;

final class VhostEdit
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $domain = Validator::domain($args[0] ?? ($input['domain'] ?? ''));

        if (isset($input['new_domain']) && strtolower(trim((string) $input['new_domain'])) !== $domain) {
            throw new BrokerException(
                'Renaming a vhost domain is not supported; delete and recreate the vhost (certificates, DNS, and config filenames all depend on the domain).',
                3
            );
        }
        if (isset($input['type']) || isset($input['new_type'])) {
            throw new BrokerException(
                'Changing vhost type is not supported; delete and recreate the vhost (PHP, static, and reverse-proxy blocks are not interchangeable).',
                3
            );
        }

        $blocked = array_map('strtolower', $config->readonlyVhosts);
        if (in_array($domain, $blocked, true) || $domain === 'default' || $domain === 'azerioid-panel') {
            throw new BrokerException("{$domain} is managed externally and can't be edited.", 3);
        }

        $changes = [];
        if (array_key_exists('root', $input) || isset($args[1])) {
            $changes['root'] = Validator::webRoot(
                (string) ($args[1] ?? $input['root']),
                $config->wwwRoot,
                $runtime
            );
        }
        if (array_key_exists('php_version', $input) || isset($args[2])) {
            $changes['php_version'] = Validator::phpVersion(
                (string) ($args[2] ?? $input['php_version']),
                $runtime->phpVersions()
            );
        }
        if (array_key_exists('tls', $input) || isset($args[3])) {
            $changes['tls'] = self::parseTls($args[3] ?? $input['tls'] ?? null);
        }

        if ($changes === []) {
            throw new BrokerException('No editable fields provided (root, php_version, tls).', 2);
        }

        return WebServers::for($config)->updateVhost($runtime, $config, $domain, $changes);
    }

    private static function parseTls(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string) $value));
        return match ($normalized) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => throw new BrokerException('tls must be true or false.', 2),
        };
    }
}
