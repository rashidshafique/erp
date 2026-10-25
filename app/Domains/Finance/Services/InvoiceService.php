<?php

namespace App\Domains\Finance\Services;

use App\Domains\Core\Contracts\TaxCalculator;
use App\Domains\Core\Tax\TaxContext;
use App\Domains\Core\Tax\TaxLine;
use App\Domains\Dispatch\Models\Dispatch;
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
        $lines = array_values(array_filter($lines, fn ($l) => trim((string) ($l['description'] ?? '')) !== ''));
        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one line with a description.');
        }
        if ($invoice !== null && $invoice->status !== 'DRAFT') {
            throw new InvalidArgumentException("Only a draft can be changed; this one is {$invoice->status}.");
        }

        return DB::transaction(function () use ($head, $lines, $invoice) {
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
            ), $lines);
            $taxes = app(TaxCalculator::class)->calculate(new TaxContext(TaxContext::SALE, Carbon::parse($head['invoice_date']), $customer->party_id), $taxLines);
            [$subtotal, $tax] = [0.0, 0.0];
            foreach ($lines as $i => $l) {
                $qty = round((float) $l['quantity'], 3);
                $price = round((float) $l['unit_price'], 4);
                $percent = $taxes->chargedRate($i);
                $net = $taxLines[$i]->net;
                $lineTax = $taxes->charged($i);
                InvoiceLine::create([
                    'invoice_id' => $invoice->id, 'line_number' => $i + 1, 'description' => trim($l['description']), 'quantity' => $qty, 'uom' => $l['uom'] ?? null,
                    'unit_price' => $price, 'tax_rate_id' => $l['tax_rate_id'] ?? null, 'tax_percent' => $percent, 'line_net' => $net, 'line_tax' => $lineTax,
                    'sales_order_line_id' => $l['sales_order_line_id'] ?? null, 'dispatch_item_id' => $l['dispatch_item_id'] ?? null,
                ]);
                $subtotal += $net;
                $tax += $lineTax;
            }
            $invoice->update(['subtotal' => round($subtotal, 2), 'tax_total' => round($tax, 2), 'total' => round($subtotal + $tax, 2)]);

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
            $due = $invoice->due_date ?? ($invoice->isCreditNote() ? null : $invoice->invoice_date->copy()->addDays(FinanceRules::daysFromTerms($invoice->payment_terms)));
            $invoice->update(['invoice_number' => $this->nextNumber($invoice->kind), 'status' => 'ISSUED', 'issued_at' => now(), 'due_date' => $due]);

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
        $invoice->update(['status' => 'VOID', 'voided_at' => now(), 'void_reason' => $reason]);

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
            'source_type' => 'invoice', 'source_id' => $original->id,
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

            return [
                'description' => trim(($product?->code ? $product->code.' ' : '').($product?->name ?? 'Castings')), 'quantity' => (float) $i->quantity_dispatched,
                'uom' => $i->uom ?: $i->salesOrderLine?->uom, 'unit_price' => (float) ($i->salesOrderLine?->unit_price ?? 0), 'tax_rate_id' => $default,
                'sales_order_line_id' => $i->sales_order_line_id, 'dispatch_item_id' => $i->id,
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
            ] : null;
        })->filter()->values()->all();
    }

    /** How much of a sales order line live invoices carry. */
    public function invoicedQuantity(string $salesOrderLineId): float
    {
        return round((float) InvoiceLine::where('sales_order_line_id', $salesOrderLineId)
            ->whereHas('invoice', fn ($q) => $q->where('kind', 'INVOICE')->where('status', '!=', 'VOID'))->sum('quantity'), 3);
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
