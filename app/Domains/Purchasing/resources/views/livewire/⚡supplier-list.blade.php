<?php

use App\Domains\Purchasing\Models\Supplier;
use App\Services\Codes\CodeGenerator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The approved supplier list: search and the filters apply as they are typed, the status tiles filter the list when
 * clicked, and a supplier is added or quick-edited in a dialog. The state is kept in the address under the same names the
 * page has always used (`q`, `status`, `state`), so existing links keep working.
 */
new class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $state = '';

    public bool $formOpen = false;

    public ?string $editingId = null;

    public ?string $saved = null;

    public ?string $savedId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->form = $this->blankForm();
    }

    /** @return array<string, mixed> */
    private function blankForm(): array
    {
        return ['code' => '', 'name' => '', 'supplies' => '', 'contact_name' => '', 'phone' => '', 'email' => '', 'billing_address' => '', 'review_interval_days' => 365, 'notes' => ''];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedState(): void
    {
        $this->resetPage();
    }

    /** Shows one slice of the list: a status, or the suppliers whose review is overdue. */
    public function showSlice(string $status = '', string $state = ''): void
    {
        $this->status = $status;
        $this->state = $state;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'state');
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
        $this->saved = $this->savedId = null;
        $this->formOpen = true;
    }

    public function edit(string $id): void
    {
        $supplier = Supplier::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $supplier->id;
        $this->form = array_map(fn ($value) => $value ?? '', $supplier->only(array_keys($this->blankForm())));
        $this->saved = $this->savedId = null;
        $this->formOpen = true;
    }

    public function closeForm(): void
    {
        $this->formOpen = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $supplier = $this->editingId ? Supplier::findOrFail($this->editingId) : null;
        $this->form['code'] = strtoupper(trim((string) $this->form['code']));
        $rules = collect(Supplier::rules($supplier))->mapWithKeys(fn ($rule, $field) => ["form.{$field}" => $rule])->all();
        $data = $this->validate($rules, [], ['form.name' => 'name', 'form.code' => 'code', 'form.review_interval_days' => 'review interval'])['form'];

        foreach (['code', 'supplies', 'contact_name', 'phone', 'email', 'billing_address', 'notes'] as $optional) {
            $data[$optional] = ($data[$optional] ?? '') === '' ? null : $data[$optional];
        }
        if ($data['code'] === null) {
            unset($data['code']);
        }

        if ($supplier) {
            $supplier->update($data);
            $this->saved = "{$supplier->code} saved.";
        } else {
            $supplier = Supplier::create($data + ['approval_status' => 'PENDING']);
            $this->saved = "{$supplier->code} added as pending. Record an evaluation to approve it.";
        }
        $this->savedId = $supplier->id;

        $this->closeForm();
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $overdue = fn ($q) => $q->whereNotIn('approval_status', Supplier::BLOCKED)->whereDate('next_review_on', '<', today());
        $search = trim($this->search);

        $suppliers = Supplier::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->matching($search)->orWhere('supplies', 'ilike', "%{$search}%")))
            ->when($this->status !== '', fn ($q) => $q->where('approval_status', $this->status))
            ->when($this->state === 'review', $overdue)
            ->orderByRaw("case approval_status when 'PENDING' then 0 when 'SUSPENDED' then 1 when 'CONDITIONAL' then 2 else 3 end")->orderedByName()
            ->paginate(20);

        return [
            'suppliers' => $suppliers,
            'statuses' => Supplier::STATUSES,
            'total' => Supplier::count(),
            'counts' => [
                'PENDING' => Supplier::where('approval_status', 'PENDING')->count(),
                'review' => $overdue(Supplier::query())->count(),
                'CONDITIONAL' => Supplier::where('approval_status', 'CONDITIONAL')->count(),
                'SUSPENDED' => Supplier::whereIn('approval_status', Supplier::BLOCKED)->count(),
            ],
            'nextCode' => app(CodeGenerator::class)->preview('supplier'),
        ];
    }
};
?>

<div>
@include('partials.quick-dialog-assets')

@if ($saved)<div class="alert alert-success" role="status">{{ $saved }} @if ($savedId)<a href="{{ route('suppliers.show', $savedId) }}">Open supplier →</a>@endif</div>@endif

<div class="metrics">
    <a href="#" class="metric" wire:click.prevent="showSlice()" style="text-decoration:none;color:inherit"><div class="metric-label">All suppliers</div><div class="metric-value">{{ $total }}</div></a>
    @foreach (['PENDING' => ['Pending approval', ['PENDING', '']], 'review' => ['Review overdue', ['', 'review']], 'CONDITIONAL' => ['Conditional', ['CONDITIONAL', '']], 'SUSPENDED' => ['Suspended / disqualified', ['SUSPENDED', '']]] as $key => [$label, $slice])
        <a href="#" class="metric" wire:click.prevent="showSlice('{{ $slice[0] }}', '{{ $slice[1] }}')" style="text-decoration:none;color:inherit"><div class="metric-label">{{ $label }}</div><div class="metric-value" @if ($counts[$key] && in_array($key, ['review', 'SUSPENDED'])) style="color:var(--danger)" @elseif ($counts[$key]) style="color:var(--warning)" @endif>{{ $counts[$key] }}</div></a>
    @endforeach
</div>

<div class="panel">
    <div class="panel-body">
        <div class="filters" role="search">
            <div class="field">
                <label for="sup-search">Search code, name or what they supply</label>
                <input id="sup-search" type="search" wire:model.live.debounce.300ms="search" autocomplete="off">
            </div>
            <div class="field">
                <label for="sup-status">Status</label>
                <select id="sup-status" wire:model.live="status">
                    <option value="">All</option>
                    @foreach ($statuses as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="sup-state">Review</label>
                <select id="sup-state" wire:model.live="state">
                    <option value="">Any</option>
                    <option value="review">Overdue</option>
                </select>
            </div>
            <button class="btn" type="button" wire:click="resetFilters">Reset</button>
            <span class="muted small" wire:loading.delay>Updating…</span>
            <button class="btn btn-primary" type="button" wire:click="create" style="margin-left:auto">+ Add supplier</button>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>Suppliers</h2>
        <span class="muted small">{{ $suppliers->total() }} records · <a href="{{ route('incoming.settings.edit') }}">Incoming rules</a></span>
    </div>

    @if ($suppliers->isEmpty())
        <div class="empty-state">
            <strong>No suppliers found</strong>
            <p>{{ $total ? 'Nothing matches these filters.' : 'Add the first supplier to start the approved list. Material lots can then name the supplier they came from.' }}</p>
            <button class="btn btn-primary" type="button" wire:click="create">+ Add supplier</button>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Supplier</th><th>Supplies</th><th>Status</th><th>Score</th><th>Next review</th><th></th></tr></thead>
                <tbody>
                @foreach ($suppliers as $supplier)
                    <tr wire:key="supplier-{{ $supplier->id }}">
                        <td><a href="{{ route('suppliers.show', $supplier) }}"><strong class="mono">{{ $supplier->code }}</strong></a><div class="muted small">{{ $supplier->name }}@if ($supplier->contact_name || $supplier->phone) · {{ collect([$supplier->contact_name, $supplier->phone])->filter()->implode(' · ') }}@endif</div></td>
                        <td>{{ $supplier->supplies ?: '—' }}</td>
                        <td><span class="status {{ in_array($supplier->approval_status, ['APPROVED']) ? 'status-completed' : (in_array($supplier->approval_status, Supplier::BLOCKED) ? 'status-cancelled' : '') }}">{{ $statuses[$supplier->approval_status] }}</span></td>
                        <td>{{ $supplier->latest_score ?? '—' }}</td>
                        <td @if ($supplier->isReviewOverdue()) style="color:var(--danger)" @endif>{{ $supplier->next_review_on?->displayDate() ?? '—' }}</td>
                        <td style="white-space:nowrap"><button class="btn btn-small" type="button" wire:click="edit('{{ $supplier->id }}')">Quick edit</button> <a href="{{ route('suppliers.show', $supplier) }}">Open →</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <div class="muted small">Showing {{ $suppliers->firstItem() }}–{{ $suppliers->lastItem() }} of {{ $suppliers->total() }}</div>
            <div class="pagination-links">{{ $suppliers->links() }}</div>
        </div>
    @endif
</div>

@if ($formOpen)
<dialog class="qd" aria-labelledby="supplier-dialog-title" x-data x-init="$el.showModal()" x-on:close="$wire.closeForm()" wire:key="supplier-dialog">
    <form wire:submit="save">
        <div class="qd-head"><h2 id="supplier-dialog-title">{{ $editingId ? 'Quick edit supplier' : 'Add supplier' }}</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
        <div class="qd-body">
            @if ($errors->any())<div class="alert alert-error qd-errors" role="alert">@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
            <div class="qd-fields">
                <div class="field"><label for="s-name">Name <span class="required">*</span></label><input id="s-name" wire:model="form.name" maxlength="150" autocomplete="off" @error('form.name') aria-invalid="true" @enderror></div>
                <div class="field"><label for="s-code">Code</label><input id="s-code" wire:model="form.code" maxlength="40" autocomplete="off" style="text-transform:uppercase" placeholder="{{ $editingId ? '' : 'Blank: '.$nextCode }}" @error('form.code') aria-invalid="true" @enderror></div>
                <div class="field field-full"><label for="s-supplies">What they supply</label><input id="s-supplies" wire:model="form.supplies" maxlength="200" placeholder="e.g. pig iron, ferro-silicon, bentonite"></div>
                <div class="field"><label for="s-contact">Contact person</label><input id="s-contact" wire:model="form.contact_name" maxlength="150"></div>
                <div class="field"><label for="s-phone">Phone</label><input id="s-phone" wire:model="form.phone" maxlength="60"></div>
                <div class="field"><label for="s-email">Email</label><input id="s-email" type="email" wire:model="form.email" maxlength="160" @error('form.email') aria-invalid="true" @enderror></div>
                <div class="field"><label for="s-interval">Review every (days) <span class="required">*</span></label><input id="s-interval" type="number" min="30" max="1825" wire:model="form.review_interval_days" @error('form.review_interval_days') aria-invalid="true" @enderror></div>
                <div class="field field-full"><label for="s-address">Address</label><textarea id="s-address" rows="2" wire:model="form.billing_address"></textarea></div>
                <div class="field field-full"><label for="s-notes">Notes</label><textarea id="s-notes" rows="2" wire:model="form.notes"></textarea></div>
            </div>
            @unless ($editingId)<p class="muted small">New suppliers start as pending. Record an evaluation on the supplier's page to approve them.</p>@endunless
        </div>
        <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">{{ $editingId ? 'Save changes' : 'Add supplier' }}</button></div>
    </form>
</dialog>
@endif
</div>
