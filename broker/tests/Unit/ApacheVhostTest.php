<?php
declare(strict_types=1);

namespace LacmpPanel\Broker\Tests;

use LacmpPanel\Broker\Config;
use LacmpPanel\Broker\FakeRuntime;
use LacmpPanel\Broker\Kernel;
use PHPUnit\Framework\TestCase;

final class ApacheVhostTest extends TestCase
{
    private function lampConfig(): Config
    {
        $cfg = new Config();
        $cfg->stack = 'lamp';
        $cfg->webServer = 'apache';
        $cfg->webService = 'apache2';
        $cfg->vhostFormat = 'apache';
        $cfg->vhostDir = '/etc/apache2/sites-enabled';
        $cfg->vhostAvailableDir = '/etc/apache2/sites-available';
        $cfg->webLogDir = '/var/log/apache2';
        $cfg->controllableServices = ['apache2', 'mariadb'];
        $cfg->phpUser = 'www-data';
        $cfg->phpGroup = 'www-data';
        $cfg->webUser = 'www-data';
        return $cfg;
    }

    private function runtime(): FakeRuntime
    {
        $rt = new FakeRuntime();
        $rt->dirs['/etc/apache2'] = true;
        $rt->dirs['/etc/apache2/sites-available'] = true;
        $rt->dirs['/etc/apache2/sites-enabled'] = true;
        $rt->dirs['/var/log/apache2'] = true;
        $rt->files['/usr/sbin/apachectl'] = '';
        $rt->files['/usr/sbin/a2ensite'] = '';
        $rt->files['/usr/sbin/a2dissite'] = '';
        $rt->script(['/usr/sbin/apachectl', '-t'], 0, 'Syntax OK');
        $rt->script(['/usr/sbin/a2ensite', 'shop.example.com'], 0, 'Enabling site shop.example.com');
        $rt->script(['/usr/sbin/a2dissite', 'shop.example.com'], 0);
        $rt->script(['/usr/bin/systemctl', 'reload', 'apache2'], 0);
        $rt->script(['/usr/bin/systemctl', 'is-active', 'apache2'], 0, "active\n");
        return $rt;
    }

    public function test_adds_php_vhost_as_apache_config(): void
    {
        $rt = $this->runtime();
        $kernel = new Kernel($this->lampConfig(), $rt);
        ob_start();
        $code = $kernel->run(['broker', 'vhost.add', 'shop.example.com', '/data/www/shop.example.com', 'php', '8.4'], []);
        $out = ob_get_clean();
        $this->assertSame(0, $code, $out);
        $path = '/etc/apache2/sites-available/shop.example.com.conf';
        $this->assertArrayHasKey($path, $rt->files);
        $this->assertStringContainsString('ServerName shop.example.com', $rt->files[$path]);
        $this->assertStringContainsString('proxy:unix:/run/php/php8.4-fpm.sock', $rt->files[$path]);
        $this->assertStringNotContainsString('php_fastcgi', $rt->files[$path]);
        $decoded = json_decode($out, true);
        $this->assertSame('systemctl', $decoded['data']['apply']['path']);
    }

    public function test_rolls_back_when_apachectl_fails(): void
    {
        $rt = $this->runtime();
        $rt->script(['/usr/sbin/apachectl', '-t'], 1, '', 'AH00526: Syntax error');
        $kernel = new Kernel($this->lampConfig(), $rt);
        ob_start();
        $code = $kernel->run(['broker', 'vhost.add', 'shop.example.com', '/data/www/shop.example.com', 'php', '8.4'], []);
        $out = ob_get_clean();
        $this->assertNotSame(0, $code);
        $this->assertArrayNotHasKey('/etc/apache2/sites-available/shop.example.com.conf', $rt->files);
        $this->assertStringContainsString('rolled back', $out);
    }

    public function test_deletes_apache_vhost(): void
    {
        $rt = $this->runtime();
        $rt->files['/etc/apache2/sites-available/shop.example.com.conf'] = "<VirtualHost *:80>\n    ServerName shop.example.com\n    DocumentRoot /data/www/shop.example.com\n</VirtualHost>\n";
        $rt->files['/etc/apache2/sites-enabled/shop.example.com.conf'] = $rt->files['/etc/apache2/sites-available/shop.example.com.conf'];
        $kernel = new Kernel($this->lampConfig(), $rt);
        ob_start();
        $code = $kernel->run(['broker', 'vhost.del', 'shop.example.com'], []);
        ob_get_clean();
        $this->assertSame(0, $code);
        $this->assertArrayNotHasKey('/etc/apache2/sites-available/shop.example.com.conf', $rt->files);
        $this->assertArrayNotHasKey('/etc/apache2/sites-enabled/shop.example.com.conf', $rt->files);
    }

    public function test_lists_each_vhost_once_despite_available_and_enabled(): void
    {
        $rt = $this->runtime();
        $panel = "<VirtualHost *:443>\n    ServerName 157.245.84.199\n    SSLEngine on\n    DocumentRoot /usr/local/lib/lacmp-panel/web/public\n</VirtualHost>\n";
        $ssl = "<VirtualHost *:443>\n    ServerName localhost\n    SSLEngine on\n    DocumentRoot /var/www/html\n</VirtualHost>\n";
        $user = "<VirtualHost *:80>\n    ServerName shop.example.com\n    DocumentRoot /data/www/shop.example.com\n</VirtualHost>\n";
        $disabled = "<VirtualHost *:80>\n    ServerName idle.example.com\n    DocumentRoot /data/www/idle.example.com\n</VirtualHost>\n";
        foreach (['lacmp-panel' => $panel, 'default-ssl' => $ssl, 'shop.example.com' => $user] as $name => $body) {
            $rt->files['/etc/apache2/sites-available/' . $name . '.conf'] = $body;
            $rt->files['/etc/apache2/sites-enabled/' . $name . '.conf'] = $body;
        }
        $rt->files['/etc/apache2/sites-available/idle.example.com.conf'] = $disabled;

        $kernel = new Kernel($this->lampConfig(), $rt);
        ob_start();
        $code = $kernel->run(['broker', 'vhost.list'], []);
        $out = ob_get_clean();
        $this->assertSame(0, $code, $out);
        $vhosts = json_decode($out, true)['data']['vhosts'] ?? [];
        $domains = array_column($vhosts, 'domain');
        $this->assertSame(array_values(array_unique($domains)), $domains);
        $this->assertCount(4, $vhosts);
        $byDomain = [];
        foreach ($vhosts as $row) {
            $byDomain[$row['domain']] = $row;
        }
        $this->assertTrue($byDomain['157.245.84.199']['readonly']);
        $this->assertTrue($byDomain['localhost']['readonly']);
        $this->assertFalse($byDomain['shop.example.com']['readonly']);
        $this->assertTrue($byDomain['shop.example.com']['enabled']);
        $this->assertFalse($byDomain['idle.example.com']['enabled']);
        $this->assertFalse($byDomain['idle.example.com']['readonly']);
    }
}
