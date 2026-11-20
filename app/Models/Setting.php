<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/** Simple key/value system settings, loaded once per request. */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    protected static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        if (static::$cache === null) {
            try {
                static::$cache = Schema::hasTable('settings') ? static::pluck('value', 'key')->all() : [];
            } catch (\Throwable) {
                static::$cache = [];
            }
        }

        return array_key_exists($key, static::$cache) ? static::$cache[$key] : $default;
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]);
        }
        static::$cache = null;
    }

    public static function flushCache(): void
    {
        static::$cache = null;
    }
}
