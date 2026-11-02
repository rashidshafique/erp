<?php

namespace App\Models;

use App\Support\AttachableEntities;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasUuids;

    /** Container key for the main photos already looked up this request (or job); scoped, so nothing carries over between requests. */
    public const COVERS = 'attachments.covers';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $attachment) {
            $attachment->document_id ??= $attachment->id;
        });
        static::saved(fn () => static::forgetCovers());
        static::deleted(fn () => static::forgetCovers());
    }

    protected function casts(): array
    {
        return ['removed_at' => 'datetime', 'superseded_at' => 'datetime', 'size_bytes' => 'integer', 'version' => 'integer'];
    }

    /** Files shown on a record: not removed, and not replaced by a newer version. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('removed_at')->whereNull('superseded_at');
    }

    /** Earlier versions of the same document, newest first. */
    public function olderVersions(): Builder
    {
        return static::where('document_id', $this->document_id)->where('version', '<', $this->version)->whereNull('removed_at')->orderByDesc('version');
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/png', 'image/jpeg', 'image/webp'], true);
    }

    /** The main photo of a record: the one marked as main, else the newest photo, else the newest image. ID documents and contracts are never used. */
    public static function coverFor(string $type, string $id): ?self
    {
        $q = fn () => static::where('attachable_type', $type)->where('attachable_id', $id)->current()
            ->whereNotIn('category', AttachableEntities::SENSITIVE_CATEGORIES)->whereIn('mime_type', ['image/png', 'image/jpeg', 'image/webp']);

        return $q()->where('is_cover', true)->first()
            ?? $q()->where('category', 'PHOTO')->orderByDesc('created_at')->orderByDesc('id')->first()
            ?? $q()->orderByDesc('created_at')->orderByDesc('id')->first();
    }

    /**
     * The main photo of many records at once, in one query, keyed by record. Same choice as coverFor.
     *
     * @param  list<string>  $ids
     * @return array<string, self>
     */
    public static function coversFor(string $type, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return static::where('attachable_type', $type)->whereIn('attachable_id', $ids)->current()
            ->whereNotIn('category', AttachableEntities::SENSITIVE_CATEGORIES)
            ->whereIn('mime_type', ['image/png', 'image/jpeg', 'image/webp'])
            ->orderByDesc('is_cover')->orderByRaw("case when category = 'PHOTO' then 0 else 1 end")
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get()->unique('attachable_id')->keyBy('attachable_id')->all();
    }

    /**
     * Looks up the main photo of many records in one query and keeps them for the rest of the request, so a list that shows a
     * photo on every row (lots, orders, runs) asks cachedCover() without a query per row. Records already kept are skipped.
     *
     * @param  array<int, string|int|null>  $ids
     */
    public static function primeCovers(string $type, array $ids): void
    {
        $covers = static::keptCovers();
        $ids = array_values(array_filter(array_unique(array_map('strval', array_filter($ids))), fn (string $id) => ! $covers->offsetExists($type.':'.$id)));
        if ($ids === []) {
            return;
        }
        $found = static::coversFor($type, $ids);
        foreach ($ids as $id) {
            $covers[$type.':'.$id] = $found[$id] ?? null;
        }
    }

    /** The main photo of a record, kept for the rest of the request (see primeCovers). Same choice as coverFor. */
    public static function cachedCover(string $type, string $id): ?self
    {
        $covers = static::keptCovers();
        if (! $covers->offsetExists($type.':'.$id)) {
            $covers[$type.':'.$id] = static::coverFor($type, $id);
        }

        return $covers[$type.':'.$id];
    }

    /** Drops the kept main photos so the next look-up goes to the database. */
    public static function forgetCovers(): void
    {
        app()->forgetInstance(self::COVERS);
    }

    /** @return \ArrayObject<string, self|null> "type:id" => main photo, or null when the record has none */
    private static function keptCovers(): \ArrayObject
    {
        if (! app()->bound(self::COVERS)) {
            app()->scoped(self::COVERS, fn () => new \ArrayObject);
        }

        return app(self::COVERS);
    }

    /**
     * Download URLs of the main photo of each record, for dropdowns that show a thumbnail beside every option. The result is
     * kept for the rest of the request, so a grid that asks once per row runs the query once.
     *
     * @param  list<string>  $ids
     * @return array<string, string> record id => photo URL
     */
    public static function coverUrlsFor(string $type, array $ids): array
    {
        static $memo = [];
        $ids = array_values(array_unique(array_filter($ids)));
        sort($ids);
        $key = $type.'|'.md5(implode(',', $ids));

        return $memo[$key] ??= array_map(fn (self $a) => route('attachments.download', $a), static::coversFor($type, $ids));
    }

    public function isInlineViewable(): bool
    {
        return in_array($this->mime_type, ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'], true);
    }
}
