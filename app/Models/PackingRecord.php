<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackingRecord extends Model
{
    use HasUuids;

    protected $table = 'packing_records';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['packed_at' => 'datetime'];
    }

    public function items(): HasMany { return $this->hasMany(PackingRecordItem::class); }
}
