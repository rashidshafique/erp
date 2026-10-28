<?php

use App\Models\StockLocation;
use App\Services\Stock\StockLevels;
use App\Services\Stock\StockLocationTree;
use App\Services\Stock\StockLocationTypes;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The stock location register: search, add, edit, switch off and delete without a reload. A location can sit under another
 * (a bin in a store), but never under itself or something below it. One in use by stock movements is switched off, not deleted.
 */
new class extends Component
{
    #[Url(except: '')]
    public string $search = '';

    public bool $showInactive = false;

    public bool $formOpen = false;

    public ?string $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $locationType = 'STORE';

    public ?string $parentId = null;

    public string $description = '';

    public function create(): void
    {
        $this->resetForm();
        $this->formOpen = true;
    }

    public function edit(string $id): void
    {
        $location = StockLocation::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $location->id;
        $this->code = $location->code;
        $this->name = $location->name;
        $this->locationType = $location->location_type;
        $this->parentId = $location->parent_location_id;
        $this->description = (string) $location->description;
        $this->formOpen = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $location = $this->editingId ? StockLocation::findOrFail($this->editingId) : null;
        $blocked = $location ? [$location->id, ...$location->descendantIds()] : [];

        $data = $this->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('stock_locations', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:120'],
            'locationType' => ['required', Rule::in(app(StockLocationTypes::class)->keys())],
            'parentId' => ['nullable', 'uuid', Rule::exists('stock_locations', 'id'), Rule::notIn($blocked)],
            'description' => ['nullable', 'string', 'max:2000'],
        ], ['parentId.not_in' => 'A location cannot sit under itself or one of its own sub-locations.']);

        $attributes = [
            'code' => $data['code'],
            'name' => $data['name'],
            'location_type' => $data['locationType'],
            'parent_location_id' => $data['parentId'] ?: null,
            'description' => $data['description'] ?: null,
        ];

        $location ? $location->update($attributes) : StockLocation::create($attributes + ['is_active' => true]);
        $this->resetForm();
    }

    public function toggleActive(string $id): void
    {
        $location = StockLocation::findOrFail($id);
        $location->update(['is_active' => ! $location->is_active]);
    }

    public function delete(string $id): void
    {
        $location = StockLocation::findOrFail($id);
        if ($location->isInUse()) {
            $this->addError('delete', "{$location->code} is in use by sub-locations or stock movements. Switch it off instead.");

            return;
        }
        $location->delete();
    }

    private function resetForm(): void
    {
        $this->reset('formOpen', 'editingId', 'code', 'name', 'locationType', 'parentId', 'description');
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $search = trim($this->search);
        $locations = StockLocation::query()
            ->with('parent')
            ->when(! $this->showInactive, fn ($q) => $q->where('is_active', true))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('code', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%")))
            ->orderBy('code')
            ->get();

        return [
            'rows' => app(StockLocationTree::class)->rows($locations, app(StockLevels::class)->onHandByLocation()),
            'typeOptions' => app(StockLocationTypes::class)->options(),
            'parents' => StockLocation::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'inactiveCount' => StockLocation::where('is_active', false)->count(),
        ];
    }
};
?>

<div>
<div class="panel">
    <div class="panel-body">
        <div class="filters" role="search">
            <div class="field">
                <label for="search">Search code or name</label>
                <input id="search" type="search" wire:model.live.debounce.300ms="search" autocomplete="off">
            </div>
            <label class="muted small"><input type="checkbox" wire:model.live="showInactive"> Show switched-off ({{ $inactiveCount }})</label>
            <button class="btn btn-primary" type="button" wire:click="create">+ Add location</button>
        </div>
    </div>
</div>

@error('delete')<div class="alert alert-danger">{{ $message }}</div>@enderror

@if ($formOpen)
<div class="panel">
    <div class="panel-header"><h2>{{ $editingId ? 'Edit location' : 'New location' }}</h2></div>
    <div class="panel-body">
        <form wire:submit="save" class="form-grid">
            <div class="field"><label for="loc-code">Code</label><input id="loc-code" wire:model="code">@error('code')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="loc-name">Name</label><input id="loc-name" wire:model="name">@error('name')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="loc-type">Type</label>
                <select id="loc-type" wire:model="locationType">@foreach ($typeOptions as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                @error('locationType')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="loc-parent">Inside</label>
                <select id="loc-parent" wire:model="parentId"><option value="">— top level —</option>@foreach ($parents as $parent)<option value="{{ $parent->id }}">{{ $parent->code }} · {{ $parent->name }}</option>@endforeach</select>
                @error('parentId')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field" style="grid-column:1/-1"><label for="loc-desc">Description</label><input id="loc-desc" wire:model="description">@error('description')<div class="error">{{ $message }}</div>@enderror</div>
            <div><button class="btn btn-primary" type="submit">Save</button> <button class="btn" type="button" wire:click="cancel">Cancel</button></div>
        </form>
    </div>
</div>
@endif

<div class="panel">
    <div class="table-wrap"><table>
        <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Held here (kg)</th><th>With sub-stores (kg)</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($rows as $row)
            @php($location = $row['location'])
            <tr wire:key="loc-{{ $location->id }}">
                <td class="mono" style="padding-left:{{ 0.75 + $row['depth'] * 1.25 }}rem">{{ $location->code }}</td>
                <td>{{ $location->name }}<div class="muted small">{{ $location->description }}</div></td>
                <td>{{ $location->typeLabel() }}@if ($location->isExternal()) <span class="muted small">(not ours)</span>@endif</td>
                <td>{{ $row['own'] > 0 ? \App\Support\Formats::number($row['own'], 3) : '—' }}</td>
                <td>{{ $row['children'] > 0 && $row['total'] > 0 ? \App\Support\Formats::number($row['total'], 3) : '—' }}</td>
                <td>{{ $location->is_active ? 'Active' : 'Switched off' }}</td>
                <td class="actions">
                    <button class="btn btn-sm" type="button" wire:click="edit('{{ $location->id }}')">Edit</button>
                    <button class="btn btn-sm" type="button" wire:click="toggleActive('{{ $location->id }}')">{{ $location->is_active ? 'Switch off' : 'Switch on' }}</button>
                    <button class="btn btn-sm" type="button" wire:click="delete('{{ $location->id }}')" wire:confirm="Delete {{ $location->code }}?">Delete</button>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No stock locations yet. Add the first store or yard.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
</div>
