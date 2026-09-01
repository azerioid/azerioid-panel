<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;
use LacmpPanel\Broker\SpacesClient;

final class BackupList
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $client = SpacesClient::fromInput($input['spaces'] ?? []);
        $listed = $client->list('lacmp/');
        $objects = [];
        foreach ($listed['objects'] as $obj) {
            $key = (string) ($obj['key'] ?? '');
            $parts = explode('/', $key);
            $objects[] = [
                'key' => $key,
                'size' => (int) ($obj['size'] ?? 0),
                'last_modified' => $obj['last_modified'] ?? null,
                'kind' => $parts[1] ?? 'unknown',
                'name' => $parts[2] ?? '',
            ];
        }
        usort($objects, static fn ($a, $b) => strcmp((string) $b['last_modified'], (string) $a['last_modified']));
        return ['objects' => $objects];
    }
}
