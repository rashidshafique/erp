<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierBillLine;
use App\Domains\Purchasing\Models\PurchaseOrder;
use App\Domains\Purchasing\Models\PurchaseOrderLine;
use App\Domains\Purchasing\Models\Supplier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Entering, posting, voiding and crediting supplier bills. A bill may name a purchase order and its lines, but never depends on one. */
class BillService
{
    public const PREFIXES = ['BILL' => 'BILL', 'CREDIT_NOTE' => 'SCN'];

    public function __construct(private InvoiceService $numbers) {}

    /**
     * Saves a draft: the head and its lines, with every amount worked out here. Pass an existing draft to replace its lines.
     *
     * @param  array<string, mixed>  $head
     * @param  list<array<string, mixed>>  $lines
     */
    public function saveDraft(array $head, array $lines, ?SupplierBill $bill = null): SupplierBill
    {
        $lines = array_values(array_filter($lines, fn ($l) => trim((string) ($l['description'] ?? '')) !== ''));
        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one line with a description.');
        }
        if ($bill !== null && $bill->status !== 'DRAFT') {
            throw new InvalidArgumentException("Only a draft can be changed; this one is {$bill->status}.");
        }

        return DB::transaction(function () use ($head, $lines, $bill) {
            $supplier = Supplier::findOrFail($head['supplier_id']);
            $head += ['kind' => 'BILL', 'bill_date' => today()->toDateString(), 'currency' => FinanceRules::currency()];
            $head['due_date'] = $head['due_date'] ?? null;
            if ($bill === null) {
                $bill = SupplierBill::create($head + ['status' => 'DRAFT']);
            } else {
                $bill->update($head);
                $bill->lines()->delete();
            }
            [$subtotal, $tax] = [0.0, 0.0];
            foreach ($lines as $i => $l) {
                $qty = round((float) ($l['quantity'] ?? 0), 3);
                if ($qty <= 0) {
                    throw new InvalidArgumentException('Line '.($i + 1).' needs a quantity.');
                }
                $price = round((float) ($l['unit_price'] ?? 0), 4);
                $percent = round((float) ($l['tax_percent'] ?? 0), 3);
                $net = round($qty * $price, 2);
                $lineTax = round($net * $percent / 100, 2);
                SupplierBillLine::create([
                    'supplier_bill_id' => $bill->id, 'line_number' => $i + 1, 'description' => trim($l['description']), 'quantity' => $qty, 'uom' => $l['uom'] ?? null,
                    'unit_price' => $price, 'tax_percent' => $percent, 'line_net' => $net, 'line_tax' => $lineTax, 'purchase_order_line_id' => ($l['purchase_order_line_id'] ?? null) ?: null,
                ]);
                $subtotal += $net;
                $tax += $lineTax;
            }
            $bill->update(['subtotal' => round($subtotal, 2), 'tax_total' => round($tax, 2), 'total' => round($subtotal + $tax, 2)]);

            return $bill->fresh('lines');
        });
    }

    /**
     * What does not match the purchase order on a bill's lines: more billed than received, or a price over the order price.
     *
     * @return list<string>
     */
    public function matchIssues(SupplierBill $bill): array
    {
        if ($bill->isCreditNote() || FinanceRules::billMatch() === 'OFF') {
            return [];
        }
        $tolerance = 1 + FinanceRules::billTolerance() / 100;
        $issues = [];
        foreach ($bill->lines()->whereNotNull('purchase_order_line_id')->get() as $line) {
            $orderLine = PurchaseOrderLine::find($line->purchase_order_line_id);
            if (! $orderLine) {
                continue;
            }
            $billedBefore = (float) SupplierBillLine::where('purchase_order_line_id', $orderLine->id)->where('id', '!=', $line->id)
                ->whereHas('bill', fn ($q) => $q->where('kind', 'BILL')->where('status', 'POSTED'))->sum('quantity');
            if ($billedBefore + $line->quantity > $orderLine->received_qty * $tolerance + 0.0005) {
                $issues[] = "Line {$line->line_number}: billing ".round($billedBefore + $line->quantity, 3)." but only {$orderLine->received_qty} received".($billedBefore > 0 ? " ({$billedBefore} already billed)" : '').'.';
            }
            if ($line->unit_price > $orderLine->unit_price * $tolerance + 0.00005) {
                $issues[] = "Line {$line->line_number}: price {$line->unit_price} is over the order price {$orderLine->unit_price}.";
            }
        }

        return $issues;
    }

    /** Gives a draft its number and makes it count as owed. The supplier's own bill number is needed and cannot be posted twice. */
    public function post(SupplierBill $bill): SupplierBill
    {
        return DB::transaction(function () use ($bill) {
            $bill = SupplierBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if ($bill->status !== 'DRAFT') {
                throw new InvalidArgumentException("This is already {$bill->status}.");
            }
            if (! $bill->lines()->exists()) {
                throw new InvalidArgumentException('A bill with no lines cannot be posted.');
            }
            if (blank($bill->supplier_bill_number)) {
                throw new InvalidArgumentException("Enter the supplier's own number for this document before posting.");
            }
            $clash = SupplierBill::where('supplier_id', $bill->supplier_id)->where('status', 'POSTED')->where('id', '!=', $bill->id)
                ->whereRaw('lower(supplier_bill_number) = ?', [mb_strtolower($bill->supplier_bill_number)])->exists();
            if ($clash) {
                throw new InvalidArgumentException("A document numbered {$bill->supplier_bill_number} from this supplier is already posted.");
            }
            if (FinanceRules::billMatch() === 'BLOCK' && ($issues = $this->matchIssues($bill)) !== []) {
                throw new InvalidArgumentException('The bill does not match the order: '.implode(' ', $issues));
            }
            $due = $bill->due_date ?? ($bill->isCreditNote() ? null : $bill->bill_date->copy()->addDays(FinanceRules::daysFromTerms($bill->payment_terms)));
            $bill->update(['bill_number' => $this->numbers->nextNumber(self::PREFIXES[$bill->kind], 'supplier_bills', 'bill_number'), 'status' => 'POSTED', 'posted_at' => now(), 'due_date' => $due]);

            return $bill->fresh();
        });
    }

    /** Cancels a posted bill or credit note that nothing has been paid or credited against. The number stays used. */
    public function void(SupplierBill $bill, string $reason): SupplierBill
    {
        if ($bill->status !== 'POSTED') {
            throw new InvalidArgumentException('Only a posted document can be voided.');
        }
        if (! $bill->isCreditNote() && ($bill->paid() > 0 || $bill->credited() > 0)) {
            throw new InvalidArgumentException('Payments or credit notes are recorded against this bill; void or reverse those first.');
        }
        $bill->update(['status' => 'VOID', 'voided_at' => now(), 'void_reason' => $reason]);

        return $bill->fresh();
    }

    /**
     * A credit note received from the supplier that takes these lines off a posted bill, posted straight away. The lines may be for less
     * than the whole bill but not more than what has not yet been credited.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public function creditNote(SupplierBill $original, array $lines, string $supplierNumber, string $reason): SupplierBill
    {
        if ($original->status !== 'POSTED' || $original->isCreditNote()) {
            throw new InvalidArgumentException('A credit note can only be entered against a posted bill.');
        }

        return DB::transaction(function () use ($original, $lines, $supplierNumber, $reason) {
            $note = $this->saveDraft([
                'kind' => 'CREDIT_NOTE', 'supplier_id' => $original->supplier_id, 'original_bill_id' => $original->id, 'currency' => $original->currency,
                'payment_terms' => $original->payment_terms, 'supplier_bill_number' => $supplierNumber, 'notes' => $reason,
            ], $lines);
            $room = round($original->total - $original->credited(), 2);
            if ($note->total > $room) {
                throw new InvalidArgumentException("The credit note is for {$note->total} but only {$room} of the bill is left to credit.");
            }

            return $this->post($note);
        });
    }

    /**
     * The lines of a purchase order still to bill: what was received and not yet on a posted bill, at the order price.
     *
     * @return list<array<string, mixed>>
     */
    public function linesFromOrder(PurchaseOrder $order): array
    {
        return $order->lines->map(function (PurchaseOrderLine $l) {
            $left = round($l->received_qty - $this->billedQuantity($l->id), 3);

            return $left > 0 ? [
                'description' => $l->description, 'quantity' => $left, 'uom' => $l->uom, 'unit_price' => $l->unit_price, 'tax_percent' => $l->tax_percent, 'purchase_order_line_id' => $l->id,
            ] : null;
        })->filter()->values()->all();
    }

    /** How much of a purchase order line posted bills carry. */
    public function billedQuantity(string $purchaseOrderLineId): float
    {
        return round((float) SupplierBillLine::where('purchase_order_line_id', $purchaseOrderLineId)
            ->whereHas('bill', fn ($q) => $q->where('kind', 'BILL')->where('status', 'POSTED'))->sum('quantity'), 3);
    }
}
