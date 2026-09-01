<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Tests;

use AzerioidPanel\Broker\Web\NginxParser;
use PHPUnit\Framework\TestCase;

final class NginxParserTest extends TestCase
{
    public function test_parses_php_vhost(): void
    {
        $contents = <<<'NGINX'
server {
    listen 80;
    server_name shop.example.com;
    root /data/www/shop.example.com;
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
}
NGINX;
        $parsed = NginxParser::parseFile('/etc/nginx/sites-available/shop.example.com.conf', $contents, []);
        $this->assertSame('shop.example.com', $parsed['domain']);
        $this->assertSame('php', $parsed['type']);
        $this->assertSame('8.4', $parsed['php_version']);
        $this->assertSame('/data/www/shop.example.com', $parsed['root']);
    }
}
