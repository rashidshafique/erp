<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MouldingWorkList extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['list_date' => 'date', 'issued_at' => 'datetime'];
    }

    public function items(): HasMany { return $this->hasMany(MouldingWorkListItem::class)->orderBy('sequence'); }
}
