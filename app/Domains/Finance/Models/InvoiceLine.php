<?php

namespace App\Domains\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_price' => 'float', 'tax_percent' => 'float', 'line_net' => 'float', 'line_tax' => 'float', 'line_withheld' => 'float', 'taxes' => 'array'];
    }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
