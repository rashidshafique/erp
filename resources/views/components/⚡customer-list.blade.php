<?php

use App\Models\Customer;
use App\Services\Codes\CodeGenerator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The customer list: search and the status filter apply as they are typed, the tiles filter the list when clicked, and a
 * customer is added or quick-edited in a dialog. The search stays under `search` in the address, the name the page has
 * always used, so existing links keep working.
 */
new class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

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
        return ['code' => '', 'name' => '', 'short_name' => '', 'contact_name' => '', 'phone' => '', 'email' => '', 'tax_number' => '', 'payment_terms' => '', 'billing_address' => '', 'shipping_address' => '', 'active' => true];
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

    public function resetFilters(): void
    {
        $this->reset('search', 'status');
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
        $rules = collect(Customer::rules($customer) + ['active' => ['boolean']])->mapWithKeys(fn ($rule, $field) => ["form.{$field}" => $rule])->all();
        $data = $this->validate($rules, [], ['form.name' => 'name', 'form.code' => 'code', 'form.email' => 'email'])['form'];

        foreach (['code', 'short_name', 'contact_name', 'phone', 'email', 'tax_number', 'payment_terms', 'billing_address', 'shipping_address'] as $optional) {
            $data[$optional] = ($data[$optional] ?? '') === '' ? null : $data[$optional];
        }
        if ($data['code'] === null) {
            unset($data['code']);
        }
        $data['active'] = (bool) $data['active'];

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
        $inactive = fn ($q) => $q->whereHas('party', fn ($p) => $p->where('active', false));

        $customers = Customer::query()->withCount(['salesOrders', 'ownedPatterns'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->matching($search)->orWhereHas('party', fn ($p) => $p
                ->where('short_name', 'ilike', "%{$search}%")->orWhere('contact_name', 'ilike', "%{$search}%")
                ->orWhere('phone', 'ilike', "%{$search}%")->orWhere('email', 'ilike', "%{$search}%"))))
            ->when($this->status === 'active', fn ($q) => $q->active())
            ->when($this->status === 'inactive', $inactive)
            ->orderBy('code')
            ->paginate(20);

        return [
            'customers' => $customers,
            'counts' => [
                '' => Customer::count(),
                'active' => Customer::active()->count(),
                'inactive' => $inactive(Customer::query())->count(),
            ],
            'nextCode' => app(CodeGenerator::class)->preview('customer'),
        ];
    }
};
?>

<div>
@include('partials.quick-dialog-assets')

@if ($saved)<div class="alert alert-success" role="status">{{ $saved }}</div>@endif

<div class="metrics">
    @foreach (['' => 'All customers', 'active' => 'Active', 'inactive' => 'Inactive'] as $key => $label)
        <a href="#" class="metric" wire:click.prevent="showSlice('{{ $key }}')" style="text-decoration:none;color:inherit"><div class="metric-label">{{ $label }}</div><div class="metric-value" @if ($key === 'inactive' && $counts[$key]) style="color:var(--warning)" @endif>{{ $counts[$key] }}</div></a>
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
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <button class="btn" type="button" wire:click="resetFilters">Reset</button>
            <span class="muted small" wire:loading.delay>Updating…</span>
            <button class="btn btn-primary" type="button" wire:click="create" style="margin-left:auto">+ New customer</button>
        </div>
    </div>
</div>

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
            <table>
                <thead><tr><th>Customer</th><th>Contact</th><th>Payment terms</th><th>Orders</th><th>Patterns held</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach ($customers as $customer)
                    <tr wire:key="customer-{{ $customer->id }}">
                        <td><strong style="font-size:15px">{{ $customer->name }}</strong><div class="muted small"><span class="mono">{{ $customer->code }}</span>@if ($customer->short_name) · {{ $customer->short_name }}@endif</div></td>
                        <td>{{ $customer->contact_name ?: '—' }}@if ($customer->phone || $customer->email)<div class="muted small">{{ collect([$customer->phone, $customer->email])->filter()->implode(' · ') }}</div>@endif</td>
                        <td>{{ $customer->payment_terms ?: '—' }}</td>
                        <td>{{ $customer->sales_orders_count }}</td>
                        <td>@if ($customer->owned_patterns_count)<a href="{{ route('patterns.index', ['owner' => $customer->id]) }}">{{ $customer->owned_patterns_count }}</a>@else — @endif</td>
                        <td><span class="status {{ $customer->active ? 'status-completed' : 'status-cancelled' }}">{{ $customer->active ? 'Active' : 'Inactive' }}</span></td>
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
                @if ($editingId)<div class="field field-full"><label><input type="checkbox" wire:model="form.active"> Active</label><div class="help">Inactive customers are left out of the order form's customer list.</div></div>@endif
            </div>
        </div>
        <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">{{ $editingId ? 'Save changes' : 'Add customer' }}</button></div>
    </form>
</dialog>
@endif
</div>
