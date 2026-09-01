<?php

namespace App\Services\Broker;

use RuntimeException;

final class BrokerCallException extends RuntimeException
{
    public function __construct(string $message, public readonly int $errorCode = 1)
    {
        parent::__construct($message, $errorCode);
    }
}
