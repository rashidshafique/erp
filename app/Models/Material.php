<?php

namespace App\Models;

use App\Services\Stock\Stockable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model implements Stockable
{
    use HasUuids, SoftDeletes;

    /** @var array<string, string> */
    public const TYPES = [
        'METAL' => 'Metal', 'RETURN' => 'Foundry returns', 'ALLOY' => 'Alloy', 'FLUX' => 'Flux', 'FUEL' => 'Fuel',
        'SAND' => 'Sand', 'BINDER' => 'Binder', 'ADDITIVE' => 'Additive', 'OTHER' => 'Other',
    ];

    /** @var list<string> */
    public const UNITS = ['kg', 'tonne', 'litre', 'piece', 'bag', 'box'];

    /**
     * The rules a material is saved under, whether from the form page or the add dialog.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(?self $material = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('materials', 'code')->ignore($material?->id)->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'material_type' => ['required', 'string', 'max:40', Rule::in(array_keys(self::TYPES))],
            'base_uom' => ['required', 'string', 'max:20'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
            'is_stock_tracked' => ['required', 'boolean'],
            'requires_lot_tracking' => ['required', 'boolean'],
            'minimum_stock_qty' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'reorder_qty' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'standard_cost_per_uom' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'description' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected $fillable = [
        'code',
        'name',
        'material_type',
        'base_uom',
        'status',
        'is_stock_tracked',
        'requires_lot_tracking',
        'minimum_stock_qty',
        'reorder_qty',
        'standard_cost_per_uom',
        'description',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_stock_tracked' => 'boolean',
            'requires_lot_tracking' => 'boolean',
            'minimum_stock_qty' => 'decimal:3',
            'reorder_qty' => 'decimal:3',
            'standard_cost_per_uom' => 'decimal:4',
        ];
    }

    public function lots(): HasMany
    {
        return $this->hasMany(MaterialLot::class);
    }

    public function inspectionItems(): HasMany
    {
        return $this->hasMany(\App\Domains\Purchasing\Models\MaterialInspectionItem::class)->orderBy('sort');
    }

    public function chargeBatchItems(): HasMany
    {
        return $this->hasMany(ChargeBatchItem::class);
    }

    public function stockItemType(): string
    {
        return 'material';
    }

    public function stockItemId(): string
    {
        return $this->id;
    }

    public function stockCode(): string
    {
        return $this->code;
    }

    public function stockName(): string
    {
        return $this->name;
    }

    public function stockUom(): string
    {
        return (string) $this->base_uom;
    }

    public function isStockTracked(): bool
    {
        return (bool) $this->is_stock_tracked;
    }

    public function isLotTracked(): bool
    {
        return (bool) $this->requires_lot_tracking;
    }

    public function standardCost(): ?float
    {
        return $this->standard_cost_per_uom === null ? null : (float) $this->standard_cost_per_uom;
    }
}
