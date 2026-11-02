<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    /** Container key for the settings loaded once per request (or job); scoped, so nothing carries over between requests. */
    public const LOADED = 'app-settings.loaded';

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetLoaded());
        static::deleted(fn () => static::forgetLoaded());
    }

    public static function getValue(string $key, ?string $default = null): ?string
    {
        return static::loaded()[$key] ?? $default;
    }

    public static function setValue(
        string $key,
        ?string $value,
        ?string $description = null
    ): void {
        static::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'description' => $description,
            ]
        );
    }

    /** Drops the loaded settings so the next read goes to the database. */
    public static function forgetLoaded(): void
    {
        app()->forgetInstance(self::LOADED);
    }

    /** @return array<string, string|null> */
    private static function loaded(): array
    {
        if (! app()->bound(self::LOADED)) {
            app()->scoped(self::LOADED, fn () => static::query()->pluck('value', 'key')->all());
        }

        return app(self::LOADED);
    }
}
