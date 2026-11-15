<?php

use App\Models\Material;
use App\Services\Stock\ReorderWatcher;
use App\Services\Stock\StockLevels;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The material list: search and the filters apply as they are typed, and a material is added or quick-edited in a dialog
 * without leaving the page. The full form page stays for the detail. The state is kept in the address, so a filtered
 * list can be bookmarked and the back button works.
 */
new class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $stock = '';

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
        return ['code' => '', 'name' => '', 'material_type' => 'METAL', 'base_uom' => 'kg', 'status' => 'ACTIVE', 'is_stock_tracked' => true,
            'requires_lot_tracking' => true, 'minimum_stock_qty' => '', 'reorder_qty' => '', 'standard_cost_per_uom' => '', 'description' => '', 'notes' => ''];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedStock(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'type', 'status', 'stock');
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
        $material = Material::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $material->id;
        $this->form = array_merge($this->blankForm(), array_map(fn ($value) => $value ?? '', $material->only(array_keys($this->blankForm()))));
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
        $material = $this->editingId ? Material::findOrFail($this->editingId) : null;
        $this->form['code'] = strtoupper(trim((string) $this->form['code']));
        $rules = collect(Material::rules($material))->mapWithKeys(fn ($rule, $field) => ["form.{$field}" => $rule])->all();
        $data = $this->validate($rules, [], ['form.code' => 'code', 'form.name' => 'name', 'form.material_type' => 'type', 'form.base_uom' => 'unit'])['form'];

        foreach (['minimum_stock_qty', 'reorder_qty', 'standard_cost_per_uom', 'description', 'notes'] as $optional) {
            $data[$optional] = ($data[$optional] ?? '') === '' ? null : $data[$optional];
        }

        if (blank($data['code'])) {
            unset($data['code']);
        }

        if ($material) {
            $material->update($data);
            $this->saved = "Material {$material->code} updated.";
        } else {
            $material = Material::create($data);
            $this->saved = "Material {$material->code} added.";
        }
        app(ReorderWatcher::class)->check($material);

        $this->closeForm();
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $levels = app(StockLevels::class);
        $reorder = $levels->needingReorder();
        $search = trim($this->search);

        $materials = Material::query()
            ->withCount(['lots', 'chargeBatchItems'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('code', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%")))
            ->when($this->type !== '', fn ($q) => $q->where('material_type', $this->type))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->stock === 'low', fn ($q) => $q->whereIn('id', $reorder->pluck('material.id')))
            ->when($this->stock === 'untracked', fn ($q) => $q->where('is_stock_tracked', false))
            ->orderBy('code')
            ->paginate(20);

        return [
            'materials' => $materials,
            'onHand' => $levels->onHand($materials->pluck('id')->all()),
            'levels' => $levels,
            'totalMaterials' => Material::count(),
            'activeMaterials' => Material::where('status', 'ACTIVE')->count(),
            'inactiveMaterials' => Material::where('status', '!=', 'ACTIVE')->count(),
            'lowCount' => $reorder->count(),
            'nextCode' => app(\App\Services\Codes\CodeGenerator::class)->preview('material'),
        ];
    }
};
?>

<div>
@include('partials.quick-dialog-assets')
@include('partials.list-ui')

@if ($saved)<div class="alert alert-success" role="status">{{ $saved }}</div>@endif

<div class="ls-card">
    <div class="ls-head">
        <h1 class="ls-title">Materials</h1>
        <div class="ls-actions"><button class="ls-btn primary" type="button" wire:click="create">New material</button></div>
    </div>
    <nav class="ls-tabs" aria-label="Material status">
        @foreach (['' => ['All', $totalMaterials], 'ACTIVE' => ['Active', $activeMaterials], 'INACTIVE' => ['Inactive', $inactiveMaterials]] as $key => [$label, $n])
            <a href="#" wire:click.prevent="$set('status', '{{ $key }}')" @class(['on' => $status === $key]) @if ($status === $key) aria-current="page" @endif>{{ $label }}<span class="n">{{ \App\Support\Formats::number($n) }}</span></a>
        @endforeach
    </nav>
    <div class="ls-tools" role="search">
        @include('partials.list-search', ['placeholder' => 'Search code or name'])
        <x-list-filters :count="($type !== '' ? 1 : 0) + ($stock === 'untracked' ? 1 : 0)">
            <label>Type<select wire:model.live="type"><option value="">All types</option>@foreach (\App\Models\Material::TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
            <label>Stock<select wire:model.live="stock"><option value="">All</option><option value="low">At or below minimum</option><option value="untracked">Not stock tracked</option></select></label>
        </x-list-filters>
        @if ($lowCount > 0 || $stock === 'low')
            <span class="ls-sep" aria-hidden="true"></span>
            <button type="button" wire:click="$set('stock', '{{ $stock === 'low' ? '' : 'low' }}')" @class(['ls-flag', 'on' => $stock === 'low']) aria-pressed="{{ $stock === 'low' ? 'true' : 'false' }}"><b>{{ \App\Support\Formats::number($lowCount) }}</b>at or below minimum</button>
        @endif
        @if ($search !== '' || $type !== '' || $status !== '' || $stock !== '')<button class="ls-tool" type="button" wire:click="resetFilters">Reset</button>@endif
        <span class="muted small" wire:loading.delay>Updating…</span>
    </div>

    @if ($materials->isEmpty())
        <div class="empty-state">
            <strong>No materials found</strong>
            <p>{{ $totalMaterials ? 'Nothing matches these filters.' : 'Add the first material to start recording lots and stock.' }}</p>
            <button class="btn btn-primary" type="button" wire:click="create">New material</button>
        </div>
    @else
        <div class="ls-list"><div class="ls-scroll">
            <table class="no-row-click">
                <thead>
                    <tr>
                        <th>Material</th><th>Type</th><th>Unit</th><th>On hand</th><th>Minimum</th>@fieldcan('costs')<th>Cost per unit</th>@endfieldcan<th>Lots</th><th>Recipes</th><th>Status</th><th><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($materials as $material)
                        @php
                            $held = $onHand[$material->id] ?? 0.0;
                            $low = $levels->isAtOrBelowMinimum($material, $held);
                            $show = route('materials.show', $material);
                        @endphp
                        <tr wire:key="material-{{ $material->id }}" tabindex="0" x-data x-on:click="if (! $event.target.closest('a,button,summary,details')) window.location = '{{ $show }}'" x-on:keydown.enter="window.location = '{{ $show }}'">
                            <td><a href="{{ $show }}"><strong>{{ $material->code }}</strong></a><div class="muted small">{{ $material->name }}</div></td>
                            <td>{{ \App\Models\Material::TYPES[$material->material_type] ?? $material->material_type }}</td>
                            <td>{{ $material->base_uom }}</td>
                            <td>
                                @if ($material->is_stock_tracked)
                                    <span @if ($low) style="color:var(--danger);font-weight:600" @endif>{{ \App\Support\Formats::number($held, 3) }}</span>
                                    @if ($low)<div class="small" style="color:var(--danger)">{{ $held <= 0.0005 ? 'out of stock' : 'reorder' }}</div>@endif
                                @else
                                    <span class="muted">not tracked</span>
                                @endif
                            </td>
                            <td>{{ $material->minimum_stock_qty !== null ? \App\Support\Formats::number((float) $material->minimum_stock_qty, 3) : '—' }}</td>
                            @fieldcan('costs')<td>{{ $material->standard_cost_per_uom !== null ? \App\Support\Formats::money((float) $material->standard_cost_per_uom).' / '.$material->base_uom : '—' }}</td>
                            @endfieldcan
                            <td>{{ $material->lots_count }}</td>
                            <td>{{ $material->charge_batch_items_count }}</td>
                            <td><span class="status {{ $material->status === 'ACTIVE' ? 'status-completed' : 'status-cancelled' }}">{{ $material->status }}</span></td>
                            <td class="r"><details class="ls-menu right"><summary class="ls-more" aria-label="Actions for {{ $material->code }}">⋯</summary>
                            <div class="ls-pop" role="menu"><a role="menuitem" href="{{ $show }}">Open</a><button type="button" role="menuitem" wire:click="edit('{{ $material->id }}')">Quick edit</button></div></details></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div></div>
        <div class="pagination ls-pager">
            <div class="muted small">Showing {{ $materials->firstItem() }}–{{ $materials->lastItem() }} of {{ $materials->total() }}</div>
            <div class="pagination-links">{{ $materials->links() }}</div>
        </div>
    @endif
</div>

@if ($formOpen)
<dialog class="qd" aria-labelledby="material-dialog-title" x-data x-init="$el.showModal()" x-on:close="$wire.closeForm()" wire:key="material-dialog">
    <form wire:submit="save">
        <div class="qd-head"><h2 id="material-dialog-title">{{ $editingId ? 'Quick edit material' : 'New material' }}</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
        <div class="qd-body">
            @if ($errors->any())<div class="alert alert-error qd-errors" role="alert">@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
            <div class="qd-fields">
                <div class="field"><label for="m-code">Code</label><input id="m-code" wire:model="form.code" maxlength="50" autocomplete="off" style="text-transform:uppercase" placeholder="{{ $editingId ? '' : 'Blank: '.$nextCode }}" @error('form.code') aria-invalid="true" @enderror></div>
                <div class="field"><label for="m-name">Name <span class="required">*</span></label><input id="m-name" wire:model="form.name" maxlength="150" autocomplete="off" @error('form.name') aria-invalid="true" @enderror></div>
                <div class="field"><label for="m-type">Type <span class="required">*</span></label>
                    <select id="m-type" wire:model="form.material_type">@foreach (\App\Models\Material::TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                <div class="field"><label for="m-uom">Unit <span class="required">*</span></label>
                    <select id="m-uom" wire:model="form.base_uom">@foreach (\App\Models\Material::UNITS as $unit)<option value="{{ $unit }}">{{ $unit }}</option>@endforeach</select></div>
                <div class="field"><label for="m-min">Minimum stock</label><input id="m-min" type="number" min="0" step="any" wire:model="form.minimum_stock_qty"><div class="help">Alerts when stock falls to this.</div></div>
                <div class="field"><label for="m-reorder">Reorder quantity</label><input id="m-reorder" type="number" min="0" step="any" wire:model="form.reorder_qty"></div>
                <div class="field"><label for="m-cost">Standard cost per unit{{ \App\Support\Formats::currencySuffix() }}</label><input id="m-cost" type="number" min="0" step="any" wire:model="form.standard_cost_per_uom"></div>
                <div class="field"><label for="m-status">Status</label>
                    <select id="m-status" wire:model="form.status"><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select></div>
                <div class="field field-full"><label class="check-field"><input type="checkbox" wire:model="form.is_stock_tracked"> Track inventory stock</label></div>
                <div class="field field-full"><label class="check-field"><input type="checkbox" wire:model="form.requires_lot_tracking"> Require lot traceability</label></div>
                <div class="field field-full"><label for="m-desc">Description</label><textarea id="m-desc" rows="2" wire:model="form.description"></textarea></div>
                <div class="field field-full"><label for="m-notes">Internal notes</label><textarea id="m-notes" rows="2" wire:model="form.notes"></textarea></div>
            </div>
        </div>
        <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">{{ $editingId ? 'Save changes' : 'Add material' }}</button></div>
    </form>
</dialog>
@endif
</div>
