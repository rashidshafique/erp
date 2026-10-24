<?php

namespace App\Domains\Purchasing\Models;

use App\Models\Material;
use App\Models\MaterialLot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderLine extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['ordered_qty' => 'float', 'received_qty' => 'float', 'unit_price' => 'float', 'tax_percent' => 'float', 'line_net' => 'float', 'line_tax' => 'float'];
    }

    public function order(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function material(): BelongsTo { return $this->belongsTo(Material::class); }
    public function lots(): HasMany { return $this->hasMany(MaterialLot::class, 'purchase_order_line_id'); }

    public function outstanding(): float
    {
        return max(0.0, round($this->ordered_qty - $this->received_qty, 3));
    }
}
