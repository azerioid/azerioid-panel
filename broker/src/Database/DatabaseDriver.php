<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Database;

interface DatabaseDriver
{
    public function engine(): string;

    public function isConfigured(): bool;

    /** @return list<array{name:string,size_bytes:int,table_count:int,users:list<array{user:string,host:string}>,protected:bool}> */
    public function list(): array;

    /** @return array{name:string,user:string,hosts:list<string>} */
    public function add(string $name, string $user, string $password): array;

    /** @return array{name:string,user:string,dropped:bool} */
    public function delete(string $name, string $user): array;

    /** @return array{user:string,reset:bool} */
    public function resetPassword(string $user, string $password): array;

    /** @return array{path:string,size_bytes:int,engine:string,name:string} */
    public function dump(string $name, string $outputPath): array;
}
