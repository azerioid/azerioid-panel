<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Component;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;
use AzerioidPanel\Broker\Systemd;
use AzerioidPanel\Broker\Validator;

final class ComponentCatalog
{
    public function __construct(
        private readonly Config $config,
        private readonly Runtime $runtime,
    ) {
    }

    /** @return array<string, mixed> */
    public function list(): array
    {
        $os = OsRelease::detect($this->runtime);
        $registry = new ComponentRegistry($this->config->registryComponentsPath, $this->runtime);
        $managed = ManagedManifest::load($this->runtime, $this->config->managedComponentsPath);
        $detector = new ComponentDetector($this->runtime, $os, $managed);

        $components = [];
        foreach ($registry->all() as $definition) {
            $components[] = $detector->inspect($definition);
        }

        return [
            'distro_key' => $os->distroKey,
            'distro_id' => $os->id,
            'distro_version' => $os->versionId,
            'pkg_mgr' => $os->pkgMgr,
            'registry_path' => $this->config->registryComponentsPath,
            'components' => $components,
            'observed_extras' => $this->observedExtras($components),
        ];
    }

    /** @return array<string, mixed> */
    public function status(string $componentId): array
    {
        $componentId = Validator::componentId($componentId);
        $os = OsRelease::detect($this->runtime);
        $registry = new ComponentRegistry($this->config->registryComponentsPath, $this->runtime);
        $managed = ManagedManifest::load($this->runtime, $this->config->managedComponentsPath);
        $detector = new ComponentDetector($this->runtime, $os, $managed);

        return $detector->inspect($registry->get($componentId)) + [
            'distro_key' => $os->distroKey,
            'distro_id' => $os->id,
        ];
    }

    /**
     * @param list<array<string, mixed>> $components
     * @return list<array<string, mixed>>
     */
    private function observedExtras(array $components): array
    {
        $knownUnits = [];
        foreach ($components as $row) {
            if (!empty($row['unit'])) {
                $knownUnits[(string) $row['unit']] = true;
            }
        }

        $extras = [];
        foreach ($this->config->observedServices as $entry) {
            $entry = trim($entry);
            if ($entry === '' || isset($knownUnits[$entry])) {
                continue;
            }
            if (preg_match('/^(?:127\.0\.0\.1|localhost):[1-9][0-9]{0,4}$/', $entry)) {
                $extras[] = [
                    'id' => 'observed:'.$entry,
                    'display_name' => $entry,
                    'kind' => 'observed',
                    'status' => 'unknown',
                    'description' => 'Configured observed upstream',
                    'bind' => $entry,
                ];
                continue;
            }
            if (!preg_match(Validator::SERVICE_PATTERN, $entry)) {
                continue;
            }
            $info = Systemd::show($this->runtime, $entry);
            if (($info['unit_file_state'] ?? '') === 'not-found') {
                continue;
            }
            $extras[] = [
                'id' => 'observed:'.$entry,
                'display_name' => $entry,
                'kind' => 'observed',
                'status' => ($info['running'] ?? false) ? 'active' : 'installed',
                'description' => (string) ($info['description'] ?? 'Observed service'),
                'unit' => $entry,
                'unit_state' => $info,
            ];
        }
        return $extras;
    }
}
