<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Web;

use AzerioidPanel\Broker\Config;
use AzerioidPanel\Broker\Runtime;

interface WebServerDriver
{
    public function stackName(): string;

    public function webServiceName(): string;

    /** @return list<array<string,mixed>> */
    public function listVhosts(Runtime $runtime, Config $config): array;

    /**
     * @param  array{domain:string,root:string,type:string,php_version:?string,upstream:?string}  $spec
     * @return array<string,mixed>
     */
    public function addVhost(Runtime $runtime, Config $config, array $spec): array;

    /** @return array<string,mixed> */
    public function removeVhost(Runtime $runtime, Config $config, string $domain): array;

    /**
     * @param  list<int>  $expectPorts
     * @return array<string,mixed>
     */
    public function reload(Runtime $runtime, Config $config, string $mode = 'auto', array $expectPorts = []): array;

    /** @return list<string> */
    public function backupPaths(Config $config): array;

    /** @return array{version:string,raw:string,service:string,label:string,stack:string} */
    public function version(Runtime $runtime, Config $config): array;
}
