<?php

use App\Http\Middleware\EnforceAccess;
use App\Models\Heat;
use App\Models\HeatMaterialConsumption;
use App\Models\Material;
use App\Services\Production\ChargeEntryEditor;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * The actual charge history of a heat. A weight is changed where it stands: the cell itself is editable, so click it, type,
 * press Enter or click away, and only this table changes; Escape puts it back. Entries can be removed from here too. Both go
 * through the same editor as the page's own routes, so closed heats and posted charges stay fixed.
 */
new class extends Component
{
    public string $heatId;

    public ?string $notice = null;

    public ?string $problem = null;

    public function mount(string $heatId): void
    {
        $this->heatId = $heatId;
    }

    public function save(string $entryId, string $weight, ChargeEntryEditor $editor): void
    {
        $this->authorizeChanges();
        $this->notice = $this->problem = null;
        $heat = Heat::findOrFail($this->heatId);
        $entry = HeatMaterialConsumption::where('heat_id', $heat->id)->findOrFail($entryId);
        $weight = str_replace([',', ' ', "\u{00A0}"], '', trim($weight));   // the cell shows the weight with thousands separators

        if (! is_numeric($weight)) {
            $this->problem = 'Enter the weight as a number.';

            return;
        }
        try {
            $editor->reweigh($heat, $entry, (float) $weight, auth()->user()?->name ?? 'someone');
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
            'hasRecipe' => $heat->charge_batch_id !== null,   // with a recipe, an entry outside its charges is an extra
            'types' => Material::TYPES,
        ];
    }
};
?>

<div>
<style>
    .qty-edit { cursor: text; border-bottom: 1px dashed var(--muted); padding: 1px 2px; outline: none; display: inline-block; min-width: 3ch; }
    .qty-edit:hover { background: color-mix(in srgb, var(--accent) 10%, transparent); border-bottom-color: var(--accent); }
    .qty-edit:focus { background: color-mix(in srgb, var(--accent) 14%, transparent); border-bottom: 1px solid var(--accent); }
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
                        @php
                            $round = match (true) {
                                $entry->charge_round === 0 => 'Start-up',
                                $entry->charge_round !== null => (string) $entry->charge_round,
                                $hasRecipe => 'Extra',
                                default => '—',
                            };
                            $reason = $round === 'Extra' && $entry->addition_reason ? (\App\Models\HeatMaterialConsumption::ADDITION_REASONS[$entry->addition_reason] ?? $entry->addition_reason) : null;
                        @endphp
                        <td>{{ $round }}@if ($reason) <span class="muted small">· {{ $reason }}</span>@endif</td>
                        <td class="num">
                            @if ($canChange)
                                <span class="qty-edit" contenteditable="plaintext-only" inputmode="decimal" spellcheck="false" role="textbox" title="Click to change the weight" aria-label="Weight of {{ $entry->material?->name }} in kg"
                                    x-data="{ shown: '' }"
                                    x-on:focus="shown = $el.textContent; getSelection().selectAllChildren($el)"
                                    x-on:keydown.enter.prevent="$el.blur()"
                                    x-on:keydown.escape.prevent="$el.textContent = shown; $el.blur()"
                                    x-on:blur="$el.textContent.trim() !== shown.trim() && $wire.save('{{ $entry->id }}', $el.textContent)">{{ \App\Support\Formats::number((float) $entry->actual_qty_kg, 3) }}</span>
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
