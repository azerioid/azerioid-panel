<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Actions;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\SpacesClient;

final class BackupPrune
{
    public function handle(string $action, array $args, array $input, Runtime $runtime, Config $config): array
    {
        $client = SpacesClient::fromInput($input['spaces'] ?? []);
        $keep = max(1, min(365, (int) ($input['keep'] ?? $args[0] ?? 14)));
        $listed = $client->list('azerioid/');
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
