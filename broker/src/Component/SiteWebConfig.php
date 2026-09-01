<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Component;

use AzerioidPanel\Broker\BrokerException;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Database\BrokerConfigWriter;
use AzerioidPanel\Broker\Runtime;

final class SiteWebConfig
{
    /** @return array<string, mixed> */
    public static function pathsFor(string $componentId, OsRelease $os): array
    {
        return match ($componentId) {
            'nginx' => $os->distroKey === 'el'
                ? [
                    'web_server' => 'nginx',
                    'web_service' => 'nginx',
                    'vhost_format' => 'nginx',
                    'site_web_server' => 'nginx',
                    'paths' => [
                        'vhost_dir' => '/etc/nginx/conf.d',
                        'vhost_available' => '/etc/nginx/conf.d',
                        'web_log_dir' => '/var/log/nginx',
                    ],
                ]
                : [
                    'web_server' => 'nginx',
                    'web_service' => 'nginx',
                    'vhost_format' => 'nginx',
                    'site_web_server' => 'nginx',
                    'paths' => [
                        'vhost_dir' => '/etc/nginx/sites-enabled',
                        'vhost_available' => '/etc/nginx/sites-available',
                        'web_log_dir' => '/var/log/nginx',
                    ],
                ],
            'apache' => $os->distroKey === 'el'
                ? [
                    'web_server' => 'apache',
                    'web_service' => 'httpd',
                    'vhost_format' => 'apache',
                    'site_web_server' => 'apache',
                    'stack' => 'lamp',
                    'paths' => [
                        'vhost_dir' => '/etc/httpd/conf.d/vhost',
                        'vhost_available' => '/etc/httpd/conf.d/vhost',
                        'web_log_dir' => '/var/log/httpd',
                        'apache_ctl' => '/usr/sbin/apachectl',
                    ],
                ]
                : [
                    'web_server' => 'apache',
                    'web_service' => 'apache2',
                    'vhost_format' => 'apache',
                    'site_web_server' => 'apache',
                    'stack' => 'lamp',
                    'paths' => [
                        'vhost_dir' => '/etc/apache2/sites-enabled',
                        'vhost_available' => '/etc/apache2/sites-available',
                        'web_log_dir' => '/var/log/apache2',
                        'apache_ctl' => '/usr/sbin/apache2ctl',
                    ],
                ],
            'caddy' => [
                'web_server' => 'caddy',
                'web_service' => 'caddy',
                'vhost_format' => 'caddyfile',
                'site_web_server' => 'caddy',
                'stack' => 'lcmp',
                'paths' => [
                    'vhost_dir' => '/etc/caddy/conf.d',
                    'vhost_available' => '',
                    'web_log_dir' => '/var/log/caddy',
                ],
            ],
            default => throw new BrokerException('Component is not a site web server.', 2),
        };
    }

    public static function apply(Runtime $runtime, Config $config, string $componentId, OsRelease $os): void
    {
        $configPath = getenv('AZERIOID_PANEL_CONFIG') ?: getenv('LACMP_PANEL_CONFIG') ?: '/etc/azerioid-panel/broker.json';
        BrokerConfigWriter::merge($runtime, $configPath, self::pathsFor($componentId, $os));
    }
}
