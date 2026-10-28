<?php

use App\Domains\Core\ModuleRegistry;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\Quotation;
use App\Domains\Sales\Models\SalesOrderLine;
use Illuminate\Database\Eloquent\Builder;
use App\Domains\Finance\Services\CreditLimits;
use App\Domains\Finance\Services\Receivables;
use App\Domains\Sales\Models\SalesOrder;
use App\Http\Middleware\EnforceAccess;
use App\Models\Customer;
use App\Services\Codes\CodeGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The customer list: search and the status filter apply as they are typed, the tiles filter the list when clicked, and a
 * customer is added or quick-edited in a dialog. The search stays under `search` in the address, the name the page has
 * always used, so existing links keep working. Clicking a row opens a side panel for the customer (design 13a to 13c): terms and
 * what is open for an active one, the path to a first order for a prospect, and why and what is blocked for one on hold.
 */
new class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: false)]
    public bool $overdueOnly = false;

    public ?string $selectedId = null;

    public bool $formOpen = false;

    public ?string $editingId = null;

    public ?string $saved = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->form = $this->blankForm();
    }

    /** @return array<string, mixed> */
    private function blankForm(): array
    {
        return ['code' => '', 'name' => '', 'short_name' => '', 'contact_name' => '', 'phone' => '', 'email' => '', 'tax_number' => '', 'payment_terms' => '', 'billing_address' => '', 'shipping_address' => '', 'active' => true, 'is_prospect' => false];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /** Shows one slice of the list: active or inactive customers, or all of them. */
    public function showSlice(string $status = ''): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function updatedOverdueOnly(): void
    {
        $this->resetPage();
    }

    public function select(string $id): void
    {
        $this->selectedId = $id;
    }

    /** A prospect becomes an active customer by hand; the first sales order does it too. */
    public function markActive(string $id): void
    {
        abort_unless(EnforceAccess::allows(auth()->user(), 'customers.index'), 403);
        Customer::findOrFail($id)->update(['is_prospect' => false]);
    }

    /** Lifts a credit hold. Placing one and the credit terms stay on the partner page. */
    public function releaseHold(string $id): void
    {
        abort_unless(EnforceAccess::allows(auth()->user(), 'finance.credit.update'), 403);
        Customer::findOrFail($id)->forceFill(['credit_hold' => false, 'credit_hold_reason' => null])->save();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'overdueOnly');
        $this->resetPage();
    }

    public function paginationView(): string
    {
        return 'livewire.pagination';
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['is_prospect' => true] + $this->blankForm();
        $this->saved = null;
        $this->formOpen = true;
    }

    public function edit(string $id): void
    {
        $customer = Customer::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $customer->id;
        $this->form = array_map(fn ($value) => $value ?? '', $customer->only(array_keys($this->blankForm())));
        $this->form['active'] = (bool) $customer->active;
        $this->saved = null;
        $this->formOpen = true;
    }

    public function closeForm(): void
    {
        $this->formOpen = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $customer = $this->editingId ? Customer::findOrFail($this->editingId) : null;
        $this->form['code'] = strtoupper(trim((string) $this->form['code']));
        $rules = collect(Customer::rules($customer) + ['active' => ['boolean'], 'is_prospect' => ['boolean']])->mapWithKeys(fn ($rule, $field) => ["form.{$field}" => $rule])->all();
        $data = $this->validate($rules, [], ['form.name' => 'name', 'form.code' => 'code', 'form.email' => 'email'])['form'];

        foreach (['code', 'short_name', 'contact_name', 'phone', 'email', 'tax_number', 'payment_terms', 'billing_address', 'shipping_address'] as $optional) {
            $data[$optional] = ($data[$optional] ?? '') === '' ? null : $data[$optional];
        }
        if ($data['code'] === null) {
            unset($data['code']);
        }
        $data['active'] = (bool) $data['active'];
        $data['is_prospect'] = (bool) $data['is_prospect'];

        if ($customer) {
            $customer->update($data);
            $this->saved = "{$customer->code} saved.";
        } else {
            $customer = Customer::create($data);
            $this->saved = "{$customer->code} added.";
        }

        $this->closeForm();
    }

    /** Every customer's open balance and the part of it that is overdue, from the receivables. */
    private function owedByCustomer(): Collection
    {
        if (! app(ModuleRegistry::class)->isEnabled('finance')) {
            return collect();
        }

        return app(Receivables::class)->open()->groupBy('customer_id')->map(fn ($g) => [
            'balance' => round($g->sum(fn ($i) => $i->balance()), 2),
            'overdue' => round($g->filter(fn ($i) => $i->isOverdue())->sum(fn ($i) => $i->balance()), 2),
        ]);
    }

    /** The customers the search, the tab and the overdue chip leave in. */
    private function filtered(Collection $owed): Builder
    {
        $search = trim($this->search);

        return Customer::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->matching($search)->orWhereHas('party', fn ($p) => $p
                ->where('short_name', 'ilike', "%{$search}%")->orWhere('contact_name', 'ilike', "%{$search}%")->orWhere('billing_address', 'ilike', "%{$search}%")
                ->orWhere('phone', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"))))
            ->when($this->status === 'active', fn ($q) => $this->inStage($q, 'active'))
            ->when($this->status === 'prospect', fn ($q) => $this->inStage($q, 'prospect'))
            ->when($this->status === 'hold', fn ($q) => $this->inStage($q, 'hold'))
            ->when($this->overdueOnly, fn ($q) => $q->whereIn('customers.id', $owed->filter(fn ($o) => $o['overdue'] > 0)->keys()))
            ->orderBy('code');
    }

    private function inStage(Builder $q, string $stage): Builder
    {
        return match ($stage) {
            'hold' => $q->where('credit_hold', true),
            'prospect' => $q->where('credit_hold', false)->where('is_prospect', true)->whereDoesntHave('salesOrders'),
            default => $q->active()->where('credit_hold', false)->where(fn ($w) => $w->where('is_prospect', false)->orWhereHas('salesOrders')),
        };
    }

    /** The list as a spreadsheet file, whatever the search and tab show. */
    public function export()
    {
        $owed = $this->owedByCustomer();
        $rows = $this->filtered($owed)->get();

        return response()->streamDownload(function () use ($rows, $owed) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Code', 'Customer', 'Contact', 'Terms', 'Balance', 'Overdue', 'Status']);
            foreach ($rows as $c) {
                fputcsv($out, [$c->code, $c->name, $c->contact_name, $c->payment_terms, $owed->get($c->id)['balance'] ?? 0, $owed->get($c->id)['overdue'] ?? 0, $c->stage()]);
            }
            fclose($out);
        }, 'customers.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $owed = $this->owedByCustomer();
        $customers = $this->filtered($owed)->withCount('salesOrders')->paginate(20);
        $ids = $customers->getCollection()->pluck('id');
        $parts = SalesOrderLine::query()->join('sales_orders as so', 'so.id', '=', 'sales_order_lines.sales_order_id')->whereIn('so.customer_id', $ids)
            ->groupBy('so.customer_id')->selectRaw('so.customer_id, count(distinct sales_order_lines.product_id) as n')->pluck('n', 'customer_id');
        $limits = $owed->isNotEmpty() || app(ModuleRegistry::class)->isEnabled('finance') ? app(CreditLimits::class) : null;
        $selected = $this->selectedId ?? $customers->first()?->id;

        return [
            'customers' => $customers, 'owed' => $owed, 'parts' => $parts,
            'openValue' => $customers->getCollection()->mapWithKeys(fn ($c) => [$c->id => $limits?->openOrders($c->id)]),
            'showFinance' => $limits !== null,
            'counts' => [
                '' => Customer::count(),
                'active' => $this->inStage(Customer::query(), 'active')->count(),
                'prospect' => $this->inStage(Customer::query(), 'prospect')->count(),
                'hold' => $this->inStage(Customer::query(), 'hold')->count(),
            ],
            'panel' => $selected ? $this->panel($selected, $limits !== null) : null,
            'nextCode' => app(CodeGenerator::class)->preview('customer'),
        ];
    }

    /**
     * What the side panel shows for one customer, by where the account stands.
     *
     * @return array<string, mixed>|null
     */
    private function panel(string $id, bool $showFinance): ?array
    {
        $customer = Customer::withCount('ownedPatterns')->find($id);
        if (! $customer) {
            return null;
        }
        $open = $customer->salesOrders()->whereIn('status', SalesOrder::OPEN);
        $oldest = $showFinance ? app(Receivables::class)->open($customer->id)->filter(fn ($i) => $i->isOverdue())->sortBy('due_date')->first() : null;
        $enquiry = Enquiry::where('customer_id', $customer->id)->orderBy('received_on')->first();
        $quote = $enquiry ? Quotation::whereIn('enquiry_id', Enquiry::where('customer_id', $customer->id)->pluck('id'))->where('status', 'ACCEPTED')->orderBy('decided_at')->first() : null;

        return [
            'customer' => $customer, 'stage' => $customer->stage(),
            'orders' => ['count' => (clone $open)->count(), 'value' => $showFinance ? app(CreditLimits::class)->openOrders($customer->id) : null],
            'enquiry' => $enquiry, 'quote' => $quote, 'firstOrder' => $customer->salesOrders()->orderBy('order_date')->first(),
            'oldest' => $oldest, 'canRelease' => EnforceAccess::allows(auth()->user(), 'finance.credit.update'),
            'missing' => collect([
                $customer->credit_limit === null ? ['Credit limit', 'Not set'] : null,
                blank($customer->tax_number) ? ['Tax number', 'Not recorded'] : null,
                blank($customer->payment_terms) ? ['Payment terms', 'Not set'] : null,
                blank($customer->contact_name) ? ['Main contact', 'Not recorded'] : null,
            ])->filter()->values()->all(),
        ];
    }
};
?>

<div>
@include('partials.quick-dialog-assets')
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
.cl-chip { padding: 6px 10px; border: 1px solid #d9dbde; border-radius: 6px; background: #fff; font-size: 13px; cursor: pointer; }
.cl-chip.on { background: #f0f1f3; }
.cl-table { margin: 0 -12px; overflow-x: auto; }
.cl-table td, .cl-table th { text-align: left; } .cl-table .r { text-align: right; }
.cl-table tbody tr { cursor: pointer; } .cl-table tbody tr.sel { background: #f5f8fe; }
.cl-sub { font-size: 12px; color: #6b6e73; }
.cl-pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; display: inline-block; }
.cl-ok { background: #e7f4ec; color: #1d7048; } .cl-warn { background: #fbf0dc; color: #94610a; } .cl-bad { background: #fbe7e5; color: #b3352c; } .cl-info { background: #eaf0fd; color: #2f5fd0; }
.cl-side { border-left: 1px solid #e6e7e9; padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 18px; }
.cl-side h3 { margin: 0; font-size: 16px; font-weight: 600; }
.cl-side .lab { font-size: 12px; color: #6b6e73; }
.cl-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.cl-sec { font-size: 12px; font-weight: 600; color: #6b6e73; text-transform: uppercase; letter-spacing: .06em; }
.cl-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px solid #e6e7e9; }
.cl-row:last-child { border-bottom: 0; } .cl-row span + span { color: #6b6e73; text-align: right; }
.cl-step { display: grid; grid-template-columns: 18px minmax(0, 1fr) auto; gap: 10px; align-items: start; padding: 8px 0; }
.cl-dot { width: 10px; height: 10px; margin-top: 4px; border-radius: 50%; }
.cl-step small { font-size: 12px; color: #6b6e73; display: block; }
.cl-alert { padding: 12px 14px; border-radius: 8px; background: #fbe7e5; color: #b3352c; display: flex; flex-direction: column; gap: 4px; line-height: 1.5; }
@media (max-width: 1000px) { .cl-grid { grid-template-columns: minmax(0, 1fr); } .cl-side { border-left: 0; border-top: 1px solid #e6e7e9; } }
</style>

@if ($saved)<div class="alert alert-success" role="status">{{ $saved }}</div>@endif

@php
    $money = fn ($v) => '$'.\App\Support\Formats::number((float) $v, 0);
    $stages = ['active' => ['Active', 'cl-ok'], 'prospect' => ['Prospect', 'cl-warn'], 'hold' => ['On hold', 'cl-bad'], 'inactive' => ['Inactive', 'cl-warn']];
    $c = $panel['customer'] ?? null; $stage = $panel['stage'] ?? null;
    $sub = fn ($x) => collect([$x->short_name, $x->billing_address ? trim(strtok($x->billing_address, "\n")) : null])->filter()->implode(' · ');
@endphp
<div class="cl-card">
    <div class="cl-bar">
        <div style="display:flex;align-items:center;gap:16px">
            <strong style="font-weight:600">Customers</strong>
            <nav class="cl-tabs">
                @foreach (['' => 'All', 'active' => 'Active', 'prospect' => 'Prospects', 'hold' => 'On hold'] as $key => $label)
                    <a href="#" wire:click.prevent="showSlice('{{ $key }}')" @class(['on' => $status === $key])>{{ $label }} {{ $counts[$key] }}</a>
                @endforeach
            </nav>
        </div>
        <div style="display:flex;gap:8px">
            <button class="cl-btn" type="button" wire:click="export">Export</button>
            <button class="cl-btn primary" type="button" wire:click="create">New customer</button>
        </div>
    </div>
    <div class="cl-grid">
        <div class="cl-main">
            <div class="cl-filters">
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, city or contact" autocomplete="off" aria-label="Search name, city or contact">
                @if ($showFinance)<button type="button" wire:click="$toggle('overdueOnly')" @class(['cl-chip', 'on' => $overdueOnly])>Overdue only</button>@endif
            </div>
            @if ($customers->isEmpty())
                <div class="muted">{{ $counts[''] ? 'No customer matches.' : 'Add the first customer before entering an order.' }}</div>
            @else
            <div class="cl-table">
                <table class="no-row-click">
                    <thead><tr><th>Customer</th><th>Supply</th><th class="r">Parts</th><th class="r">Open orders</th><th class="r">Developments</th><th class="r">PPM 12 mo</th><th>Terms</th><th class="r">Balance</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($customers as $row)
                        @php $st = $row->stage(); $o = $owed->get($row->id); @endphp
                        <tr wire:key="customer-{{ $row->id }}" @class(['sel' => $c && $c->id === $row->id]) wire:click="select('{{ $row->id }}')">
                            <td><div style="font-weight:500">{{ $row->name }}</div><div class="cl-sub">{{ $sub($row) ?: $row->code }}</div></td>
                            <td>{{ $row->sales_orders_count ? 'Serial' : '—' }}</td>
                            <td class="r">{{ $parts[$row->id] ?? 0 }}</td>
                            <td class="r">{{ $openValue[$row->id] !== null ? $money($openValue[$row->id]) : '—' }}</td>
                            <td class="r">—</td>
                            <td class="r">—</td>
                            <td>{{ $row->payment_terms ?: '—' }}</td>
                            <td class="r">@if ($showFinance){{ $money($o['balance'] ?? 0) }}@if (($o['overdue'] ?? 0) > 0) · <span class="cl-pill cl-bad">{{ $money($o['overdue']) }} overdue</span>@endif @else — @endif</td>
                            <td><span class="cl-pill {{ $stages[$st][1] }}">{{ $stages[$st][0] }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination"><div class="pagination-links">{{ $customers->links() }}</div></div>
            @endif
        </div>

        @if ($c)
        <aside class="cl-side" wire:key="customer-panel-{{ $c->id }}">
            <div>
                <div style="display:flex;align-items:center;gap:8px"><h3>{{ $c->name }}</h3>@if ($stage !== 'active')<span class="cl-pill {{ $stages[$stage][1] }}">{{ $stages[$stage][0] }}</span>@endif</div>
                <div style="color:#6b6e73">{{ $sub($c) ?: $c->code }}</div>
            </div>

            @if ($stage === 'hold')
                <div class="cl-alert"><span style="font-weight:600">Credit hold</span>
                    <span style="font-size:12.5px">{{ $c->credit_hold_reason ?: 'No reason recorded.' }}@if ($panel['oldest']) {{ $money($panel['oldest']->balance()) }} overdue {{ (int) $panel['oldest']->due_date->diffInDays(today()) }} days on <code>{{ $panel['oldest']->invoice_number }}</code>.@endif</span></div>
                <div><div class="cl-sec">Blocked while on hold</div><div style="margin-top:8px">
                    <div class="cl-row"><span>New sales orders</span><span>Blocked</span></div>
                    <div class="cl-row"><span>Dispatch notes</span><span>Blocked</span></div>
                    <div class="cl-row"><span>Quotes</span><span>Allowed</span></div>
                    <div class="cl-row"><span>Work in progress</span><span>Allowed to finish</span></div></div></div>
                <div style="font-size:12.5px;color:#6b6e73;line-height:1.5">Released when the balance is paid, or by accounts or the owner.</div>
            @else
                <div class="cl-two">
                    <div><div class="lab">Main contact</div><div>{{ $c->contact_name ?: '—' }}</div></div>
                    <div><div class="lab">Phone</div><div>{{ $c->phone ?: '—' }}</div></div>
                    <div><div class="lab">Credit limit</div><div>{!! $c->credit_limit !== null ? e($money($c->credit_limit)) : '<span style="color:#6b6e73">Not set</span>' !!}</div></div>
                    <div><div class="lab">Terms</div><div>{{ $c->payment_terms ?: '—' }}</div></div>
                </div>
                @if ($stage === 'prospect')
                    @php
                        $steps = [
                            ['Enquiry received', $panel['enquiry'] ? '<code>'.e($panel['enquiry']->code).'</code> '.e($panel['enquiry']->title) : null, $panel['enquiry']?->received_on?->format('d M')],
                            ['Quote accepted', $panel['quote'] ? '<code>'.e($panel['quote']->code).'</code>' : null, $panel['quote']?->decided_at?->format('d M')],
                            ['First serial order', $panel['firstOrder'] ? '<code>'.e($panel['firstOrder']->order_number).'</code>' : 'Converts to Active', $panel['firstOrder']?->order_date?->format('d M')],
                        ];
                        $current = collect($steps)->search(fn ($x) => $x[2] === null);
                    @endphp
                    <div><div class="cl-sec">Path to serial supply</div><div style="margin-top:4px">
                        @foreach ($steps as $i => [$what, $detail, $when])
                            <div class="cl-step">
                                @if ($when !== null)<span class="cl-dot" style="background:var(--accent)"></span>
                                @elseif ($i === $current)<span class="cl-dot" style="border:2px solid var(--accent);width:6px;height:6px;background:#fff"></span>
                                @else<span class="cl-dot" style="border:1px solid #c4c7cc"></span>@endif
                                <div><div @style(['font-weight:600' => $i === $current])>{{ $what }}</div>@if ($detail)<small>{!! $detail !!}</small>@endif</div>
                                <span class="cl-sub">{{ $when }}</span>
                            </div>
                        @endforeach
                    </div></div>
                    <div><div class="cl-sec">Missing before first order</div><div style="margin-top:8px">
                        @forelse ($panel['missing'] as [$what, $state])<div class="cl-row"><span>{{ $what }}</span><span>{{ $state }}</span></div>@empty<div class="cl-sub">Nothing missing.</div>@endforelse
                    </div></div>
                @else
                    <div><div class="cl-sec">Open now</div><div style="margin-top:8px">
                        <div class="cl-row"><span>Sales orders</span><span>{{ $panel['orders']['count'] }}{{ $panel['orders']['value'] !== null ? ' · '.$money($panel['orders']['value']) : '' }}</span></div>
                        <div class="cl-row"><span>Patterns held</span><span>@if ($c->owned_patterns_count){{ $c->owned_patterns_count }} in register <a href="{{ route('patterns.index', ['owner' => $c->id]) }}">view</a>@else — @endif</span></div>
                    </div></div>
                @endif
            @endif

            <div style="display:flex;gap:8px;flex-wrap:wrap">
                @if ($c->party_id)<a class="cl-btn" href="{{ route('parties.show', $c->party_id) }}">Open record</a>@endif
                <a class="cl-btn" href="{{ route('customer-requirements.edit', $c) }}">Requirements</a>
                @if ($stage === 'prospect')<button class="cl-btn primary" type="button" wire:click="markActive('{{ $c->id }}')">Mark as active</button>@endif
                @if ($stage === 'hold' && $panel['canRelease'])<button class="cl-btn primary" type="button" wire:click="releaseHold('{{ $c->id }}')" wire:confirm="Release the credit hold on {{ $c->name }}?">Release hold</button>@endif
            </div>
        </aside>
        @endif
    </div>
</div>

@if ($formOpen)
<dialog class="qd" aria-labelledby="customer-dialog-title" x-data x-init="$el.showModal()" x-on:close="$wire.closeForm()" wire:key="customer-dialog">
    <form wire:submit="save">
        <div class="qd-head"><h2 id="customer-dialog-title">{{ $editingId ? 'Quick edit customer' : 'New customer' }}</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
        <div class="qd-body">
            @if ($errors->any())<div class="alert alert-error qd-errors" role="alert">@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
            <div class="qd-fields">
                <div class="field"><label for="c-name">Name <span class="required">*</span></label><input id="c-name" wire:model="form.name" maxlength="200" autocomplete="off" @error('form.name') aria-invalid="true" @enderror></div>
                <div class="field"><label for="c-code">Code</label><input id="c-code" wire:model="form.code" maxlength="40" autocomplete="off" style="text-transform:uppercase" placeholder="{{ $editingId ? '' : 'Blank: '.$nextCode }}" @error('form.code') aria-invalid="true" @enderror></div>
                <div class="field"><label for="c-short">Short name</label><input id="c-short" wire:model="form.short_name" maxlength="60"></div>
                <div class="field"><label for="c-contact">Contact person</label><input id="c-contact" wire:model="form.contact_name" maxlength="120"></div>
                <div class="field"><label for="c-phone">Phone</label><input id="c-phone" wire:model="form.phone" maxlength="40"></div>
                <div class="field"><label for="c-email">Email</label><input id="c-email" type="email" wire:model="form.email" maxlength="160" @error('form.email') aria-invalid="true" @enderror></div>
                <div class="field"><label for="c-tax">Tax number</label><input id="c-tax" wire:model="form.tax_number" maxlength="60"></div>
                <div class="field"><label for="c-terms">Payment terms</label><input id="c-terms" wire:model="form.payment_terms" maxlength="100" placeholder="e.g. 30 days"></div>
                <div class="field field-full"><label for="c-billing">Billing address</label><textarea id="c-billing" rows="2" wire:model="form.billing_address"></textarea></div>
                <div class="field field-full"><label for="c-shipping">Shipping address</label><textarea id="c-shipping" rows="2" wire:model="form.shipping_address"></textarea></div>
                <div class="field field-full"><label><input type="checkbox" wire:model="form.is_prospect"> Prospect</label><div class="help">No serial supply yet. Stops being a prospect when marked active or on its first sales order.</div></div>
                @if ($editingId)<div class="field field-full"><label><input type="checkbox" wire:model="form.active"> Active</label><div class="help">Inactive customers are left out of the order form's customer list.</div></div>@endif
            </div>
        </div>
        <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">{{ $editingId ? 'Save changes' : 'Add customer' }}</button></div>
    </form>
</dialog>
@endif
</div>
