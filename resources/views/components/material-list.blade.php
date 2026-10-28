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

@if ($saved)<div class="alert alert-success" role="status">{{ $saved }}</div>@endif

<div class="metrics">
    <div class="metric"><div class="metric-label">Total materials</div><div class="metric-value">{{ \App\Support\Formats::number($totalMaterials) }}</div></div>
    <div class="metric"><div class="metric-label">Active</div><div class="metric-value">{{ \App\Support\Formats::number($activeMaterials) }}</div></div>
    <div class="metric"><div class="metric-label">Inactive</div><div class="metric-value">{{ \App\Support\Formats::number($inactiveMaterials) }}</div></div>
    <div class="metric"><div class="metric-label">At or below minimum</div><div class="metric-value" @if ($lowCount) style="color:var(--danger)" @endif>{{ \App\Support\Formats::number($lowCount) }}</div></div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="filters" role="search">
            <div class="field">
                <label for="mat-search">Search code or name</label>
                <input id="mat-search" type="search" wire:model.live.debounce.300ms="search" placeholder="e.g. FESI-75" autocomplete="off">
            </div>
            <div class="field">
                <label for="mat-type">Type</label>
                <select id="mat-type" wire:model.live="type">
                    <option value="">All types</option>
                    @foreach (\App\Models\Material::TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="mat-status">Status</label>
                <select id="mat-status" wire:model.live="status">
                    <option value="">All</option>
                    <option value="ACTIVE">Active</option>
                    <option value="INACTIVE">Inactive</option>
                </select>
            </div>
            <div class="field">
                <label for="mat-stock">Stock</label>
                <select id="mat-stock" wire:model.live="stock">
                    <option value="">All</option>
                    <option value="low">At or below minimum</option>
                    <option value="untracked">Not stock tracked</option>
                </select>
            </div>
            <button class="btn" type="button" wire:click="resetFilters">Reset</button>
            <span class="muted small" wire:loading.delay>Updating…</span>
            <button class="btn btn-primary" type="button" wire:click="create" style="margin-left:auto">+ New material</button>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>Materials</h2>
        <span class="muted small">{{ $materials->total() }} records</span>
    </div>

    @if ($materials->isEmpty())
        <div class="empty-state">
            <strong>No materials found</strong>
            <p>{{ $totalMaterials ? 'Nothing matches these filters.' : 'Add the first material to start recording lots and stock.' }}</p>
            <button class="btn btn-primary" type="button" wire:click="create">+ New material</button>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Material</th><th>Type</th><th>Unit</th><th>On hand</th><th>Minimum</th><th>Lots</th><th>Recipes</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($materials as $material)
                        @php
                            $held = $onHand[$material->id] ?? 0.0;
                            $low = $levels->isAtOrBelowMinimum($material, $held);
                        @endphp
                        <tr wire:key="material-{{ $material->id }}">
                            <td><a href="{{ route('materials.show', $material) }}"><strong>{{ $material->code }}</strong></a><div class="muted small">{{ $material->name }}</div></td>
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
                            <td>{{ $material->lots_count }}</td>
                            <td>{{ $material->charge_batch_items_count }}</td>
                            <td><span class="status {{ $material->status === 'ACTIVE' ? 'status-completed' : 'status-cancelled' }}">{{ $material->status }}</span></td>
                            <td style="white-space:nowrap"><button class="btn btn-small" type="button" wire:click="edit('{{ $material->id }}')">Quick edit</button> <a href="{{ route('materials.show', $material) }}">Open →</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">
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
