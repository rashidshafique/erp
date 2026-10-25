<?php

use App\Http\Middleware\EnforceAccess;
use App\Models\Heat;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The charges someone removed on purpose, which the schedule leaves alone. A button puts one back on the schedule, in place; the
 * list follows removals made in the charge history.
 */
new class extends Component
{
    public string $heatId;

    public function mount(string $heatId): void
    {
        $this->heatId = $heatId;
    }

    #[On('charges-changed')]
    public function refresh(): void {}

    public function restore(int $round): void
    {
        abort_unless(EnforceAccess::allows(auth()->user(), 'heats.charge-rounds.restore'), 403);
        $heat = Heat::findOrFail($this->heatId);
        $heat->update(['skipped_charge_rounds' => array_values(array_diff($heat->skippedChargeRounds(), [$round]))]);
        $this->dispatch('charges-changed');
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $heat = Heat::findOrFail($this->heatId);

        return ['rounds' => in_array($heat->status, ['CLOSED', 'CANCELLED'], true) ? [] : $heat->skippedChargeRounds()];
    }
};
?>

<div>
@if ($rounds !== [])
    <p class="small" style="margin:0 0 14px">Removed on purpose, so not added automatically:
        @foreach ($rounds as $round)
            <button type="button" class="btn btn-small" wire:click="restore({{ $round }})" wire:key="skipped-{{ $round }}" title="Put it back on the schedule">{{ $round === 0 ? 'Start-up charge' : 'Charge '.$round }}: restore</button>
        @endforeach
    </p>
@endif
</div>
