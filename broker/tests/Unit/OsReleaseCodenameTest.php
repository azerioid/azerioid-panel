<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Tests;

use AzerioidPanel\Broker\Component\OsRelease;
use AzerioidPanel\Broker\FakeRuntime;
use PHPUnit\Framework\TestCase;

final class OsReleaseCodenameTest extends TestCase
{
    public function test_ubuntu_codename_from_os_release(): void
    {
        $rt = new FakeRuntime();
        $rt->files['/etc/os-release'] = "ID=ubuntu\nVERSION_ID=\"24.04\"\nVERSION_CODENAME=noble\n";
        $os = OsRelease::detect($rt);
        $this->assertSame('noble', $os->codename);
        $this->assertSame('ubuntu', $os->distroKey);
    }
}
