<?php

namespace App\Domains\Purchasing\Services;

use App\Domains\Core\Contracts\TaxCalculator;
use App\Domains\Core\Tax\TaxContext;
use App\Domains\Core\Tax\TaxLine;
use App\Domains\Purchasing\Models\PurchaseOrder;
use App\Domains\Purchasing\Models\PurchaseOrderLine;
use App\Domains\Purchasing\Models\Supplier;
use App\Domains\Purchasing\Support\BuyingRules;
use App\Models\MaterialLot;
use App\Notifications\AppNotice;
use App\Services\Notifications\Notify;
use App\Support\Formats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Writing, issuing, receiving against and closing purchase orders. */
class PurchaseOrderService
{
    /**
     * Saves a draft: the head and its lines, with every amount worked out here. Pass an existing draft to replace its lines.
     *
     * @param  array<string, mixed>  $head
     * @param  list<array<string, mixed>>  $lines
     */
    public function saveDraft(array $head, array $lines, ?PurchaseOrder $order = null): PurchaseOrder
    {
        $lines = array_values(array_filter($lines, fn ($l) => trim((string) ($l['description'] ?? '')) !== ''));
        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one line with a description.');
        }
        if ($order !== null && $order->status !== 'DRAFT') {
            throw new InvalidArgumentException("Only a draft can be changed; this one is {$order->status}.");
        }
        $supplier = Supplier::findOrFail($head['supplier_id']);
        if ($supplier->isBlocked()) {
            throw new InvalidArgumentException("{$supplier->name} is ".strtolower(Supplier::STATUSES[$supplier->approval_status]).' and cannot be ordered from.');
        }

        return DB::transaction(function () use ($head, $lines, $order, $supplier) {
            $head += ['order_date' => today()->toDateString(), 'currency' => Formats::all()['currency']];
            if ($order === null) {
                $order = PurchaseOrder::create($head + ['status' => 'DRAFT']);
            } else {
                $order->update($head);
                $order->lines()->delete();
            }
            foreach ($lines as $i => $l) {
                if (round((float) ($l['ordered_qty'] ?? 0), 3) <= 0) {
                    throw new InvalidArgumentException('Line '.($i + 1).' needs a quantity.');
                }
            }
            $taxLines = array_map(fn ($l) => new TaxLine(
                round(round((float) $l['ordered_qty'], 3) * round((float) ($l['unit_price'] ?? 0), 4), 2),
                round((float) ($l['tax_percent'] ?? 0), 3),
            ), $lines);
            $taxes = app(TaxCalculator::class)->calculate(new TaxContext(TaxContext::PURCHASE, Carbon::parse($head['order_date']), $supplier->party_id), $taxLines);
            [$subtotal, $tax] = [0.0, 0.0];
            foreach ($lines as $i => $l) {
                $qty = round((float) $l['ordered_qty'], 3);
                $price = round((float) ($l['unit_price'] ?? 0), 4);
                $percent = $taxes->chargedRate($i);
                $net = $taxLines[$i]->net;
                $lineTax = $taxes->charged($i);
                $order->lines()->create([
                    'line_number' => $i + 1, 'material_id' => ($l['material_id'] ?? null) ?: null, 'description' => trim($l['description']), 'ordered_qty' => $qty,
                    'uom' => $l['uom'] ?? null, 'unit_price' => $price, 'tax_percent' => $percent, 'line_net' => $net, 'line_tax' => $lineTax,
                ]);
                $subtotal += $net;
                $tax += $lineTax;
            }
            $order->update(['subtotal' => round($subtotal, 2), 'tax_total' => round($tax, 2), 'total' => round($subtotal + $tax, 2)]);

            return $order->fresh('lines');
        });
    }

    /** Gives a draft its number and sends it to the supplier. */
    public function issue(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $order = PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'DRAFT') {
                throw new InvalidArgumentException("This is already {$order->status}.");
            }
            if (! $order->lines()->exists()) {
                throw new InvalidArgumentException('An order with no lines cannot be issued.');
            }
            $order->update(['po_number' => $this->nextNumber(), 'status' => 'ISSUED', 'issued_at' => now()]);

            return $order->fresh();
        });
    }

    /**
     * Receives goods against lines. Each line with a material makes a material lot in quarantine, priced at the order price, to be
     * accepted through the incoming checks as before; a line with no material only has its received quantity counted.
     *
     * @param  array<string, float|int|string|null>  $quantities  line id => quantity received now
     * @return list<MaterialLot> the lots made
     */
    public function receive(PurchaseOrder $order, array $quantities, ?string $supplierLotNumber = null, ?string $receivedOn = null): array
    {
        $quantities = array_filter(array_map(fn ($q) => round((float) $q, 3), $quantities), fn ($q) => $q > 0);
        if ($quantities === []) {
            throw new InvalidArgumentException('Enter a quantity against at least one line.');
        }

        return DB::transaction(function () use ($order, $quantities, $supplierLotNumber, $receivedOn) {
            $order = PurchaseOrder::with('supplier')->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $order->canReceive()) {
                throw new InvalidArgumentException('Goods can only be received against an issued order that is not yet fully received.');
            }
            $lots = [];
            foreach ($quantities as $lineId => $qty) {
                $line = PurchaseOrderLine::with('material')->whereKey($lineId)->where('purchase_order_id', $order->id)->lockForUpdate()->first();
                if (! $line) {
                    throw new InvalidArgumentException('A line does not belong to this order.');
                }
                $limit = BuyingRules::receiptLimit($line->ordered_qty);
                if ($limit !== null && $line->received_qty + $qty > $limit + 0.0005) {
                    $room = max(0, round($limit - $line->received_qty, 3));
                    throw new InvalidArgumentException("Line {$line->line_number} can take at most {$room} more.");
                }
                if ($line->material) {
                    $lots[] = MaterialLot::create([
                        'lot_number' => $this->lotNumber($order, $line), 'material_id' => $line->material_id, 'supplier_id' => $order->supplier_id,
                        'supplier_name_snapshot' => $order->supplier->name, 'supplier_lot_number' => $supplierLotNumber ?: null, 'purchase_order_line_id' => $line->id,
                        'received_date' => $receivedOn ?: today()->toDateString(), 'status' => 'QUARANTINE', 'received_qty' => $qty, 'accepted_qty' => 0,
                        'unit_cost' => $line->unit_price, 'currency_code' => strtoupper(substr($order->currency ?: 'PKR', 0, 3)),
                    ]);
                }
                $line->update(['received_qty' => round($line->received_qty + $qty, 3)]);
            }
            $this->refreshStatus($order);
            if ($lots !== []) {
                app(Notify::class)->toPermission('quality.manage', new AppNotice(
                    'incoming',
                    count($lots) === 1 ? "Lot {$lots[0]->lot_number} is waiting for incoming inspection" : count($lots).' lots are waiting for incoming inspection',
                    "Received against {$order->po_number} from {$order->supplier->name}: ".collect($lots)->pluck('lot_number')->implode(', ').'.',
                    'info',
                    route('incoming.lots.show', $lots[0]),
                ));
            }

            return $lots;
        });
    }

    /** Part received, received or still issued, from what the lines say. */
    public function refreshStatus(PurchaseOrder $order): void
    {
        if (! $order->canReceive() && $order->status !== 'RECEIVED') {
            return;
        }
        $lines = $order->lines()->get();
        $any = $lines->sum('received_qty') > 0;
        $done = $lines->every(fn (PurchaseOrderLine $l) => $l->received_qty >= $l->ordered_qty);
        $order->update(['status' => $done ? 'RECEIVED' : ($any ? 'PART_RECEIVED' : 'ISSUED')]);
    }

    /** Stops an order: nothing more is expected. A draft is cancelled; an issued one with goods received is closed short. */
    public function close(PurchaseOrder $order, string $reason): PurchaseOrder
    {
        if (in_array($order->status, ['CLOSED', 'CANCELLED'], true)) {
            throw new InvalidArgumentException("This is already {$order->status}.");
        }
        $received = $order->lines()->sum('received_qty') > 0;
        $order->update(['status' => $received || $order->status === 'RECEIVED' ? 'CLOSED' : 'CANCELLED', 'closed_at' => now(), 'close_reason' => $reason]);

        return $order->fresh();
    }

    /** The next order number, restarting each year: PO-2026-0001. Call inside a transaction. */
    public function nextNumber(): string
    {
        $like = 'PO-'.now()->format('Y').'-';
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['purchase_orders-number-'.$like]);
        $n = (DB::table('purchase_orders')->where('po_number', 'like', $like.'%')->pluck('po_number')->map(fn ($x) => (int) substr($x, strlen($like)))->max() ?? 0) + 1;

        return $like.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function lotNumber(PurchaseOrder $order, PurchaseOrderLine $line): string
    {
        $base = $order->po_number.'-'.$line->line_number;
        $n = MaterialLot::where('purchase_order_line_id', $line->id)->count() + 1;
        do {
            $number = $base.'-'.$n++;
        } while (MaterialLot::where('lot_number', $number)->exists());

        return $number;
    }
}
