<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSpecification extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_revision_id',
        'specification_code',
        'revision_code',
        'title',
        'specification_type',
        'status',
        'governing_standard_code',
        'governing_standard_edition',
        'material_grade',
        'drawing_number',
        'drawing_revision',
        'scope',
        'notes',
        'approved_at',
        'effective_at',
        'expires_at',
        'superseded_at',
        'prepared_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'effective_at' => 'datetime', 'expires_at' => 'datetime', 'superseded_at' => 'datetime'];
    }

    public function productRevision(): BelongsTo
    {
        return $this->belongsTo(ProductRevision::class);
    }
}
