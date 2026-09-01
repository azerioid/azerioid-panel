<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Tests;

use AzerioidPanel\Broker\BrokerException;
use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\FakeRuntime;
use AzerioidPanel\Broker\Web\ApacheDriver;
use AzerioidPanel\Broker\Web\CaddyDriver;
use PHPUnit\Framework\TestCase;

final class WebServerMainConfigTest extends TestCase
{
    public function test_caddy_main_config_is_caddyfile_constant(): void
    {
        $cfg = new Config();
        $driver = new CaddyDriver();
        $this->assertSame('/etc/caddy/Caddyfile', CaddyDriver::CADDYFILE);
        $this->assertSame('/etc/caddy/Caddyfile', Config::CADDYFILE);
        $this->assertSame('/etc/caddy/Caddyfile', $driver->mainConfigPath($cfg));
        $this->assertDoesNotMatchRegularExpression('/\s/', $driver->mainConfigPath($cfg));
    }

    public function test_apache_main_config_is_per_distro_constant(): void
    {
        $deb = new Config();
        $deb->webService = 'apache2';
        $this->assertSame('/etc/apache2/apache2.conf', (new ApacheDriver($deb))->mainConfigPath($deb));
        $this->assertSame('/etc/apache2/apache2.conf', ApacheDriver::APACHE2_CONF);

        $el = new Config();
        $el->webService = 'httpd';
        $this->assertSame('/etc/httpd/conf/httpd.conf', (new ApacheDriver($el))->mainConfigPath($el));
        $this->assertSame('/etc/httpd/conf/httpd.conf', ApacheDriver::HTTPD_CONF);
        $this->assertDoesNotMatchRegularExpression('/\s/', ApacheDriver::SITES_AVAILABLE);
        $this->assertDoesNotMatchRegularExpression('/\s/', ApacheDriver::HTTPD_VHOST_DIR);
    }

    public function test_load_coerces_spaced_caddyfile_typo(): void
    {
        $rt = new FakeRuntime();
        $rt->files['/etc/azerioid-panel/broker.json'] = json_encode([
            'paths' => ['caddyfile' => '/etc/caddy/Caddy file'],
        ], JSON_THROW_ON_ERROR);
        $cfg = Config::load('/etc/azerioid-panel/broker.json', $rt);
        $this->assertSame('/etc/caddy/Caddyfile', $cfg->caddyfile);
    }

    public function test_assert_main_config_rejects_whitespace(): void
    {
        $this->expectException(BrokerException::class);
        Config::assertMainConfigPath('/etc/caddy/Caddy file', Config::CADDYFILE);
    }
}
