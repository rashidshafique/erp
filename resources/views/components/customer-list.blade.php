<?php

use App\Domains\Core\ModuleRegistry;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Finance\Services\CreditLimits;
use App\Domains\Finance\Services\Receivables;
use App\Domains\Sales\Models\SalesOrder;
use App\Http\Middleware\EnforceAccess;
use App\Models\Customer;
use App\Services\Codes\CodeGenerator;
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
        $this->selectedId = $this->selectedId === $id ? null : $id;
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
        $this->form = $this->blankForm();
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

    /** @return array<string, mixed> */
    public function with(): array
    {
        $search = trim($this->search);
        $showDispatch = app(ModuleRegistry::class)->isEnabled('dispatch');
        $showFinance = app(ModuleRegistry::class)->isEnabled('finance');

        $hold = fn ($q) => $q->where('credit_hold', true);
        $prospect = fn ($q) => $q->where('credit_hold', false)->where('is_prospect', true)->whereDoesntHave('salesOrders');
        $inactive = fn ($q) => $q->where('credit_hold', false)->whereHas('party', fn ($p) => $p->where('active', false));
        $active = fn ($q) => $q->active()->where('credit_hold', false)->where(fn ($w) => $w->where('is_prospect', false)->orWhereHas('salesOrders'));

        $owed = collect();
        if ($showFinance) {
            $owed = app(Receivables::class)->open()->groupBy('customer_id')->map(fn ($g) => [
                'balance' => round($g->sum(fn ($i) => $i->balance()), 2),
                'overdue' => round($g->filter(fn ($i) => $i->isOverdue())->sum(fn ($i) => $i->balance()), 2),
            ]);
        }

        $customers = Customer::query()->withCount(['salesOrders', 'ownedPatterns'])
            ->when($showDispatch, fn ($q) => $q->addSelect(['last_dispatched_at' => DB::table('dispatches')->selectRaw('max(dispatched_at)')->whereColumn('dispatches.customer_id', 'customers.id')])
                ->withCasts(['last_dispatched_at' => 'datetime']))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->matching($search)->orWhereHas('party', fn ($p) => $p
                ->where('short_name', 'ilike', "%{$search}%")->orWhere('contact_name', 'ilike', "%{$search}%")
                ->orWhere('phone', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"))))
            ->when($this->status === 'active', $active)
            ->when($this->status === 'prospect', $prospect)
            ->when($this->status === 'hold', $hold)
            ->when($this->status === 'inactive', $inactive)
            ->when($this->overdueOnly, fn ($q) => $q->whereIn('customers.id', $owed->filter(fn ($o) => $o['overdue'] > 0)->keys()))
            ->orderBy('code')
            ->paginate(20);

        return [
            'customers' => $customers,
            'showDispatch' => $showDispatch,
            'showFinance' => $showFinance,
            'owed' => $owed,
            'counts' => [
                '' => Customer::count(),
                'active' => $active(Customer::query())->count(),
                'prospect' => $prospect(Customer::query())->count(),
                'hold' => $hold(Customer::query())->count(),
                'inactive' => $inactive(Customer::query())->count(),
            ],
            'panel' => $this->selectedId ? $this->panel($this->selectedId, $showFinance) : null,
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
        $stage = $customer->stage();
        $open = $customer->salesOrders()->whereIn('status', SalesOrder::OPEN);
        $oldest = $showFinance ? app(Receivables::class)->open($customer->id)->filter(fn ($i) => $i->isOverdue())->sortBy('due_date')->first() : null;
        $enquiries = Enquiry::where('customer_id', $customer->id)->orderByDesc('received_on')->limit(3)->get();

        return [
            'customer' => $customer, 'stage' => $stage,
            'orders' => ['count' => (clone $open)->count(), 'value' => $showFinance ? app(CreditLimits::class)->openOrders($customer->id) : null],
            'enquiries' => $enquiries, 'firstOrder' => $customer->salesOrders()->orderBy('order_date')->first(),
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

@if ($saved)<div class="alert alert-success" role="status">{{ $saved }}</div>@endif

<div class="metrics">
    @foreach (['' => 'All', 'active' => 'Active', 'prospect' => 'Prospects', 'hold' => 'On hold', 'inactive' => 'Inactive'] as $key => $label)
        <a href="#" class="metric" wire:click.prevent="showSlice('{{ $key }}')" style="text-decoration:none;color:inherit"><div class="metric-label">{{ $label }}</div><div class="metric-value" @if (in_array($key, ['hold', 'inactive'], true) && $counts[$key]) style="color:var(--warning)" @endif>{{ $counts[$key] }}</div></a>
    @endforeach
</div>

<div class="panel">
    <div class="panel-body">
        <div class="filters" role="search">
            <div class="field">
                <label for="cust-search">Search code, name, contact, phone or email</label>
                <input id="cust-search" type="search" wire:model.live.debounce.300ms="search" autocomplete="off">
            </div>
            <div class="field">
                <label for="cust-status">Status</label>
                <select id="cust-status" wire:model.live="status">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="prospect">Prospects</option>
                    <option value="hold">On hold</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            @if ($showFinance)<div class="field"><label>&nbsp;</label><label><input type="checkbox" wire:model.live="overdueOnly"> Overdue only</label></div>@endif
            <button class="btn" type="button" wire:click="resetFilters">Reset</button>
            <span class="muted small" wire:loading.delay>Updating…</span>
            <button class="btn btn-primary" type="button" wire:click="create" style="margin-left:auto">+ New customer</button>
        </div>
    </div>
</div>

<div style="display:grid;gap:16px;align-items:start;grid-template-columns:{{ $panel ? 'minmax(0,1fr) 340px' : 'minmax(0,1fr)' }}">
<div class="panel">
    <div class="panel-header">
        <h2>Customers</h2>
        <span class="muted small">{{ $customers->total() }} records</span>
    </div>

    @if ($customers->isEmpty())
        <div class="empty-state">
            <strong>No customers found</strong>
            <p>{{ $counts[''] ? 'Nothing matches these filters.' : 'Add the first customer before entering an order.' }}</p>
            <button class="btn btn-primary" type="button" wire:click="create">+ New customer</button>
        </div>
    @else
        <div class="table-wrap">
            {{-- Clicking a row opens its quick edit, so the app's generic "row opens its first link" is switched off here. --}}
            <table class="no-row-click">
                <thead><tr><th>Customer</th><th>Contact</th><th>Terms</th><th>Orders</th>@if ($showFinance)<th>Balance</th>@endif@if ($showDispatch)<th>Last dispatch</th>@endif<th>Patterns held</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach ($customers as $customer)
                    @php $stage = $customer->stage(); $o = $owed->get($customer->id); @endphp
                    <tr wire:key="customer-{{ $customer->id }}" class="row-link" @style(['background:var(--surface-sub)' => $selectedId === $customer->id]) title="Click to see {{ $customer->name }}" x-data x-on:click="if (! $event.target.closest('a, button, input, label') && ! String(window.getSelection())) $wire.select('{{ $customer->id }}')">
                        <td><strong style="font-size:15px">{{ $customer->name }}</strong><div class="muted small"><span class="mono">{{ $customer->code }}</span>@if ($customer->short_name) · {{ $customer->short_name }}@endif</div></td>
                        <td>{{ $customer->contact_name ?: '—' }}@if ($customer->phone || $customer->email)<div class="muted small">{{ collect([$customer->phone, $customer->email])->filter()->implode(' · ') }}</div>@endif</td>
                        <td>{{ $customer->payment_terms ?: '—' }}</td>
                        <td>{{ $customer->sales_orders_count }}</td>
                        @if ($showFinance)<td>@if ($o){{ \App\Support\Formats::number($o['balance'], 0) }}@if ($o['overdue'] > 0)<div class="small" style="color:var(--danger)">{{ \App\Support\Formats::number($o['overdue'], 0) }} overdue</div>@endif @else — @endif</td>@endif
                        @if ($showDispatch)<td>@if ($customer->last_dispatched_at)<a href="{{ route('dispatches.index', ['customer' => $customer->id]) }}">{{ $customer->last_dispatched_at->displayDate() }}</a><div class="muted small">{{ $customer->last_dispatched_at->diffForHumans() }}</div>@else — @endif</td>@endif
                        <td>@if ($customer->owned_patterns_count)<a href="{{ route('patterns.index', ['owner' => $customer->id]) }}">{{ $customer->owned_patterns_count }}</a>@else — @endif</td>
                        <td><span class="status {{ ['active' => 'status-completed', 'prospect' => 'status-in-progress', 'hold' => 'status-cancelled', 'inactive' => 'status-on-hold'][$stage] }}">{{ ['active' => 'Active', 'prospect' => 'Prospect', 'hold' => 'On hold', 'inactive' => 'Inactive'][$stage] }}</span></td>
                        <td style="white-space:nowrap"><button class="btn btn-small" type="button" wire:click="edit('{{ $customer->id }}')">Quick edit</button> <a href="{{ route('customer-requirements.edit', $customer) }}">Requirements →</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <div class="muted small">Showing {{ $customers->firstItem() }}–{{ $customers->lastItem() }} of {{ $customers->total() }}</div>
            <div class="pagination-links">{{ $customers->links() }}</div>
        </div>
    @endif
</div>

@if ($panel)
@php
    $c = $panel['customer']; $stage = $panel['stage']; $money = fn ($v) => \App\Support\Formats::number((float) $v, 0);
    $row = fn ($l, $v) => '<div style="display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid var(--border)"><span class="muted">'.e($l).'</span><span style="text-align:right">'.$v.'</span></div>';
@endphp
<aside class="panel" wire:key="customer-panel-{{ $c->id }}">
    <div class="panel-header"><div><h2>{{ $c->name }}</h2><span class="muted small">{{ $c->code }}@if ($c->short_name) · {{ $c->short_name }}@endif</span></div>
        <span class="status {{ ['active' => 'status-completed', 'prospect' => 'status-in-progress', 'hold' => 'status-cancelled', 'inactive' => 'status-on-hold'][$stage] }}">{{ ['active' => 'Active', 'prospect' => 'Prospect', 'hold' => 'On hold', 'inactive' => 'Inactive'][$stage] }}</span></div>
    <div class="panel-body">
        @if ($stage === 'hold')
            <div class="alert alert-error">Credit hold{{ $c->credit_hold_reason ? ': '.$c->credit_hold_reason : '' }}@if ($panel['oldest']) <br>{{ $money($panel['oldest']->balance()) }} overdue {{ $panel['oldest']->due_date->diffInDays(today()) }} days on <a href="{{ route('invoices.show', $panel['oldest']) }}">{{ $panel['oldest']->invoice_number }}</a>.@endif</div>
            <strong class="small">Blocked while on hold</strong>
            {!! $row('New sales orders', '<span style="color:var(--danger)">Blocked</span>') !!}
            {!! $row('Dispatch notes', '<span style="color:var(--danger)">Blocked</span>') !!}
            {!! $row('Quotes', 'Allowed') !!}
            {!! $row('Work in progress', 'Allowed to finish') !!}
            <p class="muted small">Released when the balance is paid, or by accounts or the owner.</p>
        @elseif ($stage === 'prospect')
            <strong class="small">Path to serial supply</strong>
            @php $enq = $panel['enquiries']->first(); @endphp
            {!! $row('Enquiry received', $enq ? '<a href="'.route('enquiries.show', $enq).'">'.e($enq->code).'</a> · '.e($enq->received_on?->displayShortDate()) : '<span class="muted">None yet</span>') !!}
            {!! $row('First serial order', $panel['firstOrder'] ? e($panel['firstOrder']->order_number) : '<span class="muted">Converts to Active</span>') !!}
            <strong class="small" style="display:block;margin-top:12px">Missing before first order</strong>
            @forelse ($panel['missing'] as [$what, $state]){!! $row($what, '<span style="color:var(--warning)">'.e($state).'</span>') !!}@empty<p class="muted small">Nothing missing.</p>@endforelse
        @else
            {!! $row('Main contact', e($c->contact_name ?: '—')) !!}
            {!! $row('Credit limit', $c->credit_limit !== null ? $money($c->credit_limit) : 'Not set') !!}
            {!! $row('Terms', e($c->payment_terms ?: '—')) !!}
            <strong class="small" style="display:block;margin-top:12px">Open now</strong>
            {!! $row('Sales orders', $panel['orders']['count'].($panel['orders']['value'] !== null ? ' · '.$money($panel['orders']['value']) : '')) !!}
            {!! $row('Patterns held', $c->owned_patterns_count ? '<a href="'.route('patterns.index', ['owner' => $c->id]).'">'.$c->owned_patterns_count.'</a>' : '—') !!}
        @endif
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
            @if ($c->party_id)<a class="btn" href="{{ route('parties.show', $c->party_id) }}">Open record</a>@endif
            @if ($stage === 'prospect')<button class="btn btn-primary" type="button" wire:click="markActive('{{ $c->id }}')">Mark as active</button>@endif
            @if ($stage === 'hold' && $panel['canRelease'])<button class="btn btn-primary" type="button" wire:click="releaseHold('{{ $c->id }}')" wire:confirm="Release the credit hold on {{ $c->name }}?">Release hold</button>@endif
            <button class="btn" type="button" wire:click="select('{{ $c->id }}')">Close</button>
        </div>
    </div>
</aside>
@endif
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
