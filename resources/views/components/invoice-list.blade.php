<?php

use App\Domains\Dispatch\Models\Dispatch;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\ReceiptAllocation;
use App\Domains\Finance\Models\WithholdingCertificateAllocation;
use App\Domains\Finance\Services\InvoiceService;
use App\Models\Customer;
use App\Support\Formats;
use App\Support\FinancialYear;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The invoice list (design 24a): status tabs with counts, search and customer and date chips, an edge-to-edge table, and a side
 * panel for the selected invoice whose buttons change with where it stands. The tab values are the ones the page has always
 * taken in its address (`status`, `customer`), so existing links keep working.
 */
new class extends Component
{
    use WithPagination;

    private const TABS = ['' => 'All', 'DRAFT' => 'Drafts', 'OPEN' => 'Unpaid', 'OVERDUE' => 'Overdue', 'PAID' => 'Paid', 'VOID' => 'Voided'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $customer = '';

    /** 'fy' limits the list to the financial year the day falls in; '' shows every date. */
    #[Url(except: 'fy')]
    public string $period = 'fy';

    public ?string $selectedId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCustomer(): void
    {
        $this->resetPage();
    }

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function showTab(string $tab = ''): void
    {
        $this->status = $tab;
        $this->resetPage();
    }

    public function select(string $id): void
    {
        $this->selectedId = $id;
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'customer');
        $this->period = 'fy';
        $this->resetPage();
    }

    public function paginationView(): string
    {
        return 'livewire.pagination';
    }

    /**
     * What the customer still owes on each issued invoice, worked out for the whole list in three queries instead of three per row.
     *
     * @param  Collection<int, Invoice>  $invoices
     * @return array<string, array{paid: float, balance: float}>
     */
    private function owing(Collection $invoices): array
    {
        $ids = $invoices->pluck('id');
        $paid = ReceiptAllocation::whereIn('invoice_id', $ids)->groupBy('invoice_id')->selectRaw('invoice_id, sum(amount) as s')->pluck('s', 'invoice_id');
        $credited = Invoice::where('kind', 'CREDIT_NOTE')->where('status', 'ISSUED')->whereIn('original_invoice_id', $ids)->groupBy('original_invoice_id')->selectRaw('original_invoice_id, sum(total) as s')->pluck('s', 'original_invoice_id');
        $settled = WithholdingCertificateAllocation::whereIn('invoice_id', $ids)->whereHas('certificate', fn ($q) => $q->where('status', 'ACTIVE'))->groupBy('invoice_id')->selectRaw('invoice_id, sum(amount) as s')->pluck('s', 'invoice_id');

        return $invoices->mapWithKeys(fn (Invoice $i) => [$i->id => [
            'paid' => round((float) ($paid[$i->id] ?? 0), 2),
            'balance' => $i->status === 'ISSUED' && ! $i->isCreditNote() ? round($i->total - (float) ($paid[$i->id] ?? 0) - (float) ($credited[$i->id] ?? 0) - (float) ($settled[$i->id] ?? 0), 2) : 0.0,
        ]])->all();
    }

    /** Where an invoice stands: the label, the pill colour and the tab it belongs to. */
    private function stage(Invoice $i, float $balance, float $paid): array
    {
        return match (true) {
            $i->status === 'DRAFT' => ['Draft', 'cl-grey', 'DRAFT'],
            $i->status === 'VOID' => ['Voided', 'cl-grey', 'VOID'],
            $i->isCreditNote() => ['Credit note', 'cl-info', 'CREDIT'],
            $balance <= 0 => ['Paid', 'cl-ok', 'PAID'],
            $i->due_date !== null && $i->due_date->lt(today()) => ['Overdue', 'cl-bad', 'OVERDUE'],
            $paid > 0 => ['Part paid', 'cl-info', 'OPEN'],
            default => ['Issued', 'cl-info', 'OPEN'],
        };
    }

    /** Dispatch notes shipped and not yet on an invoice, for the line under the table. */
    private function waitingDispatches(): int
    {
        if (! class_exists(Dispatch::class)) {
            return 0;
        }
        $service = app(InvoiceService::class);

        return Dispatch::with(['items.salesOrderLine.product', 'items.packingItem.lot.product'])->where('status', 'DISPATCHED')->get()
            ->filter(fn (Dispatch $d) => $service->linesFromDispatch($d) !== [])->count();
    }

    /** The sales orders, dispatch notes and receipts an invoice is tied to. */
    private function linked(Invoice $i): array
    {
        $orders = DB::table('invoice_lines')->join('sales_order_lines', 'sales_order_lines.id', '=', 'invoice_lines.sales_order_line_id')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_lines.sales_order_id')->where('invoice_lines.invoice_id', $i->id)->distinct()->pluck('sales_orders.order_number');
        $notes = DB::table('invoice_lines')->join('dispatch_items', 'dispatch_items.id', '=', 'invoice_lines.dispatch_item_id')
            ->join('dispatches', 'dispatches.id', '=', 'dispatch_items.dispatch_id')->where('invoice_lines.invoice_id', $i->id)->distinct()->pluck('dispatches.dispatch_number');
        $receipts = $i->allocations()->with('receipt')->get()->pluck('receipt.receipt_number')->filter()->unique();

        return collect([
            [$orders->count() === 1 ? 'Sales order' : 'Sales orders', $orders->implode(', ')],
            [$notes->count() === 1 ? 'Dispatch note' : 'Dispatch notes', $notes->implode(', ')],
            [$receipts->count() === 1 ? 'Receipt' : 'Receipts', $receipts->implode(', ')],
        ])->filter(fn ($l) => $l[1] !== '')->values()->all();
    }

    /** The last thing that happened to the invoice, in a line. */
    private function lastActivity(Invoice $i, float $paid): string
    {
        if ($i->status === 'DRAFT') {
            return 'Draft saved '.$i->updated_at->format('j M').'. Not posted.';
        }
        if ($i->status === 'VOID') {
            return 'Voided '.($i->voided_at ?? $i->updated_at)->format('j M').($i->void_reason ? ': '.$i->void_reason : '.');
        }
        $receipt = $i->allocations()->with('receipt')->get()->sortByDesc(fn ($a) => $a->receipt?->received_on)->first()?->receipt;
        $line = 'Posted '.($i->issued_at ?? $i->invoice_date)->format('j M').'.';

        return $receipt ? $line.' Receipt '.$receipt->receipt_number.' on '.$receipt->received_on->format('j M').' settled '.Formats::money($paid, 0).'.' : $line;
    }

    public function with(): array
    {
        $search = trim($this->search);
        $range = $this->period === 'fy' ? FinancialYear::containing() : null;
        $base = Invoice::with('customer')
            ->when($this->customer !== '' && \Illuminate\Support\Str::isUuid($this->customer), fn ($q) => $q->where('customer_id', $this->customer))
            ->when($range, fn ($q) => $q->whereBetween('invoice_date', [$range['start']->toDateString(), $range['end']->toDateString()]))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('invoice_number', 'ilike', "%{$search}%")->orWhere('customer_reference', 'ilike', "%{$search}%")
                ->orWhereHas('customer', fn ($c) => $c->matching($search))))
            ->orderByDesc('invoice_date')->orderByDesc('created_at')->get();

        $owing = $this->owing($base);
        $rows = $base->map(function (Invoice $i) use ($owing) {
            $o = $owing[$i->id];

            return ['invoice' => $i, 'balance' => $o['balance'], 'paid' => $o['paid'], 'stage' => $this->stage($i, $o['balance'], $o['paid'])];
        });
        $inTab = fn (array $r, string $tab) => match ($tab) {
            '' => true,
            'OPEN' => in_array($r['stage'][2], ['OPEN', 'OVERDUE'], true),
            default => $r['stage'][2] === $tab,
        };
        $counts = collect(self::TABS)->map(fn ($label, $tab) => $rows->filter(fn ($r) => $inTab($r, $tab))->count())->all();
        $tab = array_key_exists($this->status, self::TABS) ? $this->status : '';
        $shown = $rows->filter(fn ($r) => $inTab($r, $tab))->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paged = new LengthAwarePaginator($shown->forPage($page, 25)->values(), $shown->count(), 25, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()]);
        $selected = $shown->first(fn ($r) => $r['invoice']->id === $this->selectedId) ?? $shown->first();

        return [
            'rows' => $paged, 'counts' => $counts, 'tabs' => self::TABS, 'tab' => $tab,
            'owed' => round($shown->sum('balance'), 2),
            'customers' => Customer::whereIn('id', Invoice::select('customer_id'))->orderedByName()->get(),
            'waiting' => $this->waitingDispatches(),
            'panel' => $selected ? [
                'row' => $selected, 'linked' => $this->linked($selected['invoice']), 'last' => $this->lastActivity($selected['invoice'], $selected['paid']),
            ] : null,
        ];
    }
};
?>

<div>
<style>
.cl-card { border: 1px solid #e6e7e9; border-radius: 8px; background: #fff; overflow: hidden; font-size: 13px; }
.cl-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid #e6e7e9; }
.cl-tabs { display: flex; gap: 4px; color: #6b6e73; }
.cl-tabs a { padding: 4px 10px; border-radius: 6px; color: #6b6e73; text-decoration: none !important; }
.cl-tabs a.on { background: #f0f1f3; color: #1c1d1f; }
.cl-btn { padding: 7px 14px; border-radius: 6px; background: #fff; border: 1px solid #d9dbde; font-size: 13px; color: #1c1d1f; cursor: pointer; text-decoration: none !important; }
.cl-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; }
.cl-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; }
.cl-main { padding: 20px 28px; display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.cl-filters { display: flex; gap: 8px; align-items: center; }
.cl-filters input[type=search] { flex: 1; max-width: 320px; padding: 7px 10px; border: 1px solid #d9dbde; border-radius: 6px; font-size: 13px; }
.cl-filters select { width: auto; padding: 6px 10px; border: 1px solid #d9dbde; border-radius: 6px; background: #fff; font-size: 13px; }
.cl-table { margin: 0 -12px; overflow-x: auto; }
.cl-table td, .cl-table th { text-align: left; } .cl-table .r { text-align: right; }
.cl-table tbody tr { cursor: pointer; } .cl-table tbody tr.sel { background: #f5f8fe; }
.cl-sub { font-size: 12px; color: #6b6e73; }
.cl-pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; display: inline-block; }
.cl-ok { background: #e7f4ec; color: #1d7048; } .cl-warn { background: #fbf0dc; color: #94610a; } .cl-bad { background: #fbe7e5; color: #b3352c; } .cl-info { background: #eaf0fd; color: #2f5fd0; } .cl-grey { background: #f0f1f3; color: #55585d; }
.cl-side { border-left: 1px solid #e6e7e9; padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 18px; }
.cl-side .lab { font-size: 12px; color: #6b6e73; }
.cl-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.cl-sec { font-size: 12px; font-weight: 600; color: #6b6e73; text-transform: uppercase; letter-spacing: .06em; }
.cl-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px solid #e6e7e9; }
.cl-row span + span, .cl-row code { color: #6b6e73; text-align: right; }
@media (max-width: 1000px) { .cl-grid { grid-template-columns: minmax(0, 1fr); } .cl-side { border-left: 0; border-top: 1px solid #e6e7e9; } }
</style>

@php
    $num = fn ($v) => Formats::number((float) $v, 0);
    $p = $panel['row'] ?? null; $inv = $p['invoice'] ?? null;
@endphp
<div class="cl-card">
    <div class="cl-bar">
        <div style="display:flex;align-items:center;gap:16px">
            <strong style="font-weight:600">Invoices</strong>
            <nav class="cl-tabs">
                @foreach ($tabs as $key => $label)
                    @if ($key !== 'VOID' || $counts['VOID'] > 0)
                        <a href="#" wire:click.prevent="showTab('{{ $key }}')" @class(['on' => $tab === $key])>{{ $label }} {{ $counts[$key] }}</a>
                    @endif
                @endforeach
            </nav>
        </div>
        <div style="display:flex;gap:8px">
            <a class="cl-btn primary" href="{{ route('invoices.create') }}">New invoice</a>
        </div>
    </div>
    <div class="cl-grid">
        <div class="cl-main">
            <div class="cl-filters">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search number, customer or PO" autocomplete="off" aria-label="Search number, customer or PO">
                <select wire:model.live="customer" aria-label="Customer"><option value="">Customer: all</option>@foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                <select wire:model.live="period" aria-label="Date"><option value="fy">Date: this FY</option><option value="">Date: any</option></select>
            </div>
            @if ($rows->isEmpty())
                <div class="muted">{{ $counts[''] ? 'No invoice matches.' : 'No invoice yet.' }}</div>
            @else
            <div class="cl-table">
                <table class="no-row-click">
                    <thead><tr><th>Number</th><th>Customer</th><th>Date</th><th>Due</th><th class="r">Total</th><th class="r">Balance</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($rows as $r)
                        @php $i = $r['invoice']; [$label, $pill] = $r['stage']; $late = $label === 'Overdue'; @endphp
                        <tr wire:key="invoice-{{ $i->id }}" @class(['sel' => $inv && $inv->id === $i->id]) wire:click="select('{{ $i->id }}')">
                            <td><code style="font-weight:600">{{ $i->invoice_number ?? 'Draft' }}</code>@if ($i->isCreditNote())<div class="cl-sub">credit note</div>@endif</td>
                            <td><div style="font-weight:500">{{ $i->customer?->name }}</div><div class="cl-sub">{{ $i->customer_reference }}</div></td>
                            <td style="color:#55585d">{{ $i->invoice_date->format('j M') }}</td>
                            <td>@if ($i->due_date && $i->status !== 'DRAFT')<div>{{ $i->due_date->format('j M') }}</div><div class="cl-sub" @if ($late) style="color:#b3352c" @endif>{{ $label === 'Paid' ? 'Paid' : ($i->due_date->isPast() ? (int) $i->due_date->diffInDays(today()).' days overdue' : 'in '.(int) today()->diffInDays($i->due_date).' days') }}</div>@else — @endif</td>
                            <td class="r">{{ $num($i->total) }}</td>
                            <td class="r" style="font-weight:500">{{ $r['balance'] > 0 ? $num($r['balance']) : '—' }}</td>
                            <td><span class="cl-pill {{ $pill }}">{{ $label }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination"><div class="pagination-links">{{ $rows->links() }}</div></div>
            @endif
            <div style="display:flex;justify-content:space-between;gap:16px;font-size:12px;color:#55585d">
                <span>{{ $rows->total() }} {{ $rows->total() === 1 ? 'invoice' : 'invoices' }} · balance {{ \App\Support\Formats::money($owed, 0) }}</span>
                @if ($waiting)<span>{{ $waiting }} dispatch {{ $waiting === 1 ? 'note' : 'notes' }} not yet invoiced · <a href="{{ route('invoices.create') }}">create invoices</a></span>@endif
            </div>
        </div>

        @if ($inv)
        <aside class="cl-side" wire:key="invoice-panel-{{ $inv->id }}">
            @php [$label, $pill] = $p['stage']; @endphp
            <div>
                <div style="display:flex;align-items:center;gap:8px"><code style="font-size:16px;font-weight:600">{{ $inv->invoice_number ?? 'Draft' }}</code><span class="cl-pill {{ $pill }}">{{ $label }}</span></div>
                <div style="color:#55585d">{{ $inv->customer?->name }}@if ($inv->customer_reference) · {{ $inv->customer_reference }}@endif</div>
            </div>
            <div class="cl-two">
                <div><div class="lab">Invoice date</div><div>{{ $inv->invoice_date->format('j M Y') }}</div></div>
                <div><div class="lab">Due</div><div>{{ $inv->due_date && $inv->status !== 'DRAFT' ? $inv->due_date->format('j M Y') : '—' }}</div></div>
                <div><div class="lab">Total</div><div>{{ \App\Support\Formats::money($inv->total, 0) }}</div></div>
                <div><div class="lab">Balance</div><div style="font-weight:600">{{ \App\Support\Formats::money($p['balance'], 0) }}</div></div>
            </div>
            <div><div class="cl-sec">Amounts</div><div style="margin-top:8px">
                <div class="cl-row"><span>Value excl. tax</span><span>{{ $num($inv->subtotal) }}</span></div>
                <div class="cl-row"><span>Sales tax</span><span>{{ $num($inv->tax_total) }}</span></div>
                <div class="cl-row"><span>Received</span><span>{{ $p['paid'] > 0 ? $num($p['paid']) : '—' }}</span></div>
                <div class="cl-row"><span>Tax withheld</span><span>{{ (float) $inv->tax_withheld > 0 ? $num($inv->tax_withheld) : '—' }}</span></div>
            </div></div>
            @if ($panel['linked'])
            <div><div class="cl-sec">Linked</div><div style="margin-top:8px">
                @foreach ($panel['linked'] as [$k, $v])<div class="cl-row"><span>{{ $k }}</span><code>{{ $v }}</code></div>@endforeach
            </div></div>
            @endif
            <div><div class="cl-sec">Last activity</div><div style="margin-top:8px;color:#55585d;line-height:1.5">{{ $panel['last'] }}</div></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a class="cl-btn" href="{{ route('invoices.show', $inv) }}">Open record</a>
                @if ($inv->status === 'DRAFT')
                    <a class="cl-btn primary" href="{{ route('invoices.show', $inv) }}">Review and post</a>
                @elseif ($p['balance'] > 0)
                    <a class="cl-btn primary" href="{{ route('receipts.create', ['customer' => $inv->customer_id, 'invoice' => $inv->id]) }}">Receive payment</a>
                @elseif ($inv->status === 'ISSUED' && ! $inv->isCreditNote())
                    <a class="cl-btn" href="{{ route('invoices.credit', $inv) }}">Credit note</a>
                @endif
            </div>
        </aside>
        @endif
    </div>
</div>
</div>
