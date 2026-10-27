<?php

namespace App\Domains\Finance\Services;

use App\Domains\Core\Contracts\TaxCalculator;
use App\Domains\Core\Tax\TaxAmount;
use App\Domains\Core\Tax\TaxContext;
use App\Domains\Core\Tax\TaxLine;
use App\Domains\Dispatch\Models\Dispatch;
use App\Domains\Finance\Events\DocumentPosted;
use App\Domains\Finance\Events\DocumentVoided;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\InvoiceLine;
use App\Domains\Finance\Models\TaxRate;
use App\Models\Customer;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Raising, issuing, voiding and crediting invoices. An invoice names its source (a dispatch, a sales order or nothing) but never depends on one. */
class InvoiceService
{
    public const PREFIXES = ['INVOICE' => 'INV', 'CREDIT_NOTE' => 'CN'];

    /**
     * Saves a draft: the head and its lines, with every amount worked out here. Pass an existing draft to replace its lines.
     *
     * @param  array<string, mixed>  $head
     * @param  list<array<string, mixed>>  $lines
     */
    public function saveDraft(array $head, array $lines, ?Invoice $invoice = null): Invoice
    {
        $taxDate = $head['tax_date'] ?? null;   // a credit note is taxed as at its invoice's date
        unset($head['tax_date']);
        $lines = array_values(array_filter($lines, fn ($l) => trim((string) ($l['description'] ?? '')) !== ''));
        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one line with a description.');
        }
        if ($invoice !== null && $invoice->status !== 'DRAFT') {
            throw new InvalidArgumentException("Only a draft can be changed; this one is {$invoice->status}.");
        }

        return DB::transaction(function () use ($head, $lines, $invoice, $taxDate) {
            $customer = Customer::findOrFail($head['customer_id']);
            $head += ['kind' => 'INVOICE', 'invoice_date' => today()->toDateString(), 'currency' => FinanceRules::currency()];
            $head['payment_terms'] = $head['payment_terms'] ?? $customer->payment_terms;
            $head['due_date'] = $head['due_date'] ?? null;
            if ($invoice === null) {
                $invoice = Invoice::create($head + ['status' => 'DRAFT']);
            } else {
                $invoice->update($head);
                $invoice->lines()->delete();
            }

            $rates = TaxRate::whereIn('id', array_filter(array_column($lines, 'tax_rate_id')))->get()->keyBy('id');
            $taxLines = array_map(fn ($l) => new TaxLine(
                round(round((float) $l['quantity'], 3) * round((float) $l['unit_price'], 4), 2),
                (float) ($rates[$l['tax_rate_id'] ?? null]->rate_percent ?? 0),
                ($l['supply_type'] ?? null) === TaxLine::SERVICE ? TaxLine::SERVICE : TaxLine::GOODS,
                ($l['inventory_class'] ?? null) ?: null,
                ($l['tariff_code'] ?? null) ?: null,
            ), $lines);
            $taxes = app(TaxCalculator::class)->calculate($this->taxContext($invoice, $taxDate), $taxLines);
            [$subtotal, $tax, $withheld] = [0.0, 0.0, 0.0];
            foreach ($lines as $i => $l) {
                $taxLine = $taxLines[$i];
                InvoiceLine::create([
                    'invoice_id' => $invoice->id, 'line_number' => $i + 1, 'description' => trim($l['description']), 'quantity' => round((float) $l['quantity'], 3), 'uom' => $l['uom'] ?? null,
                    'unit_price' => round((float) $l['unit_price'], 4), 'tax_rate_id' => $l['tax_rate_id'] ?? null, 'tax_percent' => $taxes->chargedRate($i),
                    'line_net' => $taxLine->net, 'line_tax' => $taxes->charged($i), 'line_withheld' => $taxes->withheld($i),
                    'supply_type' => $taxLine->supplyType, 'tariff_code' => $taxLine->tariffCode, 'inventory_class' => $taxLine->inventoryClass,
                    'taxes' => array_map(fn (TaxAmount $t) => (array) $t, $taxes->forLine($i)),
                    'sales_order_line_id' => $l['sales_order_line_id'] ?? null, 'dispatch_item_id' => $l['dispatch_item_id'] ?? null,
                ]);
                $subtotal += $taxLine->net;
                $tax += $taxes->charged($i);
                $withheld += $taxes->withheld($i);
            }
            $invoice->update(['subtotal' => round($subtotal, 2), 'tax_total' => round($tax, 2), 'total' => round($subtotal + $tax, 2), 'tax_withheld' => round($withheld, 2)]);

            return $invoice->fresh('lines');
        });
    }

    /** Gives a draft its number and makes it count. */
    public function issue(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->status !== 'DRAFT') {
                throw new InvalidArgumentException("This is already {$invoice->status}.");
            }
            if (! $invoice->lines()->exists()) {
                throw new InvalidArgumentException('An invoice with no lines cannot be issued.');
            }
            if (! $invoice->isCreditNote() && ($blocks = $this->taxChecks($invoice)['blocks']) !== []) {
                throw new InvalidArgumentException(implode(' ', $blocks));
            }
            $due = $invoice->due_date ?? ($invoice->isCreditNote() ? null : $invoice->invoice_date->copy()->addDays(FinanceRules::daysFromTerms($invoice->payment_terms)));
            $invoice->update(['invoice_number' => $this->nextNumber($invoice->kind), 'status' => 'ISSUED', 'issued_at' => now(), 'due_date' => $due]);
            event(new DocumentPosted($invoice));

            return $invoice->fresh();
        });
    }

    /** Cancels an issued invoice or credit note that nothing has been paid or credited against. The number stays used. */
    public function void(Invoice $invoice, string $reason): Invoice
    {
        if ($invoice->status !== 'ISSUED') {
            throw new InvalidArgumentException('Only an issued document can be voided.');
        }
        if (! $invoice->isCreditNote() && ($invoice->paid() > 0 || $invoice->credited() > 0)) {
            throw new InvalidArgumentException('Payments or credit notes are recorded against this invoice; void or reverse those first, or raise a credit note instead.');
        }
        DB::transaction(function () use ($invoice, $reason) {
            $invoice->update(['status' => 'VOID', 'voided_at' => now(), 'void_reason' => $reason]);
            event(new DocumentVoided($invoice));
        });

        return $invoice->fresh();
    }

    /**
     * A credit note that takes these lines off an issued invoice, issued straight away. The lines may be for less than the whole invoice
     * but not more than what has not yet been credited.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function creditNote(Invoice $original, array $lines, string $reason): Invoice
    {
        if ($original->status !== 'ISSUED' || $original->isCreditNote()) {
            throw new InvalidArgumentException('A credit note can only be raised against an issued invoice.');
        }
        $note = $this->saveDraft([
            'kind' => 'CREDIT_NOTE', 'customer_id' => $original->customer_id, 'original_invoice_id' => $original->id, 'currency' => $original->currency,
            'payment_terms' => $original->payment_terms, 'customer_reference' => $original->customer_reference, 'notes' => $reason,
            'source_type' => 'invoice', 'source_id' => $original->id, 'place_of_supply' => $original->place_of_supply, 'tax_date' => $original->invoice_date->toDateString(),
        ], $lines);
        $room = round($original->total - $original->credited(), 2);
        if ($note->total > $room) {
            $note->delete();

            throw new InvalidArgumentException("The credit note is for {$note->total} but only {$room} of the invoice is left to credit.");
        }

        return $this->issue($note);
    }

    /**
     * The lines a dispatch would be invoiced with: what was sent at the order line's price, leaving out items already on a live invoice.
     *
     * @return list<array<string, mixed>>
     */
    public function linesFromDispatch(Dispatch $dispatch): array
    {
        $done = InvoiceLine::whereIn('dispatch_item_id', $dispatch->items->pluck('id'))
            ->whereHas('invoice', fn ($q) => $q->where('kind', 'INVOICE')->where('status', '!=', 'VOID'))->pluck('dispatch_item_id')->all();
        $default = $this->defaultTaxRateId();

        return $dispatch->items->reject(fn ($i) => in_array($i->id, $done, true))->map(function ($i) use ($default) {
            $product = $i->salesOrderLine?->product ?? $i->packingItem?->lot?->product;
            // A line sold by kg is invoiced on the net kg weighed at packing.
            $byKg = (bool) $i->salesOrderLine?->isSoldByKg();

            return [
                'description' => trim(($product?->code ? $product->code.' ' : '').($product?->name ?? 'Castings')), 'quantity' => $byKg ? (float) $i->net_weight_kg : (float) $i->quantity_dispatched,
                'uom' => $byKg ? 'kg' : ($i->uom ?: $i->salesOrderLine?->uom), 'unit_price' => (float) ($i->salesOrderLine?->unit_price ?? 0), 'tax_rate_id' => $default,
                'sales_order_line_id' => $i->sales_order_line_id, 'dispatch_item_id' => $i->id, ...$this->taxDetails($product),
            ];
        })->values()->all();
    }

    /**
     * The lines of a sales order still to invoice: ordered quantity less what live invoices already carry.
     *
     * @return list<array<string, mixed>>
     */
    public function linesFromOrder(SalesOrder $order): array
    {
        $default = $this->defaultTaxRateId();

        return $order->lines->map(function ($l) use ($default) {
            $left = round((float) $l->ordered_qty - $this->invoicedQuantity($l->id), 3);

            return $left > 0 ? [
                'description' => trim(($l->product?->code ? $l->product->code.' ' : '').($l->product?->name ?? 'Castings')), 'quantity' => $left, 'uom' => $l->uom,
                'unit_price' => (float) $l->unit_price, 'tax_rate_id' => $default, 'sales_order_line_id' => $l->id, 'dispatch_item_id' => null,
                ...$this->taxDetails($l->product),
            ] : null;
        })->filter()->values()->all();
    }

    /** How much of a sales order line live invoices carry. */
    public function invoicedQuantity(string $salesOrderLineId): float
    {
        return round((float) InvoiceLine::where('sales_order_line_id', $salesOrderLineId)
            ->whereHas('invoice', fn ($q) => $q->where('kind', 'INVOICE')->where('status', '!=', 'VOID'))->sum('quantity'), 3);
    }

    /**
     * What tax needs to know of an invoice: sale, date (or the date it is taxed as at), customer and place of supply.
     */
    public function taxContext(Invoice $invoice, ?string $taxDate = null): TaxContext
    {
        return new TaxContext(TaxContext::SALE, $taxDate ? Carbon::parse($taxDate) : $invoice->invoice_date, $invoice->customer?->party_id, $invoice->place_of_supply);
    }

    /**
     * What stops a draft being issued, or should be looked at first, for tax.
     *
     * @return array{blocks: list<string>, warnings: list<string>}
     */
    public function taxChecks(Invoice $invoice): array
    {
        $lines = $invoice->lines()->orderBy('line_number')->get()
            ->map(fn (InvoiceLine $l) => new TaxLine((float) $l->line_net, (float) $l->tax_percent, $l->supply_type, $l->inventory_class, $l->tariff_code))->all();

        return app(TaxCalculator::class)->issueChecks($this->taxContext($invoice), $lines);
    }

    /** @return array{supply_type: string, tariff_code: ?string, inventory_class: ?string} */
    private function taxDetails(?\App\Models\Product $product): array
    {
        return ['supply_type' => $product?->supply_type ?: TaxLine::GOODS, 'tariff_code' => $product?->tariff_code, 'inventory_class' => $product?->inventory_class];
    }

    public function defaultTaxRateId(): ?string
    {
        return TaxRate::where('is_active', true)->where('is_default', true)->value('id');
    }

    /** The next number for a document kind or prefix, restarting each year: INV-2026-0001. Call inside a transaction. */
    public function nextNumber(string $kindOrPrefix, string $table = 'invoices', string $column = 'invoice_number'): string
    {
        $prefix = self::PREFIXES[$kindOrPrefix] ?? $kindOrPrefix;
        $like = $prefix.'-'.now()->format('Y').'-';
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$table.'-number-'.$like]);
        $n = (DB::table($table)->where($column, 'like', $like.'%')->pluck($column)->map(fn ($x) => (int) substr($x, strlen($like)))->max() ?? 0) + 1;

        return $like.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
