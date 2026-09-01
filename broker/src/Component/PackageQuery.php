<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Component;

use AzerioidPanel\Broker\Runtime;

final class PackageQuery
{
    public static function isInstalled(Runtime $runtime, string $package, string $pkgMgr): bool
    {
        $package = trim($package);
        if ($package === '') {
            return false;
        }

        if ($pkgMgr === 'apt') {
            $result = $runtime->exec([
                '/usr/bin/dpkg-query',
                '-W',
                '-f=${Status}',
                $package,
            ]);
            return $result->ok() && str_contains($result->stdout, 'install ok installed');
        }

        $result = $runtime->exec(['/usr/bin/rpm', '-q', $package]);
        return $result->ok();
    }

    /** @param list<string> $packages */
    public static function anyInstalled(Runtime $runtime, array $packages, string $pkgMgr): bool
    {
        foreach ($packages as $package) {
            if (self::isInstalled($runtime, $package, $pkgMgr)) {
                return true;
            }
        }
        return false;
    }

    /** @param list<string> $packages */
    public static function allInstalled(Runtime $runtime, array $packages, string $pkgMgr): bool
    {
        if ($packages === []) {
            return false;
        }
        foreach ($packages as $package) {
            if (!self::isInstalled($runtime, $package, $pkgMgr)) {
                return false;
            }
        }
        return true;
    }
}
