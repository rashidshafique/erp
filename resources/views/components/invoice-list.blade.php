<?php

use App\Domains\Dispatch\Models\Dispatch;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\ReceiptAllocation;
use App\Domains\Finance\Models\WithholdingCertificateAllocation;
use App\Domains\Finance\Services\CreditLimits;
use App\Domains\Finance\Services\FinanceRules;
use App\Domains\Finance\Services\InvoiceService;
use App\Models\Customer;
use App\Support\Formats;
use App\Support\FinancialYear;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The invoice list (design 24a): status tabs with counts, a filter bar (search, customer, a date menu of presets with a custom
 * day or range on a two-month calendar, and sort), an edge-to-edge table sorted from its headers, and a side panel for the
 * selected invoice whose buttons change with where it stands. The tab values are the ones the page has always taken in its
 * address (`status`, `customer`), so existing links keep working.
 */
new class extends Component
{
    use WithPagination;

    private const TABS = ['' => 'All', 'DRAFT' => 'Drafts', 'OPEN' => 'Unpaid', 'PART' => 'Part paid', 'OVERDUE' => 'Overdue', 'PAID' => 'Paid', 'VOID' => 'Voided'];

    /** Warn on a customer's invoices from this share of its credit limit. */
    private const CREDIT_NEAR = 90;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $customer = '';

    /** Months back for the presets, keyed by the `period` value. */
    private const MONTHS = ['1m' => 1, '3m' => 3, '6m' => 6, '12m' => 12];

    /**
     * The table's columns in order, each sorting the list: label, alignment, the first direction a click gives, and the words for each direction.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    private const SORTS = [
        'date' => ['Date', 'left', 'desc', 'date, newest first', 'date, oldest first'],
        'number' => ['Number', 'left', 'desc', 'number, newest first', 'number, oldest first'],
        'customer' => ['Customer', 'left', 'asc', 'customer, A to Z', 'customer, Z to A'],
        'due' => ['Status · due', 'left', 'asc', 'due date, soonest first', 'due date, latest first'],
        'total' => ['Total', 'right', 'desc', 'total, largest first', 'total, smallest first'],
        'balance' => ['Balance', 'right', 'desc', 'balance, largest first', 'balance, smallest first'],
    ];

    /** 'fy' (default) the financial year today falls in; '1m', '3m', '6m', '12m' back from today; 'day' or 'range' from `from` to `to`; 'all' (or '') every date. */
    #[Url(except: 'fy')]
    public string $period = 'fy';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: 'date')]
    public string $sort = 'date';

    #[Url(except: 'desc')]
    public string $dir = 'desc';

    public ?string $selectedId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCustomer(): void
    {
        $this->resetPage();
    }

    /** A preset from the date menu. */
    public function setPeriod(string $period): void
    {
        $this->period = array_key_exists($period, self::MONTHS) || in_array($period, ['fy', 'all'], true) ? $period : 'fy';
        $this->resetPage();
    }

    /** A custom day (from equals to) or range picked on the calendar; the dates come as Y-m-d and are put in order. */
    public function setRange(string $from, string $to): void
    {
        $valid = fn (string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && strtotime($d) !== false;
        if (! $valid($from) || ! $valid($to)) {
            return;
        }
        [$this->from, $this->to] = $from <= $to ? [$from, $to] : [$to, $from];
        $this->period = $this->from === $this->to ? 'day' : 'range';
        $this->resetPage();
    }

    /** A header click: a new column starts in its natural direction, the same column turns round. */
    public function sortBy(string $key): void
    {
        if (! array_key_exists($key, self::SORTS)) {
            return;
        }
        if ($this->sort === $key) {
            $this->dir = $this->dir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $key;
            $this->dir = self::SORTS[$key][2];
        }
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
        $this->reset('search', 'customer', 'from', 'to');
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

    /**
     * The dates the list is limited to, or null for every date.
     *
     * @return array{start: Carbon, end: Carbon}|null
     */
    private function range(): ?array
    {
        $today = today();

        return match (true) {
            $this->period === 'fy' => FinancialYear::containing(),
            array_key_exists($this->period, self::MONTHS) => ['start' => $today->copy()->subMonthsNoOverflow(self::MONTHS[$this->period]), 'end' => $today],
            in_array($this->period, ['day', 'range'], true) && $this->from !== '' => ['start' => Carbon::parse($this->from), 'end' => Carbon::parse($this->to ?: $this->from)],
            default => null,
        };
    }

    /**
     * The date menu: its presets with the words under each, and the chip's label.
     *
     * @return array{label: string, presets: list<array{key: string, label: string, sub: string}>}
     */
    private function dateMenu(): array
    {
        $since = fn (int $n, bool $year = false) => 'since '.today()->subMonthsNoOverflow($n)->format($year ? 'j M Y' : 'j M');
        $fy = FinancialYear::containing();
        $fyWords = $fy['start']->format('M').'–'.$fy['end']->format('M');
        $day = fn (string $d) => Carbon::parse($d)->format('j M');
        $custom = match ($this->period) {
            'day' => $this->from !== '' ? $day($this->from) : '',
            'range' => $this->from !== '' ? $day($this->from).' – '.$day($this->to ?: $this->from) : '',
            default => '',
        };
        $presets = [
            ['key' => '1m', 'label' => 'Last month', 'sub' => $since(1)],
            ['key' => '3m', 'label' => 'Last 3 months', 'sub' => $since(3)],
            ['key' => '6m', 'label' => 'Last 6 months', 'sub' => $since(6)],
            ['key' => '12m', 'label' => 'Last 12 months', 'sub' => $since(12, true)],
            ['key' => 'fy', 'label' => 'This financial year', 'sub' => $fyWords],
            ['key' => 'all', 'label' => 'All dates', 'sub' => ''],
            ['key' => 'day', 'label' => 'Custom date', 'sub' => $this->period === 'day' ? $custom : 'pick a day ›'],
            ['key' => 'range', 'label' => 'Custom range', 'sub' => $this->period === 'range' ? $custom : 'pick dates ›'],
        ];
        $label = match (true) {
            $custom !== '' => $custom,
            $this->period === 'fy' => 'This FY · '.$fyWords,
            array_key_exists($this->period, self::MONTHS) => collect($presets)->firstWhere('key', $this->period)['label'],
            default => 'All dates',
        };

        return ['label' => $label, 'presets' => $presets];
    }

    /**
     * Each customer's credit position against its limit, for customers with a limit or a hold while credit checks are on.
     *
     * @param  Collection<int, Customer>  $customers
     * @return array<string, array{limit: ?float, used: float, owed: float, orders: float, pct: int, over: bool, near: bool, hold: bool, note: string}>
     */
    private function credit(Collection $customers): array
    {
        $rule = FinanceRules::creditCheck();
        if ($rule === 'OFF') {
            return [];
        }
        $limits = app(CreditLimits::class);
        $money = fn (float $v) => Formats::money($v, 0);

        return $customers->filter(fn (Customer $c) => $c->credit_limit !== null || $c->credit_hold)->mapWithKeys(function (Customer $c) use ($limits, $rule, $money) {
            $e = $limits->exposure($c->id);
            $limit = $c->credit_limit !== null ? (float) $c->credit_limit : null;
            $pct = $limit > 0 ? (int) round($e['total'] * 100 / $limit) : 0;
            $over = $limit !== null && $e['total'] > $limit;
            $released = $c->credit_release_until && $c->credit_release_until->gte(today());
            $note = match (true) {
                $released => 'Released until '.$c->credit_release_until->displayDate().': orders and dispatches go through.',
                (bool) $c->credit_hold => 'On credit hold'.($c->credit_hold_reason ? ': '.$c->credit_hold_reason : '').'. New orders and dispatches stop.',
                $over && $rule === 'BLOCK' => "New orders and dispatches for {$c->name} stop until the balance is under the limit.",
                $over => "New orders and dispatches for {$c->name} warn until the balance is under the limit.",
                default => '',
            };

            return [$c->id => ['limit' => $limit, 'used' => $e['total'], 'owed' => round($e['invoices'] - $e['on_account'], 2), 'orders' => $e['orders'], 'pct' => $pct,
                'over' => $over, 'near' => ! $over && $limit !== null && $pct >= self::CREDIT_NEAR, 'hold' => (bool) $c->credit_hold && ! $released, 'note' => $note]];
        })->all();
    }

    public function with(): array
    {
        $search = trim($this->search);
        $range = $this->range();
        $base = Invoice::with('customer')
            ->when($this->customer !== '' && \Illuminate\Support\Str::isUuid($this->customer), fn ($q) => $q->where('customer_id', $this->customer))
            ->when($range, fn ($q) => $q->whereBetween('invoice_date', [$range['start']->toDateString(), $range['end']->toDateString()]))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('invoice_number', 'ilike', "%{$search}%")->orWhere('customer_reference', 'ilike', "%{$search}%")
                ->orWhereHas('customer', fn ($c) => $c->matching($search))))
            ->orderByDesc('invoice_date')->orderByDesc('created_at')->get();
        $sort = array_key_exists($this->sort, self::SORTS) ? $this->sort : 'date';
        $desc = $this->dir !== 'asc';

        $owing = $this->owing($base);
        $rows = $base->map(function (Invoice $i) use ($owing) {
            $o = $owing[$i->id];

            return ['invoice' => $i, 'balance' => $o['balance'], 'paid' => $o['paid'], 'stage' => $this->stage($i, $o['balance'], $o['paid'])];
        });
        $inTab = fn (array $r, string $tab) => match ($tab) {
            '' => true,
            'OPEN' => in_array($r['stage'][2], ['OPEN', 'OVERDUE'], true),
            'PART' => in_array($r['stage'][2], ['OPEN', 'OVERDUE'], true) && $r['paid'] > 0,
            default => $r['stage'][2] === $tab,
        };
        $counts = collect(self::TABS)->map(fn ($label, $tab) => $rows->filter(fn ($r) => $inTab($r, $tab))->count())->all();
        $tab = array_key_exists($this->status, self::TABS) ? $this->status : '';
        $key = match ($sort) {
            'date' => fn ($r) => $r['invoice']->invoice_date->toDateString(),
            'customer' => fn ($r) => mb_strtolower((string) $r['invoice']->customer?->name),
            'number' => fn ($r) => (string) $r['invoice']->invoice_number,
            'due' => fn ($r) => $r['invoice']->status === 'DRAFT' || ! $r['invoice']->due_date ? '9999-12-31' : $r['invoice']->due_date->toDateString(),
            'total' => fn ($r) => (float) $r['invoice']->total,
            'balance' => fn ($r) => $r['balance'],
        };
        $shown = $rows->filter(fn ($r) => $inTab($r, $tab));
        $shown = ($desc ? $shown->sortByDesc($key) : $shown->sortBy($key))->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paged = new LengthAwarePaginator($shown->forPage($page, 25)->values(), $shown->count(), 25, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()]);
        $selected = $shown->first(fn ($r) => $r['invoice']->id === $this->selectedId) ?? $shown->first();
        $onPage = collect($paged->items())->map(fn ($r) => $r['invoice']->customer)->push($selected['invoice']->customer ?? null)->filter()->unique('id');

        return [
            'rows' => $paged, 'counts' => $counts, 'tabs' => self::TABS, 'tab' => $tab,
            'owed' => round($shown->sum('balance'), 2),
            'customers' => Customer::whereIn('id', Invoice::select('customer_id'))->orderedByName()->get(),
            'date' => $this->dateMenu(),
            'anyInvoice' => $base->isNotEmpty() || Invoice::query()->exists(),
            'credit' => $this->credit($onPage),
            'filtersOn' => ($this->customer !== '' ? 1 : 0) + ($range ? 1 : 0) + ($search !== '' ? 1 : 0),
            'sorts' => collect(self::SORTS)->map(fn ($s, $k) => ['label' => $s[0], 'align' => $s[1], 'on' => $k === $sort, 'words' => ucfirst($s[2] === $this->dir || $k !== $sort ? $s[3] : $s[4])])->all(),
            'sortWords' => ucfirst(self::SORTS[$sort][$desc === (self::SORTS[$sort][2] === 'desc') ? 3 : 4]),
            'sortDesc' => $desc,
            // invoices per day over the last two years, for the dots and the count on the calendar
            'perDay' => Invoice::query()->where('invoice_date', '>=', today()->subYears(2))->groupBy('invoice_date')->selectRaw('invoice_date, count(*) as n')->toBase()->pluck('n', 'invoice_date')
                ->mapWithKeys(fn ($n, $d) => [substr((string) $d, 0, 10) => (int) $n])->all(),
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
[x-cloak] { display: none !important; }
.cl-card { border: 1px solid #e6e7e9; border-radius: 8px; background: #fff; font-size: 13px; }
.cl-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid #e6e7e9; }
.cl-tabs { display: flex; gap: 18px; align-self: stretch; margin: -14px 0; }
.cl-tabs a { display: inline-flex; align-items: center; gap: 7px; padding: 0 2px; white-space: nowrap; color: #55585d; font-weight: 500; text-decoration: none !important; }
.cl-tabs a.on { color: #1c1d1f; font-weight: 600; box-shadow: inset 0 -2px 0 var(--accent); }
.cl-tabs .dot { width: 7px; height: 7px; border-radius: 50%; }
.cl-tabs .n { font-size: 12px; font-weight: 500; color: #6b6e73; font-variant-numeric: tabular-nums; }
.cl-tabs a.on .n { color: var(--accent); } .cl-tabs .n.bad { color: #b3352c; }
.cl-btn { padding: 7px 14px; border-radius: 6px; background: #fff; border: 1px solid #d9dbde; font-size: 13px; color: #1c1d1f; cursor: pointer; text-decoration: none !important; }
.cl-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; }
.cl-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; }
.cl-main { padding: 20px 28px; display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.cl-filters { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.cl-search { flex: 1; min-width: 180px; max-width: 320px; display: flex; align-items: center; gap: 8px; height: 34px; padding: 0 10px; box-sizing: border-box; border: 1px solid #c9cbcf; border-radius: 6px; background: #fff; }
.cl-search input { flex: 1; min-width: 0; border: 0 !important; padding: 0 !important; height: 32px; font-size: 13px; background: transparent; box-shadow: none !important; outline: 0; }
.cl-chip { position: relative; display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 10px 0 12px; box-sizing: border-box; border: 1px solid #c9cbcf; border-radius: 6px; background: #fff; box-shadow: 0 1px 0 rgba(0,0,0,.04); cursor: pointer; white-space: nowrap; font-size: 13px; color: #1c1d1f; }
.cl-chip .k { color: #55585d; } .cl-chip b { font-weight: 500; }
.cl-chip.open { border-color: var(--accent); background: var(--accent-soft); }
.cl-chip select { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; }
.cl-fbtn { font-weight: 500; padding: 0 12px; }
.cl-badge { min-width: 18px; height: 18px; padding: 0 5px; box-sizing: border-box; border-radius: 9px; background: var(--accent); color: #fff; font-size: 11.5px; font-weight: 600; line-height: 18px; text-align: center; }
.cl-sep { width: 1px; height: 20px; background: #e6e7e9; margin: 0 2px; }
.cl-pop { position: absolute; top: 40px; right: 0; z-index: 20; display: flex; align-items: flex-start; background: #fff; border: 1px solid #e6e7e9; border-radius: 10px; box-shadow: 0 12px 32px rgba(0,0,0,.14), 0 2px 6px rgba(0,0,0,.06); font-size: 13px; white-space: nowrap; max-width: calc(100vw - 32px); overflow-x: auto; cursor: default; }
.cl-pre { width: 230px; padding: 6px; }
.cl-pre button { display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 10px; padding: 8px 10px; border: 0; border-radius: 6px; background: transparent; color: #1c1d1f; font-size: 13px; text-align: left; cursor: pointer; }
.cl-pre button:hover { background: #f5f6f7; }
.cl-pre button.on { background: var(--accent-soft); color: var(--accent-dark); font-weight: 600; }
.cl-pre button.first-custom { margin-top: 6px; box-shadow: 0 -6px 0 #fff, 0 -7px 0 #eff0f2; }
.cl-pre .sub { font-size: 12px; color: #6b6e73; font-weight: 400; }
.cl-cal { padding: 14px 16px; display: flex; flex-direction: column; gap: 12px; border-right: 1px solid #eff0f2; }
.cl-cal .mon { width: 224px; }
.cl-cal .mhead { display: flex; align-items: center; justify-content: space-between; font-weight: 600; margin-bottom: 8px; }
.cl-cal .mhead button { border: 0; background: transparent; padding: 0 6px; font-size: 16px; color: #55585d; cursor: pointer; }
.cl-cal .wk, .cl-cal .days { display: grid; grid-template-columns: repeat(7, 32px); text-align: center; }
.cl-cal .wk { font-size: 11.5px; color: #6b6e73; margin-bottom: 4px; } .cl-cal .days { row-gap: 2px; }
.cl-cal .days button { position: relative; height: 30px; border: 0; padding: 0; background: transparent; border-radius: 6px; font-size: 13px; color: #1c1d1f; cursor: pointer; }
.cl-cal .days button.future { color: #9a9da2; }
.cl-cal .days button.today { box-shadow: inset 0 0 0 1px var(--accent); font-weight: 600; }
.cl-cal .days button.mid { background: var(--accent-soft); border-radius: 0; }
.cl-cal .days button.end { background: var(--accent); color: #fff; font-weight: 600; box-shadow: none; }
.cl-cal .days button.has::after { content: ''; position: absolute; bottom: 3px; left: 50%; margin-left: -2px; width: 4px; height: 4px; border-radius: 50%; background: var(--accent); }
.cl-cal .days button.end.has::after { background: #fff; }
.cl-cal .foot { display: flex; align-items: center; gap: 8px; border-top: 1px solid #eff0f2; padding-top: 12px; }
.cl-cal .foot button { padding: 6px 12px; border: 0; border-radius: 6px; background: transparent; color: #55585d; font-size: 13px; cursor: pointer; }
.cl-cal .foot button.apply { padding: 6px 14px; background: var(--accent); color: #fff; font-weight: 500; }
.cl-cal .foot button.apply:disabled { opacity: .45; cursor: default; }
.cl-sort { margin-left: auto; display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 4px; border: 0; background: transparent; color: #55585d; cursor: pointer; white-space: nowrap; font-size: 13px; position: relative; }
.cl-sort b { font-weight: 500; color: #1c1d1f; }
.cl-table th.s { cursor: pointer; user-select: none; white-space: nowrap; color: #55585d; font-weight: 500; }
.cl-table th.s.on { color: #1c1d1f; font-weight: 600; }
.cl-table th.s .ar { display: inline-block; width: 12px; margin-left: 4px; font-size: 11px; color: #c9cbcf; } .cl-table th.s.on .ar { color: var(--accent); }
.cl-table { margin: 0 -12px; overflow-x: auto; }
.cl-table td, .cl-table th { text-align: left; } .cl-table .r { text-align: right; }
.cl-table tbody tr { cursor: pointer; } .cl-table tbody tr.sel { background: #f5f8fe; }
.cl-sub { font-size: 12px; color: #6b6e73; }
.cl-ctx { position: fixed; z-index: 50; min-width: 160px; padding: 4px; background: #fff; border: 1px solid #e6e7e9; border-radius: 8px; box-shadow: 0 12px 32px rgba(0,0,0,.14), 0 2px 6px rgba(0,0,0,.06); font-size: 13px; }
.cl-ctx a { display: block; padding: 7px 10px; border-radius: 6px; color: #1c1d1f; text-decoration: none !important; outline: 0; }
.cl-ctx a:hover, .cl-ctx a:focus { background: #f5f6f7; }
.cl-table .nw { white-space: nowrap; font-variant-numeric: tabular-nums; }
.cl-table code { font-weight: 400; background: none; padding: 0; color: inherit; }
.cl-meter { margin-top: 8px; height: 6px; border-radius: 3px; background: #e6e7e9; overflow: hidden; } .cl-meter div { height: 6px; border-radius: 3px; }
.cl-pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; display: inline-block; }
.cl-ok { background: #e7f4ec; color: #1d7048; } .cl-warn { background: #fbf0dc; color: #94610a; } .cl-bad { background: #fbe7e5; color: #b3352c; } .cl-info { background: #eaf0fd; color: #2f5fd0; } .cl-grey { background: #f0f1f3; color: #55585d; }
.cl-side { border-left: 1px solid #e6e7e9; border-radius: 0 0 8px 0; padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 18px; }
.cl-side .lab { font-size: 12px; color: #6b6e73; }
.cl-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.cl-sec { font-size: 12px; font-weight: 600; color: #6b6e73; text-transform: uppercase; letter-spacing: .06em; }
.cl-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px solid #e6e7e9; }
.cl-row span + span, .cl-row code { color: #6b6e73; text-align: right; }
@media (max-width: 1000px) { .cl-grid { grid-template-columns: minmax(0, 1fr); } .cl-side { border-left: 0; border-top: 1px solid #e6e7e9; border-radius: 0 0 8px 8px; } }
</style>

@php
    $num = fn ($v) => Formats::number((float) $v, 0);
    $p = $panel['row'] ?? null; $inv = $p['invoice'] ?? null;
@endphp
<div class="cl-card" x-data="{ menu: false, mx: 0, my: 0, invoiceUrl: '', customerUrl: '', openMenu(e, invoice, customer) { this.invoiceUrl = invoice; this.customerUrl = customer; this.mx = Math.min(e.clientX, window.innerWidth - 180); this.my = Math.min(e.clientY, window.innerHeight - 90); this.menu = true; this.$nextTick(() => this.$refs.menu.querySelector('a').focus()); } }"
     @click.window="menu = false" @keydown.escape.window="menu = false" @scroll.window="menu = false" @resize.window="menu = false">
    {{-- Closing only hides the menu: the links keep their addresses, so a click still follows them. --}}
    <div class="cl-ctx" x-ref="menu" x-show="menu" x-cloak role="menu" :style="`left:${mx}px;top:${my}px`" @contextmenu.prevent>
        <a role="menuitem" :href="invoiceUrl">Open invoice</a>
        <a role="menuitem" :href="customerUrl">Open customer</a>
    </div>
    <div class="cl-bar">
        <div style="display:flex;align-items:center;gap:16px">
            <strong style="font-weight:600">Invoices</strong>
            <nav class="cl-tabs" role="tablist">
                @foreach ($tabs as $key => $label)
                    @if ($key !== 'VOID' || $counts['VOID'] > 0)
                        <a href="#" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click.prevent="showTab('{{ $key }}')" @class(['on' => $tab === $key])><span class="dot" style="background:{{ ['' => 'transparent', 'DRAFT' => '#9a9da2', 'OPEN' => '#2f5fd0', 'PART' => '#7f9ee6', 'OVERDUE' => '#b3352c', 'PAID' => '#1d7048', 'VOID' => '#c4c7cc'][$key] }}"></span>{{ $label }} <span @class(['n', 'bad' => $key === 'OVERDUE' && $counts[$key] > 0])>{{ $counts[$key] }}</span></a>
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
                <label class="cl-search">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#55585d" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search number, customer or PO" autocomplete="off" aria-label="Search number, customer or PO">
                </label>
                <button type="button" class="cl-chip cl-fbtn" wire:click="resetFilters" title="Reset filters: all customers, this financial year, no search">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#1c1d1f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8L3 5z"/></svg>
                    Filters @if ($filtersOn)<span class="cl-badge">{{ $filtersOn }}</span>@endif
                </button>
                <span class="cl-sep" aria-hidden="true"></span>
                <label class="cl-chip">
                    <span class="k">Customer</span><b>{{ $customers->firstWhere('id', $customer)?->name ?? 'All' }}</b>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#55585d" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    <select data-search-select wire:model.live="customer" aria-label="Customer"><option value="">All</option>@foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                </label>
                <span style="position:relative;display:inline-flex" wire:key="date-menu"
                      x-data="invoiceDates(@js($perDay), @js(today()->toDateString()), @js($from))" @click.outside="close()" @keydown.escape.window="close()">
                    <button type="button" class="cl-chip" :class="{ open }" @click="open ? close() : (open = true)" aria-haspopup="true" :aria-expanded="open">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#55585d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                        <span class="k">Date</span><b>{{ $date['label'] }}</b>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#55585d" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="cl-pop" x-show="open" x-cloak>
                        <div class="cl-cal" x-show="cal">
                            <div style="display:flex;gap:22px">
                                <template x-for="(m, mi) in months()" :key="m.key">
                                    <div class="mon">
                                        <div class="mhead">
                                            <button type="button" x-show="mi === 0" @click="shift(-1)" aria-label="Earlier months">‹</button><span x-show="mi !== 0"></span>
                                            <span x-text="m.name"></span>
                                            <button type="button" x-show="mi === 1" @click="shift(1)" aria-label="Later months">›</button><span x-show="mi !== 1"></span>
                                        </div>
                                        <div class="wk"><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span><span>Su</span></div>
                                        <div class="days">
                                            <template x-for="d in m.days" :key="d.key">
                                                <span style="display:contents">
                                                    <template x-if="! d.iso"><span></span></template>
                                                    <template x-if="d.iso">
                                                        <button type="button" @click="pick(d.iso)" :class="cls(d.iso)" :aria-label="d.iso" x-text="d.n"></button>
                                                    </template>
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <div class="foot">
                                <span style="color:#55585d" x-text="hint()"></span>
                                <span style="margin-left:auto"></span>
                                <button type="button" @click="cal = false">Cancel</button>
                                <button type="button" class="apply" :disabled="! ready()" @click="apply()">Apply</button>
                            </div>
                        </div>
                        <div class="cl-pre">
                            @foreach ($date['presets'] as $pr)
                                @php $custom = in_array($pr['key'], ['day', 'range'], true); @endphp
                                @if ($custom)
                                    <button type="button" @class(['first-custom' => $pr['key'] === 'day']) :class="{ on: cal ? mode === '{{ $pr['key'] }}' : $wire.period === '{{ $pr['key'] }}' }" @click="custom('{{ $pr['key'] }}')">
                                        <span><span x-show="cal ? mode === '{{ $pr['key'] }}' : $wire.period === '{{ $pr['key'] }}'">✓&nbsp; </span>{{ $pr['label'] }}</span><span class="sub">{{ $pr['sub'] }}</span>
                                    </button>
                                @else
                                    @php $is = $pr['key'] === 'all' ? "(\$wire.period === 'all' || \$wire.period === '')" : "\$wire.period === '{$pr['key']}'"; @endphp
                                    <button type="button" :class="{ on: ! cal && {{ $is }} }" wire:click="setPeriod('{{ $pr['key'] }}')" @click="close()">
                                        <span><span x-show="! cal && {{ $is }}">✓&nbsp; </span>{{ $pr['label'] }}</span><span class="sub">{{ $pr['sub'] }}</span>
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </span>
                <span style="position:relative;margin-left:auto" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" class="cl-sort" @click="open = ! open" aria-haspopup="true" :aria-expanded="open">
                        Sort <b>{{ $sortWords }}</b>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#55585d" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="cl-pop" x-show="open" x-cloak>
                        <div class="cl-pre">
                            @foreach ($sorts as $k => $so)
                                <button type="button" @class(['on' => $so['on']]) wire:click="sortBy('{{ $k }}')" @click="open = false"><span>@if ($so['on'])✓&nbsp; @endif{{ $so['words'] }}</span></button>
                            @endforeach
                        </div>
                    </div>
                </span>
            </div>
            @if ($rows->isEmpty())
                <div class="muted">{{ $anyInvoice ? 'No invoice matches these filters.' : 'No invoice yet.' }}</div>
            @else
            <div class="cl-table">
                <table class="no-row-click">
                    <thead><tr>@foreach ($sorts as $k => $so)<th wire:click="sortBy('{{ $k }}')" title="Sort by {{ strtolower($so['label']) }}" aria-sort="{{ $so['on'] ? ($sortDesc ? 'descending' : 'ascending') : 'none' }}" @class(['s', 'on' => $so['on'], 'r' => $so['align'] === 'right'])>{{ $so['label'] }}<span class="ar">{{ $so['on'] ? ($sortDesc ? '↓' : '↑') : '↕' }}</span></th>@endforeach</tr></thead>
                    <tbody>
                    @foreach ($rows as $r)
                        @php
                            $i = $r['invoice']; [$label, $pill] = $r['stage']; $late = $label === 'Overdue';
                            $cl = $credit[$i->customer_id] ?? null; $live = $cl && in_array($r['stage'][2], ['OPEN', 'OVERDUE'], true);
                        @endphp
                        <tr wire:key="invoice-{{ $i->id }}" @class(['sel' => $inv && $inv->id === $i->id]) wire:click="select('{{ $i->id }}')">
                            <td class="nw">{{ $i->invoice_date->format('j M') }}</td>
                            <td class="nw"><code>{{ $i->invoice_number ?? 'Draft' }}</code>@if ($i->isCreditNote())<div class="cl-sub">credit note</div>@endif</td>
                            @php $custUrl = $i->customer ? ($i->customer->party_id ? route('parties.show', $i->customer->party_id) : route('customers.index', ['search' => $i->customer->name])) : null; @endphp
                            <td><div style="font-weight:500" @if ($custUrl) @contextmenu.prevent="openMenu($event, @js(route('invoices.show', $i)), @js($custUrl))" @endif>{{ $i->customer?->name }}</div><div class="cl-sub">{{ $i->customer_reference }}</div></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;white-space:nowrap"><span class="cl-pill {{ $pill }}">{{ $label }}</span><span class="nw">{{ $i->due_date && $i->status === 'ISSUED' && ! $i->isCreditNote() ? $i->due_date->format('j M') : '—' }}</span></div>
                                @if ($i->due_date && $i->status === 'ISSUED' && ! $i->isCreditNote())
                                    <div class="cl-sub" style="margin-top:3px;@if ($late) color:#b3352c @endif">{{ $label === 'Paid' ? 'Paid' : ($i->due_date->isPast() ? (int) $i->due_date->diffInDays(today()).' days overdue' : 'in '.(int) today()->diffInDays($i->due_date).' days') }}</div>
                                @endif
                            </td>
                            <td class="r nw">{{ $num($i->total) }}</td>
                            <td class="r nw" @if ($live) title="{{ $i->customer?->name }}: {{ \App\Support\Formats::money($cl['used'], 0) }} used of {{ $cl['limit'] !== null ? \App\Support\Formats::money($cl['limit'], 0).' credit limit ('.$cl['pct'].'%)' : 'no limit' }}" @endif>
                                <div style="font-weight:500;@if ($live && $cl['over']) color:#b3352c @endif">{{ $r['balance'] > 0 ? $num($r['balance']) : '—' }}</div>
                                @if ($live && ($cl['over'] || $cl['near'] || $cl['hold']))
                                    <div style="font-size:11.5px;font-weight:600;margin-top:3px;color:{{ $cl['over'] || $cl['hold'] ? '#b3352c' : '#94610a' }}">! {{ $cl['hold'] ? 'Credit hold' : ($cl['over'] ? 'Over credit limit' : $cl['pct'].'% of credit limit') }}</div>
                                @endif
                            </td>
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
            @php $pc = $credit[$inv->customer_id] ?? null; @endphp
            @if ($pc)
            <div><div class="cl-sec">Customer credit</div>
                @if ($pc['limit'] !== null)
                    @php $tone = $pc['over'] ? '#b3352c' : ($pc['near'] ? '#94610a' : '#1d7048'); @endphp
                    <div style="margin-top:8px;display:flex;justify-content:space-between;gap:12px"><span>{{ $pc['orders'] > 0 ? 'Used of limit' : 'Owed of limit' }}</span><span style="text-align:right">{{ \App\Support\Formats::money($pc['used'], 0) }} / {{ \App\Support\Formats::money($pc['limit'], 0) }}</span></div>
                    <div class="cl-meter"><div style="width:{{ min(max($pc['pct'], 0), 100) }}%;background:{{ $tone }}"></div></div>
                    <div style="margin-top:6px;font-size:12px;font-weight:500;color:{{ $pc['over'] || $pc['near'] ? $tone : '#55585d' }}">{{ $pc['pct'] }}% used{{ $pc['over'] ? ' · over by '.\App\Support\Formats::money($pc['used'] - $pc['limit'], 0) : ($pc['near'] ? ' · close to the limit' : '') }}</div>
                    @if ($pc['orders'] > 0)<div class="cl-sub" style="margin-top:2px">owed {{ \App\Support\Formats::money($pc['owed'], 0) }} · open orders {{ \App\Support\Formats::money($pc['orders'], 0) }}</div>@endif
                @endif
                @if ($pc['note'])<div style="margin-top:4px;font-size:12px;color:{{ $pc['hold'] ? '#b3352c' : '#55585d' }};line-height:1.45">{{ $pc['note'] }}</div>@endif
            </div>
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
