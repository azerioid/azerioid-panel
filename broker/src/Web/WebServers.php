<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Web;

use LacmpPanel\Broker\Config;

final class WebServers
{
    public static function for(Config $config): WebServerDriver
    {
        return $config->stack === 'lamp' ? new ApacheDriver($config) : new CaddyDriver();
    }
}
