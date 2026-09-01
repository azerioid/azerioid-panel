<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Component;

use AzerioidPanel\Broker\Runtime;

final class ManagedManifest
{
    /** @param array<string, array<string, mixed>> $components */
    public function __construct(public readonly array $components)
    {
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->components);
    }

    public function has(string $componentId): bool
    {
        return isset($this->components[$componentId]);
    }

    public function unit(string $componentId): ?string
    {
        $unit = $this->components[$componentId]['unit'] ?? null;
        return is_string($unit) && $unit !== '' ? $unit : null;
    }

    /** @return list<string> */
    public function units(): array
    {
        $units = [];
        foreach ($this->components as $row) {
            $unit = is_string($row['unit'] ?? null) ? trim($row['unit']) : '';
            if ($unit !== '') {
                $units[] = $unit;
            }
        }
        return array_values(array_unique($units));
    }

    public static function load(Runtime $runtime, string $path): self
    {
        if (!$runtime->fileExists($path)) {
            return new self([]);
        }
        $raw = $runtime->readFile($path);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return new self([]);
        }
        $components = [];
        if (isset($data['components']) && is_array($data['components'])) {
            foreach ($data['components'] as $id => $meta) {
                if (is_array($meta)) {
                    $components[(string) $id] = $meta;
                }
            }
        }
        return new self($components);
    }

    /** @param array<string, mixed> $meta */
    public static function record(Runtime $runtime, string $path, string $componentId, array $meta): void
    {
        $manifest = self::load($runtime, $path);
        $components = $manifest->components;
        $components[$componentId] = $meta;
        self::write($runtime, $path, $components);
    }

    public static function remove(Runtime $runtime, string $path, string $componentId): void
    {
        $manifest = self::load($runtime, $path);
        $components = $manifest->components;
        unset($components[$componentId]);
        self::write($runtime, $path, $components);
    }

    /** @param array<string, array<string, mixed>> $components */
    private static function write(Runtime $runtime, string $path, array $components): void
    {
        $dir = dirname($path);
        if (!$runtime->isDir($dir)) {
            $runtime->mkdir($dir, 0750);
        }
        $payload = json_encode(['components' => $components], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return;
        }
        $runtime->writeFile($path, $payload . "\n", 0640);
    }
}
