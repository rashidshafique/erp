<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['removed_at' => 'datetime', 'size_bytes' => 'integer'];
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/png', 'image/jpeg', 'image/webp'], true);
    }

    /** The main photo of a record: the one marked as main, else the newest photo, else the newest image of any category. */
    public static function coverFor(string $type, string $id): ?self
    {
        $q = fn () => static::where('attachable_type', $type)->where('attachable_id', $id)->whereNull('removed_at')
            ->whereIn('mime_type', ['image/png', 'image/jpeg', 'image/webp']);

        return $q()->where('is_cover', true)->first()
            ?? $q()->where('category', 'PHOTO')->orderByDesc('created_at')->orderByDesc('id')->first()
            ?? $q()->orderByDesc('created_at')->orderByDesc('id')->first();
    }

    public function isInlineViewable(): bool
    {
        return in_array($this->mime_type, ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'], true);
    }
}
