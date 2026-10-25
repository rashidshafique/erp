<?php

use App\Models\Heat;
use Livewire\Attributes\On;
use Livewire\Component;

/** The "Metal charged" figure above the heat's tabs: worked out from the recorded charges, and kept current when one is changed. */
new class extends Component
{
    public string $heatId;

    public function mount(string $heatId): void
    {
        $this->heatId = $heatId;
    }

    #[On('charges-changed')]
    public function refresh(): void {}

    /** @return array<string, mixed> */
    public function with(): array
    {
        $heat = Heat::with(['chargeBatch.items', 'materialConsumptions'])->findOrFail($this->heatId);

        return [
            'heat' => $heat,
            'metal' => $heat->chargedMetalKg(),
            'planned' => $heat->chargeRounds(),
            'done' => $heat->materialConsumptions->where('charge_round', '>', 0)->pluck('charge_round')->unique()->count(),
        ];
    }
};
?>

<div class="fact" title="Worked out from the charges recorded on the Charge tab: metal materials only">
    <div class="muted small">Metal charged</div>
    <strong>{{ $metal > 0 ? \App\Support\Formats::number($metal, 1).' kg' : '—' }}</strong>
    @if ($metal > 0 && $heat->target_metal_weight_kg > 0)<span class="muted small"> · {{ \App\Support\Formats::number($metal / (float) $heat->target_metal_weight_kg * 100, 0) }}% of target</span>@endif
    @if ($heat->chargeBatch)<div class="muted small">{{ $done }} of {{ $planned }} {{ $planned === 1 ? 'charge' : 'charges' }}</div>@endif
</div>
