<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Web;

use AzerioidPanel\Broker\Config;

final class WebServers
{
    public static function for(Config $config): WebServerDriver
    {
        $driver = $config->siteWebServer !== '' ? $config->siteWebServer : $config->webServer;

        return match ($driver) {
            'nginx' => new NginxDriver($config),
            'apache', 'httpd' => new ApacheDriver($config),
            default => new CaddyDriver(),
        };
    }
}
