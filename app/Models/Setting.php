<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key/value application settings, e.g. the default night allowance fraction.
 *
 * Values are stored as strings so money-related settings stay exact when read
 * back into bcmath. Use read() to fetch one with a fallback default.
 */
class Setting extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'value', 'group'];

    /**
     * The stored value for a key, or the given default when it is not set.
     */
    public static function read(string $key, string $default): string
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value ?? $default;
    }
}
