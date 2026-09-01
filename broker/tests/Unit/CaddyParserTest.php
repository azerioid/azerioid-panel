<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Tests;

use LacmpPanel\Broker\CaddyParser;
use PHPUnit\Framework\TestCase;

final class CaddyParserTest extends TestCase
{
    public function test_parses_lacmp_php_vhost(): void
    {
        $contents = file_get_contents(__DIR__ . '/../fixtures/vhost-php.conf');
        $parsed = CaddyParser::parseFile('/etc/caddy/conf.d/example.com.conf', $contents, ['projob.az']);

        $this->assertSame(['example.com'], $parsed['domains']);
        $this->assertSame('/data/www/example.com', $parsed['root']);
        $this->assertSame('php', $parsed['type']);
        $this->assertSame('8.4', $parsed['php_version']);
        $this->assertFalse($parsed['readonly']);
        $this->assertTrue($parsed['tls']);
    }

    public function test_marks_reverse_proxy_readonly(): void
    {
        $contents = file_get_contents(__DIR__ . '/../fixtures/vhost-projob.conf');
        $parsed = CaddyParser::parseFile('/etc/caddy/conf.d/projob.az.conf', $contents, ['projob.az']);

        $this->assertSame('projob.az', $parsed['domain']);
        $this->assertSame('proxy', $parsed['type']);
        $this->assertTrue($parsed['readonly']);
        $this->assertSame('127.0.0.1:8000', $parsed['reverse_proxy']);
    }

    public function test_marks_default_site_readonly(): void
    {
        $contents = file_get_contents(__DIR__ . '/../fixtures/vhost-default.conf');
        $parsed = CaddyParser::parseFile('/etc/caddy/conf.d/default.conf', $contents, ['projob.az']);
        $this->assertTrue($parsed['readonly']);
        $this->assertFalse($parsed['tls']);
    }

    public function test_marks_panel_localhost_vhost_readonly(): void
    {
        $contents = <<<'CADDY'
http://127.0.0.1:3169 {
    bind 127.0.0.1
    root * /usr/local/lib/lacmp-panel/web/public
    php_fastcgi unix//run/php/lacmp-panel.sock
}
CADDY;
        $parsed = CaddyParser::parseFile('/etc/caddy/conf.d/lacmp-panel.conf', $contents, ['projob.az']);
        $this->assertTrue($parsed['readonly']);
        $this->assertSame('php', $parsed['type']);
    }

    public function test_extracts_www_redirect_domains(): void
    {
        $contents = <<<'CADDY'
example.com, www.example.com {
    root * /data/www/example.com
    php_fastcgi unix//run/php/php8.4-fpm.sock
}
CADDY;
        $parsed = CaddyParser::parseFile('/etc/caddy/conf.d/example.com.conf', $contents, []);
        $this->assertSame(['example.com', 'www.example.com'], $parsed['domains']);
    }
}
