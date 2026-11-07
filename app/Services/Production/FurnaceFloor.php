<?php

namespace App\Services\Production;

use App\Domains\QuickFlow\Services\NextHeatPlan;
use App\Models\Heat;
use App\Models\Material;
use App\Models\MouldCount;
use App\Support\Formats;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * What a furnace tablet asks for next: the heat that is running (if any), the heats still open, the latest mould count,
 * and whether a charge is overdue. One place decides the biggest next step, so the button and the tab badge agree.
 */
class FurnaceFloor
{
    public function __construct(
        private ChargeSchedule $schedule,
        private HeatSamples $samples,
        private HeatBalance $balance,
        private NextHeatPlan $planner,
    ) {}

    /** The heat the furnace is running now: the latest one that has started and not been closed. */
    public function runningHeat(): ?Heat
    {
        return Heat::with('furnace', 'ironGrade', 'chargeBatch.items')
            ->whereIn('status', Heat::RUNNING_STATUSES)
            ->orderByDesc('actual_start_at')
            ->orderByDesc('created_at')
            ->first();
    }

    /** The heat that has started but is not closed; a planned heat is not on the floor yet. */
    public function openHeats(?string $exceptFurnaceId = null): Collection
    {
        return Heat::with('furnace', 'ironGrade')
            ->whereNotIn('status', ['CLOSED', 'CANCELLED', 'PLANNED'])
            ->when($exceptFurnaceId, fn ($q) => $q->where('furnace_id', '!=', $exceptFurnaceId))
            ->orderBy('actual_start_at')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * The next heat the plan would melt, or null when there is nothing to melt.
     *
     * @return ?array<string, mixed>
     */
    public function nextHeat(): ?array
    {
        return $this->planner->build()['heats'][0] ?? null;
    }

    /**
     * The latest count of moulds ready, all patterns counted at the same time.
     *
     * @return ?array{moulds: int, by: ?string, at: ?CarbonInterface}
     */
    public function latestCount(): ?array
    {
        $latest = MouldCount::whereNotNull('counted_at')->orderByDesc('counted_at')->first();
        if (! $latest) {
            return null;
        }
        $rows = MouldCount::where('counted_at', $latest->counted_at)->get();

        return ['moulds' => (int) $rows->sum('counted'), 'by' => $latest->counted_by, 'at' => $latest->counted_at];
    }

    /** How many minutes the heat's next charge is past due; null when no charge is waiting or it has no due time. */
    public function chargeLateMinutes(Heat $heat): ?int
    {
        $next = $this->schedule->for($heat)['next'];
        if (! $next || ! $next['due_at'] instanceof CarbonInterface) {
            return null;
        }

        return max(0, (int) floor((now()->getTimestamp() - $next['due_at']->getTimestamp()) / 60));
    }

    /** Whether the home tab should carry the black "!" because a charge on the running heat is overdue. */
    public function homeHasOverdueCharge(): bool
    {
        $heat = $this->runningHeat();

        return $heat !== null && ($this->chargeLateMinutes($heat) ?? 0) > 0;
    }

    /** The address the Heat tab opens: the running heat, else the next heat. */
    public function heatTabHref(): string
    {
        $heat = $this->runningHeat();

        return $heat ? route('furnace.heat.show', $heat) : route('furnace.next-heat');
    }

    /**
     * The one biggest next step on a running heat. The most overdue step wins; on a tie the charge wins, as the design says.
     *
     * @return array{kind: string, round: ?int, minutes: int, label: string, sub: string, tone: ?string}
     */
    public function bigStep(Heat $heat): array
    {
        $plan = $this->schedule->for($heat);
        $next = $plan['next'];
        $chargeLate = 0;
        if ($next && $next['due_at'] instanceof CarbonInterface) {
            $chargeLate = max(0, (int) floor((now()->getTimestamp() - $next['due_at']->getTimestamp()) / 60));
        }

        $sample = $this->samples->due($heat);
        $testLate = $sample && $sample['overdue'] ? max(0, $sample['minutes_since'] - $sample['interval']) : 0;

        if ($testLate > 0 && $testLate > $chargeLate) {
            return [
                'kind' => 'test', 'round' => null, 'minutes' => $testLate, 'label' => 'Record test',
                'sub' => ($testLate === 1 ? '1 minute' : $testLate.' minutes').' late · a sample every '.$sample['interval'].' min',
                'tone' => null,
            ];
        }

        if (! $next) {
            return [
                'kind' => 'pour', 'round' => null, 'minutes' => 0, 'label' => 'Pour moulds',
                'sub' => 'all '.$heat->chargeRounds().' charges are recorded', 'tone' => null,
            ];
        }

        $round = (int) $next['round'];
        $lines = $this->roundLinesLabel($heat, $round);
        $sub = $next['due_at'] instanceof CarbonInterface
            ? ($chargeLate > 0 ? 'Charge #'.$round.' is '.$this->minutes($chargeLate).' late · due '.$next['due_at']->format('H:i') : 'due '.$next['due_at']->format('H:i').($lines !== '' ? ' · '.$lines : ''))
            : 'no due time until the furnace has a melting rate'.($lines !== '' ? ' · '.$lines : '');

        return ['kind' => 'charge', 'round' => $round, 'minutes' => $chargeLate, 'label' => 'Charged #'.$round, 'sub' => $sub, 'tone' => null];
    }

    /** One charge's lines in words: "50 pig iron · 40 steel scrap · 16 coke". */
    public function roundLinesLabel(Heat $heat, int $round): string
    {
        $lines = $this->schedule->lines($heat, $round);
        $names = Material::whereIn('id', $lines->pluck('material_id')->all())->pluck('name', 'id');

        return $lines->take(5)->map(fn ($line) => Formats::number((float) $line['actual_qty_kg'], 0).' '.Str::lower((string) ($names[$line['material_id']] ?? 'material')))
            ->implode(' · ');
    }

    /**
     * The metal stands: charged, tapped, poured, left and unaccounted.
     *
     * @return array<string, mixed>
     */
    public function balance(Heat $heat): array
    {
        return $this->balance->for($heat);
    }

    private function minutes(int $minutes): string
    {
        return $minutes === 1 ? '1 min' : $minutes.' min';
    }
}
