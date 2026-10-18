<?php

namespace App\Support;

use App\Models\AppSetting;

/** Whether files keep a version history, and for which categories. Off by default; every file has a version number either way. */
class DocumentVersioning
{
    public const DEFAULT_CATEGORIES = ['DRAWING', 'SPECIFICATION', 'CERTIFICATE', 'CONTRACT'];

    private static ?array $cache = null;

    public static function enabled(): bool
    {
        return self::settings()['enabled'];
    }

    /** Whether a record shows the files of the records it comes from. On unless switched off. */
    public static function showRelated(): bool
    {
        return self::settings()['related'];
    }

    /** @return list<string> */
    public static function categories(): array
    {
        return self::settings()['categories'];
    }

    public static function appliesTo(string $category): bool
    {
        return self::enabled() && in_array($category, self::categories(), true);
    }

    /** @param list<string> $categories */
    public static function save(bool $enabled, array $categories, bool $related = true): void
    {
        AppSetting::setValue('documents.show_related', $related ? '1' : '0', "Show the files of a record's related records");
        AppSetting::setValue('documents.versioning', $enabled ? '1' : '0', 'Keep a version history of files');
        AppSetting::setValue('documents.versioned_categories', implode(',', array_values(array_intersect(array_keys(AttachableEntities::CATEGORIES), $categories))), 'File categories that keep versions');
        self::$cache = null;
    }

    /** @return array{enabled: bool, related: bool, categories: list<string>} */
    private static function settings(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        try {
            $saved = AppSetting::getValue('documents.versioned_categories');

            return self::$cache = [
                'enabled' => AppSetting::getValue('documents.versioning', '0') === '1',
                'related' => AppSetting::getValue('documents.show_related', '1') === '1',
                'categories' => $saved === null ? self::DEFAULT_CATEGORIES : array_values(array_filter(explode(',', $saved))),
            ];
        } catch (\Throwable) {
            return ['enabled' => false, 'related' => true, 'categories' => self::DEFAULT_CATEGORIES];
        }
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
