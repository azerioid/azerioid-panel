<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker\Database;

use AzerioidPanel\Broker\BrokerException;
use AzerioidPanel\Broker\Validator;

final class SqlIdent
{
    public static function mysql(string $name): string
    {
        if (!preg_match(Validator::DB_NAME_PATTERN, $name) && $name !== 'localhost' && $name !== '127.0.0.1') {
            throw new BrokerException('Unsafe SQL identifier.', 2);
        }

        return $name;
    }

    public static function postgres(string $name): string
    {
        if (!preg_match(Validator::DB_NAME_PATTERN, $name)) {
            throw new BrokerException('Unsafe SQL identifier.', 2);
        }

        return $name;
    }

    public static function escapeLiteral(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
