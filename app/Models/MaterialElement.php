<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One element of a material's chemistry: its typical percentage, spread and how much of it reaches the iron. */
class MaterialElement extends Model
{
    use HasUuids;

    protected $fillable = ['material_id', 'element', 'nominal_pct', 'min_pct', 'max_pct', 'recovery_pct'];

    protected function casts(): array
    {
        return ['nominal_pct' => 'decimal:4', 'min_pct' => 'decimal:4', 'max_pct' => 'decimal:4', 'recovery_pct' => 'decimal:2'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
