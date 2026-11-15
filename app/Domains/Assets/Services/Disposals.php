<?php

namespace App\Domains\Assets\Services;

use App\Domains\Assets\Models\AssetDisposal;
use App\Domains\Assets\Models\AssetEvent;
use App\Domains\Assets\Models\DepreciationRun;
use App\Domains\Assets\Models\FixedAsset;
use App\Domains\Core\ModuleRegistry;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Finance\Services\ReceiptService;
use App\Domains\Ledger\Models\LedgerAccount;
use App\Domains\Ledger\Services\Ledger;
use App\Domains\Ledger\Services\LedgerSettings;
use App\Models\Customer;
use App\Models\Equipment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Selling, scrapping or writing off an asset (design 28h). Depreciation is charged to the day it goes, its cost and all its
 * accumulated depreciation leave the books, and the difference from the price is a gain or loss on disposal (42-01-05). A sale
 * or scrap sale raises an invoice to the buyer whose line credits that account, so the gain is price less book value; the
 * invoice is paid like any other, or the money taken now is recorded against it. The equipment record, if any, is set disposed.
 */
class Disposals
{
    /** The gain or loss on disposal account of the starting chart. */
    public const GAIN_ACCOUNT = '42-01-05';

    public function __construct(private Ledger $ledger, private Depreciation $depreciation) {}

    public static function gainAccount(): LedgerAccount
    {
        return LedgerAccount::where('code', self::GAIN_ACCOUNT)->first()
            ?? throw new InvalidArgumentException('The chart has no gain or loss on disposal account ('.self::GAIN_ACCOUNT.').');
    }

    /** Why the asset cannot be disposed of on this day, or null when it can. */
    public function block(FixedAsset $asset, Carbon $date): ?string
    {
        if ($asset->status !== 'IN_USE') {
            return $asset->status === 'IN_PROGRESS' ? "{$asset->code} is not in use yet; nothing to dispose of." : "{$asset->code} is already ".strtolower(FixedAsset::STATUSES[$asset->status]).'.';
        }
        if ($date->gt(today())) {
            return 'The date cannot be in the future.';
        }
        if ($asset->in_use_on && $date->lt($asset->in_use_on)) {
            return "{$asset->code} went into use on {$asset->in_use_on->displayDate()}; the date cannot be before that.";
        }
        if (($closed = LedgerSettings::closedTo()) && $date->toDateString() <= $closed) {
            return 'The books are closed to '.Carbon::parse($closed)->displayDate().'; pick a later date or reopen the month in month close.';
        }
        $month = $date->copy()->startOfMonth();
        $later = DepreciationRun::where('status', DepreciationRun::POSTED)->where('period', '>=', $month->toDateString())
            ->whereHas('lines', fn ($q) => $q->where('fixed_asset_id', $asset->id))->orderByDesc('period')->first();
        if ($later) {
            return "The {$later->label()} run already charged {$asset->code} for the whole month; reverse it first, or date the disposal after it.";
        }
        if ($asset->method !== 'NONE' && ! $this->coveredTo($asset, $month->copy()->subDay())) {
            return 'Post the '.$month->copy()->subMonthNoOverflow()->format('F Y').' depreciation run first; the disposal charges only '.$month->format('F').' to the day.';
        }

        return null;
    }

    /**
     * What the disposal would post on that day at that price.
     *
     * @return array{charge: float, before: float, cleared: float, book: float, gain: float}
     */
    public function figures(FixedAsset $asset, Carbon $date, float $price): array
    {
        $line = $this->depreciation->forMonth($date->copy()->startOfMonth(), [$asset->id], $date)->first();
        $charge = round((float) ($line['charge'] ?? 0), 2);
        $before = $asset->depreciatedTo();
        $book = round($asset->cost - $before - $charge, 2);

        return ['charge' => $charge, 'before' => $before, 'cleared' => round($before + $charge, 2), 'book' => $book, 'gain' => round($price - $book, 2)];
    }

    /**
     * Disposes of the asset. For a sale or scrap: the buyer (a customer, or a new one by name and tax number), the price without tax
     * (for scrap, weight × rate per kg), the tax rate, and whether the money is in already (method and reference).
     *
     * @param  array{kind: string, date: string, customer_id?: ?string, buyer_name?: ?string, buyer_ntn?: ?string, price?: float|string|null, weight_kg?: float|string|null, rate_per_kg?: float|string|null, tax_rate_id?: ?string, received?: bool, method?: ?string, reference?: ?string, reason?: ?string}  $data
     */
    public function dispose(FixedAsset $asset, array $data): AssetDisposal
    {
        $kind = $data['kind'];
        if (! isset(AssetDisposal::KINDS[$kind])) {
            throw new InvalidArgumentException('Pick sell, sell as scrap or write off.');
        }
        $date = Carbon::parse($data['date'])->startOfDay();
        if ($block = $this->block($asset, $date)) {
            throw new InvalidArgumentException($block);
        }
        $modules = app(ModuleRegistry::class);
        if (! $modules->isEnabled('ledger')) {
            throw new InvalidArgumentException('Switch on the General ledger under Settings > Modules to post fixed assets.');
        }
        $sells = $kind !== 'WRITE_OFF';
        if ($sells && ! $modules->isEnabled('finance')) {
            throw new InvalidArgumentException('Switch on Finance under Settings > Modules to invoice the buyer, or write the asset off.');
        }
        $weight = $kind === 'SCRAP' ? round((float) ($data['weight_kg'] ?? 0), 2) : null;
        $price = match ($kind) {
            'SELL' => round((float) ($data['price'] ?? 0), 2),
            'SCRAP' => InvoiceService::lineNet(['quantity' => $weight, 'unit_price' => (float) ($data['rate_per_kg'] ?? 0)]),
            default => 0.0,
        };
        if ($sells && $price <= 0) {
            throw new InvalidArgumentException($kind === 'SCRAP' ? 'Enter the weight and the rate per kg.' : 'Enter the price.');
        }
        if ($sells && empty($data['customer_id']) && trim((string) ($data['buyer_name'] ?? '')) === '') {
            throw new InvalidArgumentException('Pick the buyer, or enter a new buyer\'s name.');
        }
        $class = $asset->assetClass;

        return DB::transaction(function () use ($asset, $data, $kind, $date, $sells, $weight, $price, $class) {
            $asset = FixedAsset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if ($asset->status !== 'IN_USE') {
                throw new InvalidArgumentException("{$asset->code} has been disposed of already.");
            }
            $f = $this->figures($asset, $date, $price);
            [$invoice, $receipt, $customer] = [null, null, null];
            if ($sells) {
                $customer = ! empty($data['customer_id']) ? Customer::findOrFail($data['customer_id'])
                    : Customer::create(['name' => trim((string) $data['buyer_name']), 'tax_number' => trim((string) ($data['buyer_ntn'] ?? '')) ?: null]);
                $invoices = app(InvoiceService::class);
                $line = $kind === 'SCRAP'
                    ? ['description' => "{$asset->code} {$asset->name}, as scrap", 'quantity' => $weight, 'uom' => 'kg', 'unit_price' => (float) $data['rate_per_kg']]
                    : ['description' => "{$asset->code} {$asset->name}", 'quantity' => 1, 'uom' => 'nos', 'unit_price' => $price];
                $invoice = $invoices->saveDraft(['customer_id' => $customer->id, 'invoice_date' => $date->toDateString(), 'source_type' => 'fixed_asset', 'source_id' => $asset->id,
                    'notes' => 'Sale of fixed asset '.$asset->code.'.'], [$line + ['tax_rate_id' => ($data['tax_rate_id'] ?? null) ?: null, 'supply_type' => 'GOODS', 'account_id' => self::gainAccount()->id]]);
                $invoice = $invoices->issue($invoice);
                if (! empty($data['received'])) {
                    $receipt = app(ReceiptService::class)->record(['customer_id' => $customer->id, 'received_on' => $date->toDateString(), 'amount' => $invoice->total,
                        'method' => ($data['method'] ?? null) ?: 'Cash', 'reference' => ($data['reference'] ?? null) ?: null, 'notes' => "Sale of {$asset->code}"], [$invoice->id => $invoice->total]);
                }
            }
            if (($f['cleared'] > 0 && ! $class->depreciation_account_id) || ($f['charge'] > 0 && ! $class->expense_account_id)) {
                throw new InvalidArgumentException("Asset class {$class->name} needs an expense and an accumulated depreciation account.");
            }
            $label = AssetDisposal::KINDS[$kind][0];
            $status = AssetDisposal::KINDS[$kind][1];
            $disposal = AssetDisposal::create([
                'fixed_asset_id' => $asset->id, 'kind' => $kind, 'disposed_on' => $date->toDateString(), 'customer_id' => $customer?->id, 'invoice_id' => $invoice?->id,
                'receipt_id' => $receipt?->id, 'price' => $price, 'weight_kg' => $weight, 'charge' => $f['charge'], 'cost_removed' => $asset->cost,
                'depreciation_cleared' => $f['cleared'], 'book_value' => $f['book'], 'gain' => $f['gain'], 'reason' => ($data['reason'] ?? null) ?: null, 'user_id' => auth()->id(),
            ]);
            // its own source: the asset's put-in-use journal already holds the fixed_asset one
            $journal = $this->ledger->post($date->toDateString(), [
                ['debit' => $f['charge'], 'account_id' => $class->expense_account_id, 'description' => "{$asset->code} depreciation 1–{$date->format('j M')}"],
                ['debit' => $f['before'], 'account_id' => $class->depreciation_account_id, 'description' => "{$asset->code} depreciation cleared"],
                ['debit' => max(0.0, $f['book']), 'account_id' => self::gainAccount()->id, 'description' => "{$asset->code} book value"],
                ['credit' => max(0.0, -$f['book']), 'account_id' => self::gainAccount()->id, 'description' => "{$asset->code} book value"],
                ['credit' => $asset->cost, 'account_id' => $class->cost_account_id, 'description' => $asset->code],
            ], ['type' => 'fixed_asset_disposal', 'id' => $disposal->id], "{$asset->code} {$asset->name} · ".strtolower($label).($invoice ? " · {$invoice->invoice_number}" : ''));
            $disposal->update(['journal_id' => $journal->id]);
            $asset->update(['status' => $status, 'disposed_on' => $date->toDateString()]);
            if ($asset->equipment_id) {
                Equipment::whereKey($asset->equipment_id)->update(['status' => 'DISPOSED']);
            }
            $result = $f['gain'] >= 0 ? 'gain '.number_format($f['gain']) : 'loss '.number_format(-$f['gain']);
            AssetEvent::create(['fixed_asset_id' => $asset->id, 'kind' => $status, 'happened_on' => $date->toDateString(), 'journal_id' => $journal->id, 'user_id' => auth()->id(),
                'note' => trim(($customer ? "To {$customer->name}" : ($data['reason'] ?? 'Written off')).($invoice ? " · {$invoice->invoice_number}" : '')." · book value ".number_format($f['book'])." · {$result}")]);

            return $disposal;
        });
    }

    /** Whether the asset's depreciation already reaches the day given: a run for that month is posted, or it came in depreciated to it. */
    private function coveredTo(FixedAsset $asset, Carbon $day): bool
    {
        if ($asset->in_use_on && $asset->in_use_on->gt($day)) {
            return true;
        }
        if ($asset->opening_to && $asset->opening_to->gte($day)) {
            return true;
        }

        return DepreciationRun::where('status', DepreciationRun::POSTED)->where('period', $day->copy()->startOfMonth()->toDateString())->exists();
    }
}
