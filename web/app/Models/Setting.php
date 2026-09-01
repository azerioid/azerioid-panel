<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::query()->find($key);
        if ($row === null) {
            return $default;
        }
        $decoded = json_decode((string) $row->value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $row->value;
    }

    public static function putSecret(string $key, string $plain): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => 'enc:' . \Illuminate\Support\Facades\Crypt::encryptString($plain)]
        );
    }

    public static function getSecret(string $key): ?string
    {
        $row = static::query()->find($key);
        if ($row === null || $row->value === null || $row->value === '') {
            return null;
        }
        $v = (string) $row->value;
        if (! str_starts_with($v, 'enc:')) {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString(substr($v, 4));
        } catch (\Throwable) {
            return null;
        }
    }

    public static function forgetSecret(string $key): void
    {
        static::query()->where('key', $key)->delete();
    }

    public static function secretIsSet(string $key): bool
    {
        return static::getSecret($key) !== null;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_string($value) ? $value : json_encode($value)]
        );
    }
}
