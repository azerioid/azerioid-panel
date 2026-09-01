<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Component;

use AzerioidPanel\Broker\BrokerException;
use AzerioidPanel\Broker\Runtime;

/** @psalm-type OsInfo array{id: string, version_id: string, distro_key: string, pkg_mgr: string} */
final class OsRelease
{
    public function __construct(
        public readonly string $id,
        public readonly string $versionId,
        public readonly string $codename,
        public readonly string $distroKey,
        public readonly string $pkgMgr,
    ) {
    }

    public static function detect(Runtime $runtime): self
    {
        $vars = self::parse($runtime);
        $id = strtolower((string) ($vars['ID'] ?? 'unknown'));
        $versionId = (string) ($vars['VERSION_ID'] ?? '0');
        $codename = (string) ($vars['VERSION_CODENAME'] ?? self::codenameFallback($id, $versionId));

        $distroKey = match ($id) {
            'ubuntu' => 'ubuntu',
            'debian' => 'debian',
            'almalinux', 'rocky', 'rhel', 'ol', 'centos' => 'el',
            default => throw new BrokerException("Unsupported OS for component registry: {$id}.", 1),
        };

        $pkgMgr = in_array($distroKey, ['ubuntu', 'debian'], true) ? 'apt' : 'dnf';

        return new self($id, $versionId, $codename, $distroKey, $pkgMgr);
    }

    private static function codenameFallback(string $id, string $versionId): string
    {
        if ($id === 'ubuntu') {
            return match ($versionId) {
                '24.04' => 'noble',
                '22.04' => 'jammy',
                default => 'noble',
            };
        }
        if ($id === 'debian') {
            return match ($versionId) {
                '12' => 'bookworm',
                '11' => 'bullseye',
                default => 'bookworm',
            };
        }

        return 'el' . explode('.', $versionId)[0];
    }

    /** @return array<string, string> */
    private static function parse(Runtime $runtime): array
    {
        if (!$runtime->fileExists('/etc/os-release')) {
            throw new BrokerException('Cannot read /etc/os-release.', 1);
        }
        $vars = [];
        foreach (explode("\n", $runtime->readFile('/etc/os-release')) as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $vars[$key] = trim($value, "\"'");
        }
        return $vars;
    }
}
