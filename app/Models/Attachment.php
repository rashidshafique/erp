<?php

namespace App\Models;

use App\Support\AttachableEntities;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $attachment) {
            $attachment->document_id ??= $attachment->id;
        });
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

    public function isInlineViewable(): bool
    {
        return in_array($this->mime_type, ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'], true);
    }
}
