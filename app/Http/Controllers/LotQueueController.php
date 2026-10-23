<?php

namespace App\Http\Controllers;

use App\Models\CastingLot;
use App\Models\PackingRecordItem;
use App\Models\ProductionStageDefinition;
use App\Services\Production\LotService;
use App\Services\Production\ShipmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class LotQueueController extends Controller
{
    /** Hours since the pour after which the oldest lot in a step turns the step amber, then red. */
    public const AMBER_AFTER_HOURS = 24;

    public const RED_AFTER_HOURS = 48;

    /**
     * Where every open lot stands, as a line of steps in process order: waiting for shakeout, after each active stage, ready
     * to pack. Each step carries its totals so the flow strip and the board can show how much work sits where.
     */
    public function queue(Request $request, LotService $svc): View
    {
        $lots = CastingLot::with(['product', 'stageEvents.stage', 'pourMouldAssembly.pour', 'pourMouldAssemblies.mouldAssembly', 'productionOrder'])
            ->whereIn('status', ['IN_PRODUCTION', 'ON_HOLD', 'READY'])->get();
        $activeStages = ProductionStageDefinition::where('is_active', true)->orderBy('sequence')->get();

        $steps = ['waiting' => $this->step('waiting', 'Waiting for shakeout')];
        foreach ($activeStages as $stage) {
            $steps['after-'.$stage->code] = $this->step('after-'.$stage->code, 'After '.$stage->name);
        }
        $steps['ready'] = $this->step('ready', 'Ready to pack');

        foreach ($lots as $lot) {
            $last = $lot->stageEvents->sortBy(fn ($e) => $e->stage?->sequence)->last()?->stage;
            $key = match (true) {
                $lot->status === 'READY' => 'ready',
                $last !== null => 'after-'.$last->code,
                default => 'waiting',
            };
            // A lot after a stage that has since been switched off still shows, in its own step just before Ready to pack.
            if (! isset($steps[$key])) {
                $ready = $steps['ready'];
                unset($steps['ready']);
                $steps[$key] = $this->step($key, 'After '.$last->name);
                $steps['ready'] = $ready;
            }

            $poured = $lot->pourMouldAssembly?->pour?->completed_at;
            $steps[$key]['lots'][] = [
                'lot' => $lot, 'pieces' => $svc->pieces($lot), 'poured' => $poured, 'age_h' => $poured ? (int) $poured->diffInHours(now()) : null,
                'cool' => $svc->cooling($lot), 'urgent' => $lot->productionOrder?->required_date,
                'moulds' => $lot->pourMouldAssemblies->map(fn ($row) => ['number' => $row->mouldAssembly?->assembly_number ?? '—', 'pieces' => (int) $row->pivot->quantity])->all(),
            ];
        }

        foreach ($steps as &$step) {
            usort($step['lots'], fn ($x, $y) => ($y['age_h'] ?? -1) <=> ($x['age_h'] ?? -1));
            $step['lot_count'] = count($step['lots']);
            $step['pieces'] = array_sum(array_column($step['lots'], 'pieces'));
            $step['oldest_h'] = $step['lots'][0]['age_h'] ?? null;
            $step['tone'] = match (true) {
                $step['lot_count'] === 0 => 'empty',
                $step['oldest_h'] !== null && $step['oldest_h'] >= self::RED_AFTER_HOURS => 'red',
                $step['oldest_h'] !== null && $step['oldest_h'] >= self::AMBER_AFTER_HOURS => 'amber',
                default => 'green',
            };
        }
        unset($step);

        $awaitingDispatch = Route::has('finished-goods.index')
            ? PackingRecordItem::with(['lot.product', 'packing'])->get()->filter(fn ($i) => app(ShipmentService::class)->undispatched($i) > 0)->count()
            : 0;

        return view('lots.queue', [
            'stages' => $activeStages->where('code', '!=', 'FINAL_INSPECTION')->values(),
            'steps' => array_values($steps),
            'openLots' => $lots->count(),
            'view' => $request->query('view') === 'board' ? 'board' : 'list',
            'awaitingDispatch' => $awaitingDispatch,
        ]);
    }

    /** @return array{key: string, label: string, lots: list<array<string, mixed>>} */
    private function step(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label, 'lots' => []];
    }
}
