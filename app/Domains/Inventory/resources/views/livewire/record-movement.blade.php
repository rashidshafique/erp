<?php

use App\Models\Material;
use App\Models\MaterialLot;
use App\Models\StockLocation;
use App\Services\Stock\InsufficientStockException;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * One screen for the stock movements nobody posts from another page: an issue, a write-off (scrap), a count adjustment up
 * or down, and a transfer between locations. Everything goes through the stock ledger, so cost layers and the
 * reconciliation page stay in step. Locations are optional; a foundry that keeps none leaves them blank.
 */
new class extends Component
{
    /** @var array<string, array{label: string, needsReason: bool, from: bool, to: bool}> */
    public const KINDS = [
        'ISSUE' => ['label' => 'Issue to production or use', 'needsReason' => false, 'from' => true, 'to' => false],
        'SCRAP' => ['label' => 'Scrap or write off', 'needsReason' => true, 'from' => true, 'to' => false],
        'ADJUST_OUT' => ['label' => 'Count adjustment: less than recorded', 'needsReason' => true, 'from' => true, 'to' => false],
        'ADJUST_IN' => ['label' => 'Count adjustment: more than recorded', 'needsReason' => true, 'from' => false, 'to' => true],
        'TRANSFER' => ['label' => 'Transfer between locations', 'needsReason' => false, 'from' => true, 'to' => true],
    ];

    public string $kind = 'ISSUE';

    public ?string $materialId = null;

    public ?string $lotId = null;

    public string $quantity = '';

    public string $unitCost = '';

    public ?string $fromLocationId = null;

    public ?string $toLocationId = null;

    public string $reason = '';

    public string $recordedBy = '';

    public ?string $done = null;

    public function mount(): void
    {
        $this->recordedBy = (string) auth()->user()?->name;
    }

    public function updatedKind(): void
    {
        $this->reset('fromLocationId', 'toLocationId', 'unitCost');
        $this->resetValidation();
        $this->fillCostFromLot();
    }

    public function updatedMaterialId(): void
    {
        $this->reset('lotId', 'fromLocationId', 'toLocationId', 'unitCost');
    }

    public function updatedLotId(): void
    {
        $this->reset('fromLocationId', 'toLocationId');
        $this->fillCostFromLot();
    }

    private function fillCostFromLot(): void
    {
        $lot = $this->lotId ? MaterialLot::find($this->lotId) : null;
        $this->unitCost = $this->kind === 'ADJUST_IN' && $lot?->unit_cost !== null ? (string) (float) $lot->unit_cost : '';
    }

    public function save(): void
    {
        $kind = self::KINDS[$this->kind];
        $locations = Rule::exists('stock_locations', 'id')->where('is_active', true);

        $data = $this->validate([
            'kind' => ['required', Rule::in(array_keys(self::KINDS))],
            'materialId' => ['required', 'uuid', Rule::exists('materials', 'id')->where('is_stock_tracked', true)->whereNull('deleted_at')],
            'lotId' => ['required', 'uuid', Rule::exists('material_lots', 'id')->where('material_id', $this->materialId)->where('status', 'ACCEPTED')],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'unitCost' => [$this->kind === 'ADJUST_IN' ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999999'],
            'fromLocationId' => ['nullable', 'uuid', $locations],
            'toLocationId' => [$this->kind === 'TRANSFER' ? 'required' : 'nullable', 'uuid', $locations, $this->kind === 'TRANSFER' ? Rule::notIn([$this->fromLocationId]) : 'nullable'],
            'reason' => [$kind['needsReason'] ? 'required' : 'nullable', 'string', 'max:2000'],
            'recordedBy' => ['required', 'string', 'max:100'],
        ], ['toLocationId.not_in' => 'Choose a different location to move the stock to.', 'toLocationId.required' => 'Choose where the stock is moving to.']);

        $quantity = (float) $data['quantity'];
        $details = ['recorded_by' => $data['recordedBy'], 'reason' => $data['reason'] ?: null];

        try {
            $movement = DB::transaction(function () use ($data, $quantity, $details) {
                $lot = MaterialLot::whereKey($data['lotId'])->lockForUpdate()->firstOrFail();
                $uom = (string) $lot->material->base_uom;
                $ledger = app(StockLedger::class);

                return match ($data['kind']) {
                    'ISSUE' => $ledger->issue($lot, $quantity, $uom, $details + ['source_type' => 'MANUAL_ISSUE', 'from_location_id' => $data['fromLocationId'] ?: null]),
                    'SCRAP' => $ledger->issue($lot, $quantity, $uom, $details + ['source_type' => 'SCRAP', 'from_location_id' => $data['fromLocationId'] ?: null]),
                    'ADJUST_OUT' => $ledger->issue($lot, $quantity, $uom, $details + ['source_type' => 'ADJUSTMENT', 'from_location_id' => $data['fromLocationId'] ?: null]),
                    'ADJUST_IN' => $ledger->receive($lot, $quantity, (float) $data['unitCost'], $uom, $details + ['source_type' => 'ADJUSTMENT', 'to_location_id' => $data['toLocationId'] ?: null]),
                    'TRANSFER' => $ledger->transfer($lot, $quantity, $uom, $data['fromLocationId'] ?: null, $data['toLocationId'], $details),
                };
            });
        } catch (InsufficientStockException $e) {
            $this->addError('quantity', $e->getMessage());

            return;
        }

        $this->done = "{$movement->movement_number} recorded.";
        $this->reset('quantity', 'reason', 'fromLocationId', 'toLocationId');
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $lots = $this->materialId
            ? MaterialLot::where('material_id', $this->materialId)->where('status', 'ACCEPTED')->orderBy('lot_number')->get()
            : collect();
        $chosen = $this->lotId ? $lots->firstWhere('id', $this->lotId) : null;
        $held = $chosen ? app(StockLedger::class)->heldByLocation($chosen) : [];

        return [
            'kinds' => self::KINDS,
            'materials' => Material::where('is_stock_tracked', true)->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'code', 'name', 'base_uom']),
            'lots' => $lots,
            'locations' => StockLocation::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'held' => $held,
            'onHand' => round(array_sum($held), 3),
        ];
    }
};
?>

<div>
@if ($done)<div class="alert alert-success">{{ $done }} <a href="{{ route('stock-movements.index') }}">See the ledger</a></div>@endif

<div class="panel">
    <div class="panel-body">
        <form wire:submit="save" class="form-grid">
            <div class="field"><label for="mv-kind">What happened</label>
                <select id="mv-kind" wire:model.live="kind">@foreach ($kinds as $key => $info)<option value="{{ $key }}">{{ $info['label'] }}</option>@endforeach</select></div>
            <div class="field"><label for="mv-material">Material</label>
                <select id="mv-material" wire:model.live="materialId"><option value="">— choose —</option>@foreach ($materials as $material)<option value="{{ $material->id }}">{{ $material->code }} · {{ $material->name }}</option>@endforeach</select>
                @error('materialId')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="mv-lot">Lot</label>
                <select id="mv-lot" wire:model.live="lotId"><option value="">— choose —</option>@foreach ($lots as $lot)<option value="{{ $lot->id }}">{{ $lot->lot_number }}</option>@endforeach</select>
                @error('lotId')<div class="error">{{ $message }}</div>@enderror
                @if ($lotId)<div class="muted small">On hand: {{ \App\Support\Formats::number($onHand, 3) }} kg</div>@endif</div>
            <div class="field"><label for="mv-qty">Quantity (kg)</label><input id="mv-qty" type="number" step="0.001" wire:model="quantity">
                @error('quantity')<div class="error">{{ $message }}</div>@enderror</div>

            @if ($kind === 'ADJUST_IN')
                <div class="field"><label for="mv-cost">Cost per kg (PKR)</label><input id="mv-cost" type="number" step="0.0001" wire:model="unitCost">
                    @error('unitCost')<div class="error">{{ $message }}</div>@enderror</div>
            @endif

            @if ($locations->isNotEmpty())
                @if ($kinds[$kind]['from'])
                    <div class="field"><label for="mv-from">{{ $kind === 'TRANSFER' ? 'From' : 'Taken from' }}</label>
                        <select id="mv-from" wire:model="fromLocationId"><option value="">— no location —</option>@foreach ($locations as $location)<option value="{{ $location->id }}">{{ $location->code }} · {{ $location->name }}@isset($held[$location->id]) ({{ \App\Support\Formats::number($held[$location->id], 3) }} kg)@endisset</option>@endforeach</select>
                        @error('fromLocationId')<div class="error">{{ $message }}</div>@enderror</div>
                @endif
                @if ($kinds[$kind]['to'])
                    <div class="field"><label for="mv-to">{{ $kind === 'TRANSFER' ? 'To' : 'Put into' }}</label>
                        <select id="mv-to" wire:model="toLocationId"><option value="">— no location —</option>@foreach ($locations as $location)<option value="{{ $location->id }}">{{ $location->code }} · {{ $location->name }}</option>@endforeach</select>
                        @error('toLocationId')<div class="error">{{ $message }}</div>@enderror</div>
                @endif
            @endif

            <div class="field" style="grid-column:1/-1"><label for="mv-reason">Reason{{ $kinds[$kind]['needsReason'] ? '' : ' (optional)' }}</label><input id="mv-reason" wire:model="reason">
                @error('reason')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="mv-by">Recorded by</label><input id="mv-by" wire:model="recordedBy">
                @error('recordedBy')<div class="error">{{ $message }}</div>@enderror</div>
            <div><button class="btn btn-primary" type="submit">Record movement</button></div>
        </form>
    </div>
</div>
</div>
