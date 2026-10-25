<?php

namespace App\Models;

use App\Models\Concerns\GeneratesCodes;
use App\Models\Concerns\HasTradeDetails;
use App\Services\Stock\InventoryClass;
use App\Services\Stock\Stockable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model implements Stockable
{
    use GeneratesCodes, HasTradeDetails, HasUuids, SoftDeletes;

    public string $codeKind = 'material';

    /** @var array<string, string> */
    public const TYPES = [
        'METAL' => 'Metal', 'RETURN' => 'Foundry returns', 'ALLOY' => 'Alloy', 'FLUX' => 'Flux', 'FUEL' => 'Fuel',
        'SAND' => 'Sand', 'BINDER' => 'Binder', 'ADDITIVE' => 'Additive', 'OTHER' => 'Other',
    ];

    /** Material types that are not metal: their weight is left out of the metal the charge is worked out over. */
    public const NON_METAL_TYPES = ['FUEL', 'FLUX', 'SAND', 'BINDER'];

    /** @var list<string> */
    public const UNITS = ['kg', 'tonne', 'litre', 'piece', 'bag', 'box'];

    /**
     * The rules a material is saved under, whether from the form page or the add dialog. A blank code is generated when the
     * material is created, and kept as it is when the material is edited; a typed one is upper-cased by the caller first.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(?self $material = null): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50', Rule::unique('materials', 'code')->ignore($material?->id)->whereNull('deleted_at')],
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
            ...self::tradeRules(),
        ];
    }

    /**
     * Rules for the optional chemistry panel: per element key (as in {@see IronGrade::ELEMENTS}) a typical, minimum and maximum
     * percentage and a recovery. Unknown element keys are ignored when saved.
     *
     * @return array<string, list<mixed>>
     */
    public static function chemistryRules(): array
    {
        return [
            'chemistry' => ['nullable', 'array'],
            'chemistry.*.nominal_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'chemistry.*.min_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'chemistry.*.max_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'chemistry.*.recovery_pct' => ['nullable', 'numeric', 'min:0', 'max:300'],
        ];
    }

    /**
     * What is wrong between the figures of one element: a spread that does not hold the typical value, or a spread with no typical value.
     *
     * @param  array<string, array<string, mixed>>  $chemistry
     * @return array<string, string> Field name => message.
     */
    public static function chemistryProblems(array $chemistry): array
    {
        $problems = [];
        foreach (IronGrade::ELEMENTS as $key => $label) {
            $row = $chemistry[$key] ?? [];
            [$nominal, $min, $max] = array_map(fn ($f) => ($row[$f] ?? '') === '' ? null : (float) $row[$f], ['nominal_pct', 'min_pct', 'max_pct']);
            if ($nominal === null && ($min !== null || $max !== null || ($row['recovery_pct'] ?? '') !== '')) {
                $problems["chemistry.{$key}.nominal_pct"] = "{$label}: give the typical percentage, or clear the other figures.";
            } elseif ($nominal !== null && (($min !== null && $min > $nominal) || ($max !== null && $max < $nominal) || ($min !== null && $max !== null && $min > $max))) {
                $problems["chemistry.{$key}.nominal_pct"] = "{$label}: the minimum must not be above the typical value, nor the maximum below it.";
            }
        }

        return $problems;
    }

    /**
     * Replaces the chemistry with the figures given. An element with no typical value is removed; a blank recovery means 100.
     *
     * @param  array<string, array<string, mixed>>  $chemistry
     */
    public function saveChemistry(array $chemistry): void
    {
        foreach (IronGrade::ELEMENTS as $key => $label) {
            $row = $chemistry[$key] ?? [];
            if (($row['nominal_pct'] ?? '') === '') {
                $this->elements()->where('element', $key)->delete();

                continue;
            }
            $this->elements()->updateOrCreate(['element' => $key], [
                'nominal_pct' => $row['nominal_pct'],
                'min_pct' => ($row['min_pct'] ?? '') === '' ? null : $row['min_pct'],
                'max_pct' => ($row['max_pct'] ?? '') === '' ? null : $row['max_pct'],
                'recovery_pct' => ($row['recovery_pct'] ?? '') === '' ? 100 : $row['recovery_pct'],
            ]);
        }
    }

    public function elements(): HasMany
    {
        return $this->hasMany(MaterialElement::class);
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

    public function defaultInventoryClass(): InventoryClass
    {
        return InventoryClass::forMaterialType($this->material_type);
    }

    public function standardCost(): ?float
    {
        return $this->standard_cost_per_uom === null ? null : (float) $this->standard_cost_per_uom;
    }
}
