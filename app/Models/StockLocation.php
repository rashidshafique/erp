<?php

namespace App\Models;

use Database\Factories\StockLocationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\Stock\StockLocationTypes;
use Illuminate\Support\Facades\DB;

/** A place stock is kept: a store, yard, bin, quarantine area or line-side point. Locations nest under a parent. */
class StockLocation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['code', 'name', 'location_type', 'parent_location_id', 'is_active', 'description'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function newFactory(): Factory
    {
        return StockLocationFactory::new();
    }

    public function typeLabel(): string
    {
        return app(StockLocationTypes::class)->label($this->location_type);
    }

    /** Whether this place belongs to someone else (a subcontractor's works), so its stock is ours but not in our stores. */
    public function isExternal(): bool
    {
        return app(StockLocationTypes::class)->isExternal($this->location_type);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_location_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_location_id');
    }

    /** The ids of every location below this one, at any depth. @return list<string> */
    public function descendantIds(): array
    {
        $ids = [];
        $level = [$this->id];
        while ($level !== []) {
            $level = self::whereIn('parent_location_id', $level)->pluck('id')->all();
            $ids = [...$ids, ...$level];
        }

        return $ids;
    }

    /** Whether any stock movement or reservation names this location, so it can only be switched off. */
    public function isInUse(): bool
    {
        return $this->children()->exists()
            || DB::table('stock_movements')->where('from_location_id', $this->id)->orWhere('to_location_id', $this->id)->exists()
            || DB::table('inventory_reservations')->where('stock_location_id', $this->id)->exists();
    }
}
