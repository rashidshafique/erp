<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CastingLot extends Model
{
    use HasUuids;

    protected $fillable = [
        'lot_number',
        'pour_mould_assembly_id',
        'production_order_id',
        'product_id',
        'product_revision_id',
        'status',
        'quantity_poured',
        'quantity_good',
        'quantity_rejected',
        'gross_weight_kg',
        'net_casting_weight_kg',
        'created_from_pour_at',
        'closed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_poured' => 'integer',
            'quantity_good' => 'integer',
            'quantity_rejected' => 'integer',
            'gross_weight_kg' => 'decimal:3',
            'net_casting_weight_kg' => 'decimal:3',
            'created_from_pour_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function pourMouldAssembly(): BelongsTo
    {
        return $this->belongsTo(PourMouldAssembly::class);
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productRevision(): BelongsTo
    {
        return $this->belongsTo(ProductRevision::class);
    }

    public function stageEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CastingLotStageEvent::class)->orderBy('created_at');
    }

    public function inspections(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QualityInspection::class)->orderBy('created_at');
    }

    public function ncrs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NonconformanceReport::class)->orderBy('created_at');
    }

    public function packingItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PackingRecordItem::class);
    }

    public function sourceHeat(): ?Heat
    {
        return $this->pourMouldAssembly?->pour?->heat;
    }
}
