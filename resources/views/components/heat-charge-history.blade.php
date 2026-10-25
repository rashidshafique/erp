<?php

use App\Http\Middleware\EnforceAccess;
use App\Models\Heat;
use App\Models\HeatMaterialConsumption;
use App\Models\Material;
use App\Services\Production\ChargeEntryEditor;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * The actual charge history of a heat. A weight is changed where it stands: click it, type, press Enter or click away, and only
 * this table changes. Entries can be removed from here too. Both go through the same editor as the page's own routes, so closed
 * heats and posted charges stay fixed.
 */
new class extends Component
{
    public string $heatId;

    public ?string $editing = null;

    public string $weight = '';

    public ?string $notice = null;

    public ?string $problem = null;

    public function mount(string $heatId): void
    {
        $this->heatId = $heatId;
    }

    public function edit(string $entryId): void
    {
        $this->authorizeChanges();
        $entry = HeatMaterialConsumption::where('heat_id', $this->heatId)->findOrFail($entryId);
        $this->editing = $entry->id;
        $this->weight = rtrim(rtrim((string) $entry->actual_qty_kg, '0'), '.');
        $this->notice = $this->problem = null;
    }

    public function cancel(): void
    {
        $this->editing = null;
        $this->problem = null;
    }

    public function save(ChargeEntryEditor $editor): void
    {
        $id = $this->editing;
        if ($id === null) {
            return;   // Enter and the click that follows it both arrive here; the second finds nothing to save
        }
        $this->authorizeChanges();
        $this->editing = null;
        $this->notice = $this->problem = null;
        $heat = Heat::findOrFail($this->heatId);
        $entry = HeatMaterialConsumption::where('heat_id', $heat->id)->findOrFail($id);

        if (! is_numeric($this->weight)) {
            $this->problem = 'Enter the weight as a number.';

            return;
        }
        try {
            $editor->reweigh($heat, $entry, (float) $this->weight, auth()->user()?->name ?? 'someone');
        } catch (ValidationException $e) {
            $this->problem = $e->validator->errors()->first();

            return;
        }
        $this->dispatch('charges-changed');
    }

    public function remove(string $entryId, ChargeEntryEditor $editor): void
    {
        $this->authorizeChanges();
        $this->notice = $this->problem = null;
        $heat = Heat::findOrFail($this->heatId);
        try {
            $round = $editor->remove($heat, HeatMaterialConsumption::where('heat_id', $heat->id)->findOrFail($entryId));
        } catch (ValidationException $e) {
            $this->problem = $e->validator->errors()->first();

            return;
        }
        $this->notice = 'Charge entry removed.'.($round === null ? '' : ' '.($round === 0 ? 'The start-up charge' : "Charge {$round}").' will not be added automatically; restore it from the Charge tab if it should be.');
        $this->dispatch('charges-changed');
    }

    private function authorizeChanges(): void
    {
        abort_unless(EnforceAccess::allows(auth()->user(), 'heats.charge-entries.update'), 403);
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $heat = Heat::with(['materialConsumptions.material', 'materialConsumptions.materialLot'])->findOrFail($this->heatId);

        return [
            'entries' => $heat->materialConsumptions,
            'canChange' => app(ChargeEntryEditor::class)->open($heat),
            'types' => Material::TYPES,
        ];
    }
};
?>

<div>
<style>
    .qty-edit { cursor: text; border-bottom: 1px dashed var(--muted); padding: 1px 2px; }
    .qty-edit:hover { background: color-mix(in srgb, var(--accent) 10%, transparent); border-bottom-color: var(--accent); }
    .qty-input { width: 110px; min-height: 28px; padding: 2px 6px; text-align: right; font: inherit; }
</style>
@if ($problem)<div class="alert alert-error">{{ $problem }}</div>@endif
@if ($notice)<div class="alert alert-success">{{ $notice }}</div>@endif
<div class="panel">
    <div class="panel-header">
        <h2>Actual charge history</h2>
        <span class="muted small">{{ $entries->count() }} entries @if ($canChange && $entries->isNotEmpty()) · click a weight to change it @endif</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Material type</th><th>Material</th><th>Lot / reference</th>
                    <th>Round</th><th class="num">Quantity kg</th><th>Cost PKR/kg</th><th>Total cost PKR</th>
                    <th>Recorded at</th><th>Operator</th>@if ($canChange)<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    @php $unitCost = $entry->actual_qty_kg > 0 ? (float) $entry->actual_total_cost / (float) $entry->actual_qty_kg : null; @endphp
                    <tr wire:key="entry-{{ $entry->id }}">
                        <td>{{ $entry->sequence }}</td>
                        <td>{{ $types[$entry->material?->material_type] ?? \Illuminate\Support\Str::headline(strtolower((string) $entry->material?->material_type)) ?: '—' }}@if ($entry->consumption_type === 'INTERNAL_RETURN') <span class="muted small">· return</span>@endif</td>
                        <td>{{ $entry->material?->code }} — {{ $entry->material?->name }}</td>
                        <td>{{ $entry->materialLot?->lot_number ?? $entry->source_reference ?? '—' }}</td>
                        <td>{{ $entry->charge_round === null ? '—' : ($entry->charge_round === 0 ? 'Start-up' : $entry->charge_round) }}</td>
                        <td class="num">
                            @if ($canChange && $editing === $entry->id)
                                <input class="qty-input" type="number" step="any" min="0.001" wire:model="weight" wire:keydown.enter.prevent="save" wire:keydown.escape="cancel" wire:blur="save"
                                    x-init="$nextTick(() => { $el.focus(); $el.select(); })" aria-label="Weight of {{ $entry->material?->name }} in kg">
                            @elseif ($canChange)
                                <span class="qty-edit" wire:click="edit('{{ $entry->id }}')" title="Click to change the weight" role="button" tabindex="0" wire:keydown.enter="edit('{{ $entry->id }}')">{{ \App\Support\Formats::number((float) $entry->actual_qty_kg, 3) }}</span>
                            @else
                                {{ \App\Support\Formats::number((float) $entry->actual_qty_kg, 3) }}
                            @endif
                        </td>
                        <td>{{ $unitCost !== null ? \App\Support\Formats::number($unitCost, 4) : '—' }}</td>
                        <td>{{ \App\Support\Formats::number((float) $entry->actual_total_cost, 2) }}</td>
                        <td>{{ $entry->consumed_at?->displayAt() ?? '—' }}</td>
                        <td>{{ $entry->recorded_by }}</td>
                        @if ($canChange)
                            <td><button type="button" class="icon-btn icon-btn-delete" wire:click="remove('{{ $entry->id }}')" wire:confirm="Remove {{ $entry->material?->name ?? 'this entry' }} ({{ \App\Support\Formats::number((float) $entry->actual_qty_kg, 3) }} kg) from the charges?" title="Remove" aria-label="Remove {{ $entry->material?->name }}">{!! \App\Support\NavIcons::get('trash', 16) !!}</button></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canChange ? 11 : 10 }}" class="muted">No actual charge entries recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</div>
