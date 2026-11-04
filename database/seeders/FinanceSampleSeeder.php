<?php

namespace Database\Seeders;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\TaxRate;
use App\Domains\Finance\Services\BillService;
use App\Domains\Finance\Services\InvoiceService;
use App\Domains\Dispatch\Models\Dispatch;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Domains\Purchasing\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sample dispatches, invoices (issued and draft) and supplier bills on the customers, products and suppliers already in the
 * database. Run on demand: php artisan db:seed --class=FinanceSampleSeeder. Skips itself when the dispatches are already there.
 */
class FinanceSampleSeeder extends Seeder
{
    private const MARK = 'Sample data';

    public function run(InvoiceService $invoices, BillService $bills): void
    {
        if (DB::table('dispatches')->where('notes', self::MARK)->exists()) {
            $this->command?->warn('Sample finance data is already there.');

            return;
        }
        $tax = TaxRate::where('is_active', true)->orderByDesc('is_default')->first();
        $taxId = $tax?->id;
        $packItem = DB::table('packing_record_items')->value('id');
        $line = fn (string $code) => SalesOrderLine::with('salesOrder')->whereHas('product', fn ($q) => $q->where('code', $code))->first();

        // Dispatch notes: [customer product code => [line code, qty]], dated; the first group stays unbilled for the new invoice screen.
        $unbilled = [
            [['PRD-0313', 120]], [['PRD-0313', 200]], [['PRD-0314', 300]],
        ];
        foreach ($unbilled as $n => $items) {
            $this->dispatch($line, $packItem, $items, now()->subDays(6 - $n * 2));
        }

        $billed = [
            ['items' => [['PRD-0253', 400], ['PRD-0240', 400]], 'days' => 12, 'prices' => [185, 240], 'issue' => true],
            ['items' => [['PRD-0153', 12000]], 'days' => 20, 'prices' => [3.85], 'issue' => true],
            ['items' => [['PRD-0255', 250]], 'days' => 55, 'prices' => [310], 'issue' => true],
            ['items' => [['PRD-0305', 500]], 'days' => 3, 'prices' => [640], 'issue' => false, 'discount' => 5],
        ];
        foreach ($billed as $b) {
            $date = now()->subDays($b['days']);
            $d = $this->dispatch($line, $packItem, $b['items'], $date);
            $lines = $invoices->linesFromDispatch(Dispatch::with(['items.salesOrderLine.product', 'items.salesOrderLine.salesOrder'])->find($d));
            foreach ($lines as $i => &$l) {
                $l['unit_price'] = $b['prices'][$i];
                $l['discount_percent'] = $b['discount'] ?? 0;
            }
            unset($l);
            $customerId = Dispatch::find($d)->customer_id;
            $inv = $invoices->saveDraft(['customer_id' => $customerId, 'invoice_date' => $date->addDay()->toDateString(), 'source_type' => 'dispatch', 'source_id' => $d], $lines);
            if ($b['issue']) {
                $invoices->issue($inv);
            }
        }

        // Two drafts without a dispatch: free lines, one with a fixed discount and tooling.
        $zeeshan = $line('PRD-0301')?->salesOrder?->customer_id;
        if ($zeeshan) {
            $invoices->saveDraft(['customer_id' => $zeeshan, 'invoice_date' => now()->toDateString(), 'customer_reference' => 'PO-ZS-114'], [
                ['description' => 'PRD-0301 Zeeshan Sb Grill Plate S', 'quantity' => 100, 'uom' => 'pcs', 'unit_price' => 950, 'discount_amount' => 500, 'tax_rate_id' => $taxId, 'product_id' => $line('PRD-0301')->product_id],
                ['description' => 'Pattern amortisation', 'quantity' => 100, 'uom' => 'pcs', 'unit_price' => 40, 'tax_rate_id' => $taxId],
                ['description' => 'Freight', 'quantity' => 1, 'uom' => 'trip', 'unit_price' => 2500, 'tax_rate_id' => $taxId],
            ]);
        }
        $omar = $line('PRD-0314')?->salesOrder?->customer_id;
        if ($omar) {
            $invoices->saveDraft(['customer_id' => $omar, 'invoice_date' => now()->toDateString()], [
                ['description' => 'PRD-0314 4in RED Iron Base', 'quantity' => 50, 'uom' => 'pcs', 'unit_price' => 4258.53, 'discount_percent' => 2.5, 'tax_rate_id' => $taxId, 'product_id' => $line('PRD-0314')->product_id],
            ]);
        }

        // Supplier bills: three posted (one overdue), one draft.
        $suppliers = Supplier::where('approval_status', 'APPROVED')->orderBy('code')->get();
        foreach ($suppliers->take(2)->values() as $s => $supplier) {
            foreach ([
                ['days' => 50, 'no' => 'SB-'.(4100 + $s), 'what' => 'Hard coke', 'qty' => 2000, 'uom' => 'kg', 'price' => 62.5, 'post' => true],
                ['days' => 9, 'no' => 'SB-'.(4200 + $s), 'what' => 'Ferrosilicon', 'qty' => 400, 'uom' => 'kg', 'price' => 310, 'post' => true],
                ['days' => 1, 'no' => null, 'what' => 'Bentonite', 'qty' => 25, 'uom' => 'bag', 'price' => 1450, 'post' => false],
            ] as $row) {
                if ($s === 1 && $row['days'] === 9) {
                    continue;
                }
                $bill = $bills->saveDraft([
                    'supplier_id' => $supplier->id, 'bill_date' => now()->subDays($row['days'])->toDateString(), 'supplier_bill_number' => $row['no'], 'payment_terms' => 'Net 30',
                ], [['description' => $row['what'], 'quantity' => $row['qty'], 'uom' => $row['uom'], 'unit_price' => $row['price'], 'tax_percent' => $tax ? (float) $tax->rate_percent : 0]]);
                if ($row['post']) {
                    $bills->post($bill);
                }
            }
        }
        $this->command?->info('Added '.Dispatch::where('notes', self::MARK)->count().' dispatches, '.Invoice::count().' invoices in all and '.SupplierBill::count().' supplier bills.');
    }

    /**
     * @param  callable(string): ?SalesOrderLine  $line
     * @param  list<array{0: string, 1: int|float}>  $items
     */
    private function dispatch(callable $line, ?string $packItem, array $items, \DateTimeInterface $at): string
    {
        $first = $line($items[0][0]);
        $id = (string) Str::uuid();
        $number = 'DSP-'.$at->format('Ymd').'-'.str_pad((string) (DB::table('dispatches')->where('dispatch_number', 'like', 'DSP-'.$at->format('Ymd').'-%')->count() + 101), 3, '0', STR_PAD_LEFT);
        DB::table('dispatches')->insert(['id' => $id, 'dispatch_number' => $number, 'customer_id' => $first->salesOrder->customer_id, 'status' => 'DISPATCHED', 'dispatched_at' => $at, 'total_net_weight_kg' => 0, 'total_packages' => count($items), 'notes' => self::MARK, 'created_at' => $at, 'updated_at' => $at]);
        foreach ($items as [$code, $qty]) {
            DB::table('dispatch_items')->insert(['id' => (string) Str::uuid(), 'dispatch_id' => $id, 'packing_record_item_id' => $packItem, 'sales_order_line_id' => $line($code)->id, 'quantity_dispatched' => $qty, 'net_weight_kg' => 0, 'uom' => 'PCS', 'created_at' => $at, 'updated_at' => $at]);
        }

        return $id;
    }
}
