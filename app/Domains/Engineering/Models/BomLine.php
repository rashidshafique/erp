<?php

namespace App\Domains\Engineering\Models;

use App\Models\Material;
use App\Models\ProductRevision;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One item a product revision uses, and how much of it per casting, per mould or per kg of metal poured. */
class BomLine extends Model
{
    use HasUuids;

    /** @var array<string, string> */
    public const BASES = ['PER_CASTING' => 'per casting', 'PER_MOULD' => 'per mould', 'PER_KG_POURED' => 'per kg poured'];

    protected $fillable = ['product_revision_id', 'material_id', 'quantity', 'basis', 'notes'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    public function productRevision(): BelongsTo
    {
        return $this->belongsTo(ProductRevision::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }
}
