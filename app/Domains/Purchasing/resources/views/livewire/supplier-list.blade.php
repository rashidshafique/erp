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
@include('partials.list-ui')

@if ($saved)<div class="alert alert-success" role="status">{{ $saved }} @if ($savedId)<a href="{{ route('suppliers.show', $savedId) }}">Open supplier →</a>@endif</div>@endif

<div class="ls-card">
    <div class="ls-head">
        <h1 class="ls-title">Suppliers</h1>
        <div class="ls-actions">
            <a class="ls-btn" href="{{ route('settings.rules', ['advanced' => 1]).'#purchasing' }}">Incoming rules</a>
            <button class="ls-btn primary" type="button" wire:click="create">New supplier</button>
        </div>
    </div>
    <nav class="ls-tabs" aria-label="Supplier status">
        @foreach (['' => ['All', $total, ['', '']], 'PENDING' => ['Pending approval', $counts['PENDING'], ['PENDING', '']], 'CONDITIONAL' => ['Conditional', $counts['CONDITIONAL'], ['CONDITIONAL', '']], 'SUSPENDED' => ['Suspended / disqualified', $counts['SUSPENDED'], ['SUSPENDED', '']]] as $key => [$label, $n, $slice])
            @php $on = $status === $slice[0]; @endphp
            <a href="#" wire:click.prevent="showSlice('{{ $slice[0] }}', '{{ $slice[1] }}')" @class(['on' => $on]) @if ($on) aria-current="page" @endif>{{ $label }}<span @class(['n', 'bad' => $key === 'SUSPENDED' && $n > 0])>{{ $n }}</span></a>
        @endforeach
    </nav>
    <div class="ls-tools" role="search">
        @include('partials.list-search', ['placeholder' => 'Search code, name or what they supply'])
        <x-list-filters :count="in_array($status, ['', 'PENDING', 'CONDITIONAL', 'SUSPENDED'], true) ? 0 : 1">
            <label>Status<select wire:model.live="status"><option value="">All</option>@foreach ($statuses as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
        </x-list-filters>
        @if ($counts['review'] > 0 || $state === 'review')
            <span class="ls-sep" aria-hidden="true"></span>
            <button type="button" wire:click="showSlice('{{ $status }}', '{{ $state === 'review' ? '' : 'review' }}')" @class(['ls-flag', 'on' => $state === 'review']) aria-pressed="{{ $state === 'review' ? 'true' : 'false' }}"><b>{{ $counts['review'] }}</b>Review overdue</button>
        @endif
        @if ($search !== '' || $status !== '' || $state !== '')<button class="ls-tool" type="button" wire:click="resetFilters">Reset</button>@endif
        <span class="muted small" wire:loading.delay>Updating…</span>
    </div>

    @if ($suppliers->isEmpty())
        <div class="empty-state">
            <strong>No suppliers found</strong>
            <p>{{ $total ? 'Nothing matches these filters.' : 'Add the first supplier to start the approved list. Material lots can then name the supplier they came from.' }}</p>
            <button class="btn btn-primary" type="button" wire:click="create">New supplier</button>
        </div>
    @else
        <div class="ls-list"><div class="ls-scroll">
            <table class="no-row-click">
                <thead><tr><th>Supplier</th><th>Supplies</th><th>Status</th><th>Score</th><th>Next review</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                @foreach ($suppliers as $supplier)
                    @php $show = route('suppliers.show', $supplier); @endphp
                    <tr wire:key="supplier-{{ $supplier->id }}" tabindex="0" x-data x-on:click="if (! $event.target.closest('a,button,summary,details')) window.location = '{{ $show }}'" x-on:keydown.enter="window.location = '{{ $show }}'">
                        <td><a href="{{ $show }}"><strong class="mono">{{ $supplier->code }}</strong></a><div class="muted small">{{ $supplier->name }}@if ($supplier->contact_name || $supplier->phone) · {{ collect([$supplier->contact_name, $supplier->phone])->filter()->implode(' · ') }}@endif</div></td>
                        <td>{{ $supplier->supplies ?: '—' }}</td>
                        <td><span class="status {{ in_array($supplier->approval_status, ['APPROVED']) ? 'status-completed' : (in_array($supplier->approval_status, Supplier::BLOCKED) ? 'status-cancelled' : '') }}">{{ $statuses[$supplier->approval_status] }}</span></td>
                        <td>{{ $supplier->latest_score ?? '—' }}</td>
                        <td @if ($supplier->isReviewOverdue()) style="color:var(--danger)" @endif>{{ $supplier->next_review_on?->displayDate() ?? '—' }}</td>
                        <td class="r"><details class="ls-menu right"><summary class="ls-more" aria-label="Actions for {{ $supplier->code }}">⋯</summary>
                            <div class="ls-pop" role="menu"><a role="menuitem" href="{{ $show }}">Open</a><button type="button" role="menuitem" wire:click="edit('{{ $supplier->id }}')">Quick edit</button></div></details></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div></div>
        <div class="pagination ls-pager">
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
