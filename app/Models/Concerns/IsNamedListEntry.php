<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * An entry of an editable settings list: its name is unique ignoring case, and its code is made from the name.
 */
trait IsNamedListEntry
{
    /** Collapses runs of spaces and trims, so "Silica  sand " and "Silica sand" are the same name. */
    public static function tidyName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    public static function nameTaken(string $name, ?string $ignoreId = null): bool
    {
        return static::whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    /** A unique code made from the name; codes of deleted entries stay taken. */
    public static function freeCode(string $name): string
    {
        $base = Str::upper(Str::limit(Str::slug($name, '_'), 30, '')) ?: 'ENTRY';
        $code = $base;
        for ($i = 2; static::withTrashed()->where('code', $code)->exists(); $i++) {
            $code = $base.'_'.$i;
        }

        return $code;
    }

    public static function nextSortOrder(): int
    {
        return (int) static::withTrashed()->max('sort_order') + 10;
    }
}
