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

    public function isInlineViewable(): bool
    {
        return in_array($this->mime_type, ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'], true);
    }
}
