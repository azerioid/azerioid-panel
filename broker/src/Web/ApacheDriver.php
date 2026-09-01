<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Web;

use LacmpPanel\Broker\BrokerException;
use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\Runtime;

final class ApacheDriver implements WebServerDriver
{
    public const APACHE2_CONF = Config::APACHE2_CONF;
    public const HTTPD_CONF = Config::HTTPD_CONF;
    public const SITES_AVAILABLE = '/etc/apache2/sites-available';
    public const SITES_ENABLED = '/etc/apache2/sites-enabled';
    public const HTTPD_VHOST_DIR = '/etc/httpd/conf.d/vhost';

    public function __construct(private readonly Config $config)
    {
    }

    public function stackName(): string
    {
        return 'lamp';
    }

    public function webServiceName(): string
    {
        return $this->config->webService;
    }

    public function listVhosts(Runtime $runtime, Config $config): array
    {
        $sites = [];
        $seenDomain = [];
        foreach ($this->listVhostFiles($runtime, $config) as $entry) {
            try {
                $contents = $runtime->readFile($entry['path']);
            } catch (\Throwable) {
                continue;
            }
            $parsed = ApacheParser::parseFile($entry['path'], $contents, $config->readonlyVhosts, $entry['enabled']);
            $key = strtolower((string) $parsed['domain']);
            if (isset($seenDomain[$key])) {
                continue;
            }
            $seenDomain[$key] = true;
            $sites[] = $parsed;
        }
        usort($sites, static fn ($a, $b) => strcmp((string) $a['domain'], (string) $b['domain']));
        return $sites;
    }

    public function addVhost(Runtime $runtime, Config $config, array $spec): array
    {
        $domain = $spec['domain'];
        $root = $spec['root'];
        $type = $spec['type'];
        $phpVersion = $spec['php_version'] ?? null;
        $upstream = $spec['upstream'] ?? null;

        $confPath = $this->siteAvailablePath($config, $domain);
        if ($runtime->fileExists($confPath)) {
            throw new BrokerException("A vhost for {$domain} already exists.", 3);
        }
        foreach ($this->listVhosts($runtime, $config) as $parsed) {
            if (($parsed['domain'] ?? '') === $domain || in_array($domain, $parsed['domains'] ?? [], true)) {
                throw new BrokerException(
                    !empty($parsed['readonly'])
                        ? "{$domain} is managed externally and can't be edited."
                        : "A vhost for {$domain} already exists.",
                    3
                );
            }
        }

        $contents = $this->render($runtime, $config, $domain, $root, $type, $phpVersion, $upstream);
        if (!$runtime->isDir($root)) {
            $runtime->mkdir($root, 0755);
            $runtime->chown($root, $config->phpUser, $config->phpGroup);
        }
        $this->ensureLogDir($runtime, $config);

        $dir = dirname($confPath);
        if (!$runtime->isDir($dir)) {
            $runtime->mkdir($dir, 0755);
        }
        $tmp = $confPath . '.lacmp-tmp';
        $runtime->writeFile($tmp, $contents, 0644);
        try {
            $runtime->rename($tmp, $confPath);
        } catch (BrokerException $e) {
            $runtime->deleteFile($tmp);
            throw $e;
        }

        $enabled = false;
        try {
            $this->enableSite($runtime, $config, $domain, $confPath);
            $enabled = true;
            $this->validate($runtime, $config);
            $applied = $this->reload($runtime, $config, 'auto');
        } catch (BrokerException $e) {
            if ($enabled) {
                $this->disableSite($runtime, $config, $domain);
            }
            $runtime->deleteFile($confPath);
            try {
                $this->reload($runtime, $config, 'auto');
            } catch (BrokerException) {
            }
            throw new BrokerException(
                'Apache rejected the config. The new vhost was rolled back. Existing sites were left serving. ' . $e->getMessage(),
                1
            );
        }

        return [
            'domain' => $domain,
            'root' => $root,
            'type' => $type,
            'php_version' => $phpVersion,
            'upstream' => $upstream,
            'source' => $confPath,
            'apply' => $applied,
        ];
    }

    public function removeVhost(Runtime $runtime, Config $config, string $domain): array
    {
        $confPath = $this->existingPath($runtime, $config, $domain);
        if ($confPath === null) {
            throw new BrokerException('Vhost config does not exist.', 3);
        }
        $contents = $runtime->readFile($confPath);
        $parsed = ApacheParser::parseFile($confPath, $contents, $config->readonlyVhosts);
        if ($parsed['readonly']) {
            throw new BrokerException('This vhost is managed externally and cannot be deleted by the panel.', 3);
        }

        $this->disableSite($runtime, $config, $domain);
        $available = $this->siteAvailablePath($config, $domain);
        $enabled = rtrim($config->vhostDir, '/') . '/' . $domain . '.conf';
        foreach (array_unique([$confPath, $available, $enabled]) as $path) {
            if ($runtime->fileExists($path)) {
                $runtime->deleteFile($path);
            }
        }

        try {
            $this->validate($runtime, $config);
            $applied = $this->reload($runtime, $config, 'auto');
        } catch (BrokerException $e) {
            $runtime->writeFile($confPath, $contents, 0644);
            $this->enableSite($runtime, $config, $domain, $confPath);
            try {
                $this->reload($runtime, $config, 'auto');
            } catch (BrokerException) {
            }
            throw new BrokerException(
                'Apache would fail without this vhost; the file was restored. ' . $e->getMessage(),
                1
            );
        }

        return [
            'domain' => $domain,
            'deleted' => $confPath,
            'web_root_preserved' => $parsed['root'],
            'apply' => $applied,
        ];
    }

    public function reload(Runtime $runtime, Config $config, string $mode = 'auto', array $expectPorts = []): array
    {
        $this->validate($runtime, $config);
        $unit = $config->webService;
        fwrite(STDERR, "==> Applying via systemctl reload {$unit}\n");
        $result = $runtime->exec(['/usr/bin/systemctl', 'reload', $unit], null, 60);
        if (!$result->ok()) {
            if ($mode === 'restart' || $mode === 'auto') {
                fwrite(STDERR, "==> Applying via systemctl restart {$unit} (brief connection drop)\n");
                $result = $runtime->exec(['/usr/bin/systemctl', 'restart', $unit], null, 60);
                if ($result->ok()) {
                    $this->assertActive($runtime, $unit);
                    return ['path' => 'restart', 'address' => '', 'admin_spec' => 'n/a', 'admin_enabled' => false];
                }
            }
            $detail = trim($result->stderr . "\n" . $result->stdout);
            throw new BrokerException(
                "systemctl reload {$unit} failed" . ($detail !== '' ? ': ' . $detail : '.'),
                1
            );
        }
        $this->assertActive($runtime, $unit);
        fwrite(STDERR, "==> Apache apply path: systemctl (unit {$unit})\n");
        return ['path' => 'systemctl', 'address' => '', 'admin_spec' => 'n/a', 'admin_enabled' => false];
    }

    public function backupPaths(Config $config): array
    {
        $paths = [$config->vhostDir];
        if ($config->vhostAvailableDir !== '' && $config->vhostAvailableDir !== $config->vhostDir) {
            $paths[] = $config->vhostAvailableDir;
        }
        return $paths;
    }

    public function version(Runtime $runtime, Config $config): array
    {
        $ctl = $this->apacheCtl($runtime, $config);
        $r = $runtime->exec(array_merge($ctl, ['-v']));
        $raw = trim($r->stdout !== '' ? $r->stdout : $r->stderr);
        $version = $raw;
        if (preg_match('/Apache\/([0-9.]+)/', $raw, $m)) {
            $version = $m[1];
        }
        return [
            'version' => $version,
            'raw' => explode("\n", $raw)[0] ?? $raw,
            'service' => $config->webService,
            'label' => 'Apache',
            'stack' => 'lamp',
        ];
    }

    public function mainConfigPath(Config $config): string
    {
        return $config->webService === 'httpd' ? self::HTTPD_CONF : self::APACHE2_CONF;
    }

    private function validate(Runtime $runtime, Config $config): void
    {
        $ctl = $this->apacheCtl($runtime, $config);
        foreach (array_merge($ctl, [$this->mainConfigPath($config), $config->vhostDir, $config->vhostAvailableDir]) as $token) {
            if ($token !== '' && preg_match('/\s/', $token) === 1) {
                throw new BrokerException("Apache path contains whitespace: '{$token}'", 1);
            }
        }
        $result = $runtime->exec(array_merge($ctl, ['-t']), null, 20);
        if (!$result->ok()) {
            $detail = trim($result->stderr . "\n" . $result->stdout);
            throw new BrokerException(
                'Apache rejected the config: ' . ($detail !== '' ? $detail : 'configtest failed'),
                1
            );
        }
    }

    /** @return list<string> */
    private function apacheCtl(Runtime $runtime, Config $config): array
    {
        foreach ([$config->apacheCtl, '/usr/sbin/apache2ctl', '/usr/sbin/apachectl', '/usr/sbin/httpd'] as $bin) {
            if ($runtime->fileExists($bin)) {
                return [$bin];
            }
        }
        return ['/usr/sbin/apachectl'];
    }

    private function debianLayout(Runtime $runtime, Config $config): bool
    {
        return $runtime->isDir($config->vhostAvailableDir) || $runtime->isDir(self::SITES_AVAILABLE);
    }

    private function siteAvailablePath(Config $config, string $domain): string
    {
        if ($config->vhostAvailableDir !== '') {
            return rtrim($config->vhostAvailableDir, '/') . '/' . $domain . '.conf';
        }
        return rtrim($config->vhostDir, '/') . '/' . $domain . '.conf';
    }

    private function existingPath(Runtime $runtime, Config $config, string $domain): ?string
    {
        foreach ($this->listVhostFiles($runtime, $config) as $entry) {
            if (basename($entry['path'], '.conf') === $domain) {
                return $entry['path'];
            }
            try {
                $parsed = ApacheParser::parseFile(
                    $entry['path'],
                    $runtime->readFile($entry['path']),
                    $config->readonlyVhosts,
                    $entry['enabled']
                );
            } catch (\Throwable) {
                continue;
            }
            if (($parsed['domain'] ?? '') === $domain || in_array($domain, $parsed['domains'] ?? [], true)) {
                return $entry['path'];
            }
        }
        $candidate = $this->siteAvailablePath($config, $domain);
        return $runtime->fileExists($candidate) ? $candidate : null;
    }

    /**
     * Debian: sites-enabled (active, often symlinks) then sites-available leftovers (disabled).
     * EL: a single vhost dir. Dedup by real path, then basename, then ServerName.
     *
     * @return list<array{path:string,enabled:bool}>
     */
    private function listVhostFiles(Runtime $runtime, Config $config): array
    {
        $enabledDir = rtrim($config->vhostDir, '/');
        $availableDir = rtrim((string) $config->vhostAvailableDir, '/');
        $split = $availableDir !== '' && $availableDir !== $enabledDir;

        $out = [];
        $seenCanon = [];
        $seenBase = [];

        $push = static function (string $path, bool $enabled) use ($runtime, &$out, &$seenCanon, &$seenBase): void {
            $canon = $runtime->realPath($path);
            if ($canon === '') {
                $canon = $path;
            }
            if (isset($seenCanon[$canon])) {
                return;
            }
            $base = strtolower(basename($path));
            if (isset($seenBase[$base])) {
                return;
            }
            $seenCanon[$canon] = true;
            $seenBase[$base] = true;
            $out[] = ['path' => $path, 'enabled' => $enabled];
        };

        foreach ($runtime->glob($enabledDir . '/*.conf') as $file) {
            $read = $file;
            if ($split) {
                $avail = $availableDir . '/' . basename($file);
                if ($runtime->fileExists($avail)) {
                    $read = $avail;
                }
            }
            $push($read, true);
        }
        if ($split) {
            foreach ($runtime->glob($availableDir . '/*.conf') as $file) {
                $push($file, false);
            }
        }

        return $out;
    }

    private function enableSite(Runtime $runtime, Config $config, string $domain, string $confPath): void
    {
        if ($this->debianLayout($runtime, $config) && $runtime->fileExists('/usr/sbin/a2ensite')) {
            $r = $runtime->exec(['/usr/sbin/a2ensite', $domain], null, 15);
            if (!$r->ok()) {
                throw new BrokerException(trim($r->stderr . "\n" . $r->stdout) ?: 'a2ensite failed.', 1);
            }
            return;
        }
        $enabled = rtrim($config->vhostDir, '/') . '/' . $domain . '.conf';
        if ($enabled !== $confPath && !$runtime->fileExists($enabled)) {
            $runtime->writeFile($enabled, $runtime->readFile($confPath), 0644);
        }
    }

    private function disableSite(Runtime $runtime, Config $config, string $domain): void
    {
        if ($this->debianLayout($runtime, $config) && $runtime->fileExists('/usr/sbin/a2dissite')) {
            $runtime->exec(['/usr/sbin/a2dissite', $domain], null, 15);
        }
    }

    private function assertActive(Runtime $runtime, string $unit): void
    {
        $active = $runtime->exec(['/usr/bin/systemctl', 'is-active', $unit]);
        if (trim($active->stdout) !== 'active' && !$active->ok()) {
            throw new BrokerException("Apache is not active after apply (systemctl is-active {$unit} failed).", 1);
        }
    }

    private function ensureLogDir(Runtime $runtime, Config $config): void
    {
        $dir = $config->webLogDir;
        if (!$runtime->isDir($dir)) {
            $runtime->mkdir($dir, 0755);
            $runtime->chown($dir, $config->webUser, $config->webUser);
        }
    }

    private function render(
        Runtime $runtime,
        Config $config,
        string $domain,
        string $root,
        string $type,
        ?string $phpVersion,
        ?string $upstream,
    ): string {
        $logs = $config->webLogDir;
        $phpBlock = '';
        $proxyBlock = '';
        $dirBlock = '';
        if ($type === 'php' && $phpVersion !== null) {
            $sock = $config->phpFpmUnixPath($phpVersion, $runtime);
            $phpBlock = <<<PHP
    <FilesMatch \\.php\$>
        SetHandler "proxy:unix:{$sock}|fcgi://localhost"
    </FilesMatch>

PHP;
        }
        if ($type === 'proxy' && $upstream !== null) {
            $proxyBlock = "    ProxyPreserveHost On\n    ProxyPass / http://{$upstream}/\n    ProxyPassReverse / http://{$upstream}/\n";
        }
        if ($type !== 'proxy') {
            $dirBlock = <<<DIR
    DocumentRoot {$root}
    <Directory {$root}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

DIR;
        }

        return <<<EOF
<VirtualHost *:80>
    ServerName {$domain}
{$dirBlock}{$phpBlock}{$proxyBlock}    ErrorLog  {$logs}/{$domain}-error.log
    CustomLog {$logs}/{$domain}-access.log combined
</VirtualHost>

EOF;
    }
}
