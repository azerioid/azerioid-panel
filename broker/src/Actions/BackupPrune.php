<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Actions;

use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;
use LacmpPanel\Broker\SpacesClient;

final class BackupPrune
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $client = SpacesClient::fromInput($input['spaces'] ?? []);
        $keep = max(1, min(365, (int) ($input['keep'] ?? $args[0] ?? 14)));
        $listed = $client->list('lacmp/');
        $byKind = [];
        foreach ($listed['objects'] as $obj) {
            $key = (string) ($obj['key'] ?? '');
            $parts = explode('/', $key);
            $kind = ($parts[1] ?? 'unknown') . '/' . ($parts[2] ?? '');
            $byKind[$kind][] = $obj;
        }
        $deleted = [];
        foreach ($byKind as $group) {
            usort($group, static fn ($a, $b) => strcmp((string) $b['last_modified'], (string) $a['last_modified']));
            foreach (array_slice($group, $keep) as $old) {
                $client->delete((string) $old['key']);
                $deleted[] = $old['key'];
            }
        }
        return ['deleted' => $deleted, 'keep' => $keep];
    }
}
