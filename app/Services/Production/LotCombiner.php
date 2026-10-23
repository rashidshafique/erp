<?php

namespace App\Services\Production;

use App\Models\CastingLot;
use App\Models\CastingLotStageEvent;
use App\Models\QualityInspection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Combines lots of the same heat, status, order and revision that have passed the same stages into one lot. The oldest lot
 * (lowest code) is kept: counts are added up, each stage and each kind of inspection becomes one record with the lots' counts
 * added up, and the moulds, NCRs and other records move to it. The other lots are deleted.
 */
class LotCombiner
{
    /** Statuses whose pieces are packed or gone; their lots are not combined. */
    public const FIXED_STATUSES = ['PACKED', 'DISPATCHED', 'CLOSED'];

    /** Records that point at a lot and simply move to the kept one. */
    private const MOVED_TABLES = ['nonconformance_reports', 'casting_lot_pour_mould_assembly', 'packing_record_items', 'product_deviations', 'process_measurements', 'process_control_events'];

    public function __construct(private LotService $lots) {}

    /**
     * @param  Collection<int, CastingLot>  $lots
     * @return array{kept: array<int, string>, combined: int, skipped: int}
     */
    public function combine(Collection $lots): array
    {
        $lots = $lots->reject(fn (CastingLot $lot) => in_array($lot->status, self::FIXED_STATUSES, true))
            ->load('pourMouldAssembly.pour', 'stageEvents');
        $groups = $lots->groupBy(fn (CastingLot $lot) => $this->groupKey($lot));
        $kept = [];
        $combined = 0;

        DB::transaction(function () use ($groups, &$kept, &$combined) {
            foreach ($groups->filter(fn ($group) => $group->count() > 1) as $group) {
                $keep = $this->combineGroup($group->sortBy(fn (CastingLot $lot) => $this->codeOrder($lot))->values());
                $kept[] = $keep->label;
                $combined += $group->count();
            }
        });

        return ['kept' => $kept, 'combined' => $combined, 'skipped' => $lots->count() - $combined];
    }

    /** Lots combine only when they came from the same heat for the same order and revision, share a status and passed the same stages. */
    private function groupKey(CastingLot $lot): string
    {
        $stages = $lot->stageEvents->pluck('stage_definition_id')->unique()->sort()->implode(',');

        return implode('|', [$lot->pourMouldAssembly?->pour?->heat_id, $lot->status, $lot->production_order_id, $lot->product_revision_id, $stages]);
    }

    /** Running number of the code (YYMMDD-NN), so -9 comes before -10; lots without a code go by lot number. */
    private function codeOrder(CastingLot $lot): string
    {
        return $lot->code
            ? substr($lot->code, 0, strrpos($lot->code, '-')).str_pad(substr($lot->code, strrpos($lot->code, '-') + 1), 6, '0', STR_PAD_LEFT)
            : 'z'.$lot->lot_number;
    }

    /** @param  Collection<int, CastingLot>  $group  oldest first */
    private function combineGroup(Collection $group): CastingLot
    {
        $keep = $group->first();
        $ids = $group->pluck('id')->all();
        $otherIds = array_slice($ids, 1);

        $this->combineStageEvents($keep, $ids);
        $this->combineInspections($keep, $ids);
        foreach (self::MOVED_TABLES as $table) {
            DB::table($table)->whereIn('casting_lot_id', $otherIds)->update(['casting_lot_id' => $keep->id]);
        }

        $gross = $group->pluck('gross_weight_kg')->filter(fn ($kg) => $kg !== null);
        $net = $group->pluck('net_casting_weight_kg')->filter(fn ($kg) => $kg !== null);
        $notes = $group->flatMap(fn (CastingLot $lot) => preg_split('/\R/', (string) $lot->notes))->map(fn ($line) => trim($line))->filter()->unique()->values()
            ->push(now()->displayShortDateTime().' combined '.$group->count().' lots: '.$group->pluck('label')->implode(', '));

        CastingLot::whereIn('id', $otherIds)->delete();
        $keep->update([
            'quantity_poured' => (int) $group->sum('quantity_poured'),
            'gross_weight_kg' => $gross->isEmpty() ? null : round((float) $gross->sum(), 3),
            'net_casting_weight_kg' => $net->isEmpty() ? null : round((float) $net->sum(), 3),
            'created_from_pour_at' => $group->pluck('created_from_pour_at')->filter()->min(),
            'notes' => $notes->implode("\n"),
        ]);

        return $this->lots->refresh($keep);
    }

    /**
     * One event per stage. A lot recorded at a stage more than once counts only its latest pieces there; rejections are all
     * added up, as they are for the lot's count.
     *
     * @param  array<int, string>  $ids
     */
    private function combineStageEvents(CastingLot $keep, array $ids): void
    {
        $events = CastingLotStageEvent::whereIn('casting_lot_id', $ids)->orderBy('created_at')->get();
        foreach ($events->groupBy('stage_definition_id') as $stageEvents) {
            $latest = $stageEvents->groupBy('casting_lot_id')->map(fn ($lotEvents) => $lotEvents->last());
            $target = $latest[$keep->id] ?? $latest->first();
            $target->update([
                'casting_lot_id' => $keep->id,
                'quantity_in' => (int) $latest->sum('quantity_in'),
                'quantity_accepted' => (int) $latest->sum('quantity_accepted'),
                'quantity_rejected' => (int) $stageEvents->sum('quantity_rejected'),
                'quantity_out' => (int) $latest->sum('quantity_out'),
                'started_at' => $stageEvents->pluck('started_at')->filter()->min(),
                'completed_at' => $stageEvents->pluck('completed_at')->filter()->max(),
                'rejection_reason' => $stageEvents->pluck('rejection_reason')->filter()->unique()->implode('; ') ?: null,
            ]);
            CastingLotStageEvent::whereIn('id', $stageEvents->pluck('id')->reject(fn ($id) => $id === $target->id))->delete();
        }
    }

    /**
     * One inspection per type, status and outcome, so an open inspection stays open and an accept stays apart from a reject.
     * Results and NCRs of the dropped inspections move to the one kept.
     *
     * @param  array<int, string>  $ids
     */
    private function combineInspections(CastingLot $keep, array $ids): void
    {
        $inspections = QualityInspection::whereIn('casting_lot_id', $ids)->orderBy('created_at')->get();
        foreach ($inspections->groupBy(fn ($i) => implode('|', [$i->inspection_type, $i->stage_definition_id, $i->status, $i->overall_disposition])) as $same) {
            $latest = $same->groupBy('casting_lot_id')->map(fn ($lotInspections) => $lotInspections->last());
            $target = $latest[$keep->id] ?? $latest->first();
            $target->update([
                'casting_lot_id' => $keep->id,
                'sample_size' => (int) $latest->sum('sample_size'),
                'quantity_inspected' => (int) $latest->sum('quantity_inspected'),
                'quantity_accepted' => (int) $latest->sum('quantity_accepted'),
                'quantity_rejected' => (int) $same->sum('quantity_rejected'),
                'quantity_rework' => (int) $latest->sum('quantity_rework'),
                'quantity_pending' => (int) $latest->sum('quantity_pending'),
                'inspected_at' => $same->pluck('inspected_at')->filter()->max(),
            ]);
            $dropped = $same->pluck('id')->reject(fn ($id) => $id === $target->id)->values();
            DB::table('nonconformance_reports')->whereIn('quality_inspection_id', $dropped)->update(['quality_inspection_id' => $target->id]);
            DB::table('quality_inspection_results')->whereIn('quality_inspection_id', $dropped)->update(['quality_inspection_id' => $target->id]);
            QualityInspection::whereIn('id', $dropped)->delete();
        }
    }
}
