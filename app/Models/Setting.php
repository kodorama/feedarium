<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $setting_key
 * @property string|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class Setting extends Model
{
    protected $primaryKey = 'setting_key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'setting_key',
        'value',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $record = self::query()->find($key);

        return $record?->value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(
            ['setting_key' => $key],
            ['value' => (string) $value],
        );
    }
}
