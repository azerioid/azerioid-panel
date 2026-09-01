<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Database;

use AzerioidPanel\Broker\Runtime;

final class BrokerConfigWriter
{
    /** @param array<string, mixed> $patch */
    public static function merge(Runtime $runtime, string $path, array $patch): void
    {
        $data = [];
        if ($runtime->fileExists($path)) {
            $decoded = json_decode($runtime->readFile($path), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        $data = self::replaceRecursive($data, $patch);
        $runtime->writeFile(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
            0600
        );
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $patch */
    private static function replaceRecursive(array $base, array $patch): array
    {
        foreach ($patch as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::replaceRecursive($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
