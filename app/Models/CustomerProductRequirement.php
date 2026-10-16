<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerProductRequirement extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'product_revision_id',
        'product_specification_id',
        'requirement_code',
        'revision_code',
        'title',
        'status',
        'customer_document_number',
        'customer_document_revision',
        'customer_grade',
        'market_or_destination',
        'language_code',
        'scope',
        'special_instructions',
        'notes',
        'customer_approved_at',
        'effective_at',
        'expires_at',
        'superseded_at',
    ];

    protected function casts(): array
    {
        return ['customer_approved_at' => 'datetime', 'effective_at' => 'datetime', 'expires_at' => 'datetime', 'superseded_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function productRevision(): BelongsTo
    {
        return $this->belongsTo(ProductRevision::class);
    }
}
