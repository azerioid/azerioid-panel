<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Component;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;

final class PortOwnership
{
    /** @var list<string> */
    private const SITE_WEB_COMPONENTS = ['caddy', 'nginx', 'apache'];

    public function __construct(
        private readonly Config $config,
        private readonly Runtime $runtime,
        private readonly OsRelease $os,
    ) {
    }

    public function conflictPresent(string $conflictId): bool
    {
        if ($conflictId === 'caddy') {
            return $this->caddyOwnsSitePorts();
        }

        $managed = ManagedManifest::load($this->runtime, $this->config->managedComponentsPath);
        if ($managed->has($conflictId)) {
            return true;
        }

        if (!in_array($conflictId, self::SITE_WEB_COMPONENTS, true)) {
            return $this->componentDetected($conflictId, $managed);
        }

        return $this->componentDetected($conflictId, $managed);
    }

    private function caddyOwnsSitePorts(): bool
    {
        if ($this->config->siteWebServer === 'caddy') {
            return true;
        }

        $managed = ManagedManifest::load($this->runtime, $this->config->managedComponentsPath);
        if ($managed->has('caddy')) {
            return true;
        }

        return $this->listeningOnPort(80) && $this->caddyProcessOwnsPort(80);
    }

    private function componentDetected(string $componentId, ManagedManifest $managed): bool
    {
        try {
            $registry = new ComponentRegistry($this->config->registryComponentsPath, $this->runtime);
            $definition = $registry->get($componentId);
            $detector = new ComponentDetector($this->runtime, $this->os, $managed);
            $status = (string) ($detector->inspect($definition)['status'] ?? 'not_installed');

            return in_array($status, ['installed', 'active', 'broken'], true);
        } catch (\Throwable) {
            return false;
        }
    }

    private function listeningOnPort(int $port): bool
    {
        $result = $this->runtime->exec(['/usr/bin/ss', '-ltn', 'sport', '=', (string) $port]);
        if (!$result->ok()) {
            return false;
        }

        return trim($result->stdout) !== '' && str_contains($result->stdout, ':' . $port);
    }

    private function caddyProcessOwnsPort(int $port): bool
    {
        $result = $this->runtime->exec(['/usr/bin/ss', '-ltnp', 'sport', '=', (string) $port]);
        if (!$result->ok()) {
            return false;
        }

        return str_contains(strtolower($result->stdout), 'caddy');
    }
}
