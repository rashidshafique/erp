<?php

namespace App\Http\Controllers;

use App\Models\CastingLot;
use App\Models\PackingRecordItem;
use App\Models\ProductionStageDefinition;
use App\Services\Production\LotService;
use App\Services\Production\ShipmentService;
use App\Support\QueueRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class LotQueueController extends Controller
{
    /**
     * Where every open lot stands, as a line of steps in process order: waiting for shakeout, after each active stage, ready
     * to pack. Each step carries its totals so the flow strip and the board can show how much work sits where.
     */
    public function queue(Request $request, LotService $svc): View
    {
        $lots = CastingLot::with(['product', 'stageEvents.stage', 'pourMouldAssembly.pour', 'pourMouldAssemblies.mouldAssembly', 'productionOrder'])
            ->whereIn('status', ['IN_PRODUCTION', 'ON_HOLD', 'READY'])->get();

        $steps = [];
        foreach (QueueRules::steps() as $key => $label) {
            $steps[$key] = $this->step($key, $label);
        }

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
                'lot' => $lot, 'last_seq' => (int) ($last?->sequence ?? 0), 'pieces' => $svc->pieces($lot), 'poured' => $poured, 'age_h' => $poured ? (int) $poured->diffInHours(now()) : null,
                'cool' => $svc->cooling($lot), 'urgent' => $lot->productionOrder?->required_date,
                'moulds' => $lot->pourMouldAssemblies->map(fn ($row) => ['number' => $row->mouldAssembly?->assembly_number ?? '—', 'pieces' => (int) $row->pivot->quantity])->all(),
            ];
        }

        [$amber, $red, $byPieces] = [QueueRules::amberHours(), QueueRules::redHours(), QueueRules::countsPieces()];
        foreach ($steps as &$step) {
            usort($step['lots'], fn ($x, $y) => ($y['age_h'] ?? -1) <=> ($x['age_h'] ?? -1));
            $step['lot_count'] = count($step['lots']);
            $step['pieces'] = array_sum(array_column($step['lots'], 'pieces'));
            $step['oldest_h'] = $step['lots'][0]['age_h'] ?? null;
            $step['limit'] = QueueRules::wipLimit($step['key']);
            $step['limit_unit'] = $byPieces ? 'pcs' : 'lots';
            $step['over_limit'] = $step['limit'] !== null && ($byPieces ? $step['pieces'] : $step['lot_count']) > $step['limit'];
            $step['tone'] = match (true) {
                $step['lot_count'] === 0 => 'empty',
                $step['over_limit'], $step['oldest_h'] !== null && $step['oldest_h'] >= $red => 'red',
                $step['oldest_h'] !== null && $step['oldest_h'] >= $amber => 'amber',
                default => 'green',
            };
        }
        unset($step);

        $awaitingDispatch = Route::has('finished-goods.index')
            ? PackingRecordItem::with(['lot.product', 'packing'])->get()->filter(fn ($i) => app(ShipmentService::class)->undispatched($i) > 0)->count()
            : 0;

        return view('lots.queue', [
            'stages' => ProductionStageDefinition::where('is_active', true)->where('code', '!=', 'FINAL_INSPECTION')->orderBy('sequence')->get(),
            'steps' => array_values($steps),
            'flow' => $this->flow($steps),
            'openLots' => $lots->count(),
            'view' => $request->query('view') === 'board' ? 'board' : 'list',
            'awaitingDispatch' => $awaitingDispatch,
        ]);
    }

    /**
     * The flow strip: one block per active stage with the lots waiting for it and the lots done with it, then a block for every step
     * that belongs to no active stage (after a stage since switched off, ready to pack). A block takes the worst tone of its sides.
     *
     * @param  array<string, array<string, mixed>>  $steps
     * @return list<array{label: string, tone: string, sides: list<array{word: ?string, step: array<string, mixed>}>}>
     */
    private function flow(array $steps): array
    {
        $blocks = [];
        $before = 'waiting';
        $used = [];
        foreach (ProductionStageDefinition::where('is_active', true)->orderBy('sequence')->get() as $stage) {
            $after = 'after-'.$stage->code;
            $blocks[] = ['label' => $stage->name, 'sides' => [['word' => 'waiting', 'step' => $steps[$before]], ['word' => 'done', 'step' => $steps[$after]]]];
            $used[$before] = $used[$after] = true;
            $before = $after;
        }
        foreach ($steps as $key => $step) {
            if (! isset($used[$key])) {
                $blocks[] = ['label' => $step['label'], 'sides' => [['word' => null, 'step' => $step]]];
            }
        }
        $rank = ['empty' => 0, 'green' => 1, 'amber' => 2, 'red' => 3];

        return array_map(function (array $block) use ($rank): array {
            $block['tone'] = collect($block['sides'])->pluck('step.tone')->sortByDesc(fn ($t) => $rank[$t])->first();

            return $block;
        }, $blocks);
    }

    /** @return array{key: string, label: string, lots: list<array<string, mixed>>} */
    private function step(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label, 'lots' => []];
    }
}
