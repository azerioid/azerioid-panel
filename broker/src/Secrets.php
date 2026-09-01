<?php
declare(strict_types=1);

namespace AzerioidPanel\Broker;

final class Secrets
{
    public static function generatePassword(int $length = 24): string
    {
        $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $bytes = random_bytes($length);
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[ord($bytes[$i]) % ($max + 1)];
        }
        return Validator::password($password);
    }
}
