<?php

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\PriceList;
use App\Domains\Sales\Models\PriceListItem;
use App\Models\AppSetting;
use App\Models\Customer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Finds a product's list price for a customer: from the customer's own list when it prices the product, else from the default
 * list, each only while it applies on the date. The quantity break is the highest one reached. A price per kg is turned into a
 * price per piece by the casting's net weight, and the other way round.
 */
class PriceLists
{
    public const CHECK = 'sales.price_check';

    /** What happens when an order line is priced under its list price. */
    public const CHECK_OPTIONS = ['OFF' => 'Not checked', 'WARN' => 'Contract review warns', 'BLOCK' => 'Contract review blocks'];

    public const DISCOUNTS = 'sales.line_discounts';

    public const DISCOUNT_LIMIT = 'sales.discount_limit';

    /** Whether order and invoice lines show a discount box. Lines that already carry a discount keep it either way. */
    public static function discountsOn(): bool
    {
        return (string) AppSetting::getValue(self::DISCOUNTS, '0') === '1';
    }

    /** A discount above this percent is flagged at contract review; 0 means any discount is allowed. */
    public static function discountLimit(): float
    {
        return max(0.0, (float) AppSetting::getValue(self::DISCOUNT_LIMIT, '0'));
    }

    /** @return 'OFF'|'WARN'|'BLOCK' */
    public static function check(): string
    {
        $v = (string) AppSetting::getValue(self::CHECK, 'WARN');

        return isset(self::CHECK_OPTIONS[$v]) ? $v : 'WARN';
    }

    /**
     * The list price of a quantity of a product in the line's unit, or null when no list prices it.
     *
     * @param  string  $uom  'piece' or 'kg', the unit the line is sold in
     * @param  float  $kgEach  the net kg of one casting, to convert between per piece and per kg
     * @return array{price: float, list: PriceList, item: PriceListItem}|null
     */
    public function priceFor(?string $customerId, string $productId, float $quantity, string $uom, float $kgEach, CarbonInterface $date): ?array
    {
        $customer = $customerId ? Customer::find($customerId) : null;
        $lists = collect([$customer?->price_list_id ? PriceList::find($customer->price_list_id) : null, PriceList::where('is_default', true)->first()])
            ->filter(fn ($l) => $l && $l->appliesOn($date))->unique('id');
        foreach ($lists as $list) {
            if ($found = $this->fromList($list, $productId, $quantity, $uom, $kgEach)) {
                return $found;
            }
        }

        return null;
    }

    /** @return array{price: float, list: PriceList, item: PriceListItem}|null */
    private function fromList(PriceList $list, string $productId, float $quantity, string $uom, float $kgEach): ?array
    {
        $pieces = $uom === 'kg' ? ($kgEach > 0 ? $quantity / $kgEach : 0) : $quantity;
        $kg = $uom === 'kg' ? $quantity : $quantity * $kgEach;
        $reached = $list->items()->where('product_id', $productId)->get()
            ->filter(fn (PriceListItem $i) => $i->min_quantity <= ($i->basis === 'KG' ? $kg : $pieces) + 0.0005)
            ->filter(fn (PriceListItem $i) => $this->convert($i, $uom, $kgEach) !== null);
        // The line's own basis first, then the highest break reached.
        $item = $reached->sortByDesc(fn (PriceListItem $i) => [($i->basis === 'KG') === ($uom === 'kg') ? 1 : 0, $i->min_quantity])->first();

        return $item ? ['price' => $this->convert($item, $uom, $kgEach), 'list' => $list, 'item' => $item] : null;
    }

    private function convert(PriceListItem $item, string $uom, float $kgEach): ?float
    {
        return match (true) {
            ($item->basis === 'KG') === ($uom === 'kg') => round($item->price, 4),
            $kgEach <= 0 => null,
            $item->basis === 'KG' => round($item->price * $kgEach, 4),
            default => round($item->price / $kgEach, 4),
        };
    }

    /**
     * Replaces a list's prices.
     *
     * @param  list<array<string, mixed>>  $rows  product_id, basis, min_quantity, price
     */
    public function saveItems(PriceList $list, array $rows): void
    {
        $rows = array_values(array_filter($rows, fn ($r) => filled($r['product_id'] ?? null) && ($r['price'] ?? '') !== '' && $r['price'] !== null));
        $keys = array_map(fn ($r) => $r['product_id'].'|'.($r['basis'] ?? 'PIECE').'|'.round((float) ($r['min_quantity'] ?? 0), 3), $rows);
        if (count($keys) !== count(array_unique($keys))) {
            throw new InvalidArgumentException('A product has the same quantity break twice.');
        }
        DB::transaction(function () use ($list, $rows) {
            $list->items()->delete();
            foreach ($rows as $r) {
                $list->items()->create(['product_id' => $r['product_id'], 'basis' => ($r['basis'] ?? 'PIECE') === 'KG' ? 'KG' : 'PIECE',
                    'min_quantity' => round((float) ($r['min_quantity'] ?? 0), 3), 'price' => round((float) $r['price'], 4)]);
            }
        });
    }

    /** A copy of the list under a new code, every price changed by a percent (rounded to 2 places), not the default and not yet active. */
    public function copy(PriceList $list, string $code, string $name, float $percent, ?string $validFrom): PriceList
    {
        return DB::transaction(function () use ($list, $code, $name, $percent, $validFrom) {
            $new = PriceList::create(['code' => $code, 'name' => $name, 'currency' => $list->currency, 'valid_from' => $validFrom, 'is_active' => false, 'is_default' => false,
                'notes' => "Copied from {$list->code}".($percent != 0.0 ? sprintf(', prices %+.2f%%', $percent) : '').'.']);
            foreach ($list->items as $i) {
                $new->items()->create(['product_id' => $i->product_id, 'basis' => $i->basis, 'min_quantity' => $i->min_quantity, 'price' => round($i->price * (1 + $percent / 100), 2)]);
            }

            return $new;
        });
    }

    /** Makes this the only default list. */
    public function makeDefault(PriceList $list): void
    {
        DB::transaction(function () use ($list) {
            PriceList::whereKeyNot($list->id)->update(['is_default' => false]);
            $list->update(['is_default' => true]);
        });
    }
}
