<?php

namespace App\Services\Quality;

use App\Models\CastingLot;
use App\Models\InspectionPlan;
use App\Models\NonconformanceReport;
use App\Models\QualityDefect;
use App\Models\QualityDefectMark;
use App\Models\QualityInspection;
use App\Models\QualityInspectionResult;
use App\Services\Production\AfterPour;
use App\Services\Production\LotService;
use App\Support\QualityRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The quick QC tally: castings are counted good, rework or rejected per product, and the counts are shared out over that
 * product's open lots oldest first. Each lot touched gets a completed inspection, so lot counts, orders and packing work as usual.
 */
class QuickInspection
{
    public function __construct(private LotService $lots) {}

    /**
     * Open lots of each product revision with pieces still to check, oldest first.
     *
     * @return Collection<int, Collection<int, CastingLot>> keyed by product revision id
     */
    public function waiting(): Collection
    {
        return CastingLot::with(['product', 'productRevision'])->whereIn('status', ['IN_PRODUCTION', 'READY'])
            ->orderBy('created_at')->get()
            ->each(fn (CastingLot $lot) => $lot->setAttribute('to_check', $this->toCheck($lot)))
            ->filter(fn (CastingLot $lot) => $lot->to_check > 0)
            ->groupBy('product_revision_id');
    }

    /**
     * Pieces of the lot not yet part of any inspection: those through every step before inspection, or, when quality control
     * does not wait for the steps, those not rejected at a stage.
     */
    public function toCheck(CastingLot $lot): int
    {
        $ready = QualityRules::qcWaitsForSteps() ? app(AfterPour::class)->progress($lot)['done'] : (int) $lot->quantity_poured - (int) $lot->stageEvents()->sum('quantity_rejected');

        return max(0, $ready - (int) $lot->inspections()->sum('quantity_inspected'));
    }

    public function hasApprovedPlan(int|string $productRevisionId): bool
    {
        return InspectionPlan::where('product_revision_id', $productRevisionId)->where('status', 'APPROVED')
            ->where('plan_type', 'FINAL_INSPECTION')->whereNull('superseded_at')->exists();
    }

    /**
     * @param  array<string, int>  $defects  quality defect id => rejected pieces with that defect (optional split of $rejected)
     * @param  list<array{defect_id: string, quantity: int, disposition: string, pattern_revision_id?: ?string, cavity_number?: ?int, location?: ?string, piece_ids?: ?string}>  $marks
     *                                                                                                                                                                         defects marked by cavity and where, rejected or to rework
     * @return array{inspections: int, ncrs: int}
     */
    public function record(string $productRevisionId, int $accepted, int $rejected, int $rework, array $defects = [], ?string $inspector = null, array $marks = []): array
    {
        $total = $accepted + $rejected + $rework;
        if ($total <= 0) {
            throw new InvalidArgumentException('Enter how many castings were checked.');
        }
        foreach ($defects as $id => $n) {
            if ($n > 0) {
                $marks[] = ['defect_id' => $id, 'quantity' => (int) $n, 'disposition' => 'REJECT'];
            }
        }
        $marks = array_values(array_filter($marks, fn ($m) => (int) $m['quantity'] > 0));
        $this->checkMarks($marks, $rejected, $rework);

        return DB::transaction(function () use ($productRevisionId, $accepted, $rejected, $rework, $marks, $inspector, $total) {
            $lots = CastingLot::where('product_revision_id', $productRevisionId)->whereIn('status', ['IN_PRODUCTION', 'READY'])
                ->orderBy('created_at')->lockForUpdate()->get();
            $available = $lots->sum(fn (CastingLot $lot) => $this->toCheck($lot));
            if ($total > $available) {
                throw new InvalidArgumentException("Only {$available} casting(s) of this product are left to check; {$total} were entered.");
            }

            $raiseNcr = QualityRules::raisesNcr($rejected + $rework, $total);
            $left = ['accepted' => $accepted, 'rework' => $rework, 'rejected' => $rejected];
            $marksLeft = $marks;
            $made = 0;
            $ncrs = 0;

            foreach ($lots as $lot) {
                $take = min($this->toCheck($lot), $left['accepted'] + $left['rework'] + $left['rejected']);
                if ($take <= 0) {
                    continue;
                }
                $part = [];
                foreach (['accepted', 'rework', 'rejected'] as $kind) {
                    $part[$kind] = min($left[$kind], $take);
                    $left[$kind] -= $part[$kind];
                    $take -= $part[$kind];
                }

                $lotMarks = [];
                $room = ['REJECT' => $part['rejected'], 'REWORK' => $part['rework']];
                foreach ($marksLeft as $i => $mark) {
                    $use = min((int) $mark['quantity'], $room[$mark['disposition']]);
                    if ($use > 0) {
                        $lotMarks[] = ['quantity' => $use] + $mark;
                        $marksLeft[$i]['quantity'] -= $use;
                        $room[$mark['disposition']] -= $use;
                    }
                }
                $lotDefects = collect($lotMarks)->groupBy('defect_id')->map(fn ($m) => (int) $m->sum('quantity'))->all();

                $checked = $part['accepted'] + $part['rework'] + $part['rejected'];
                $inspection = QualityInspection::create([
                    'inspection_number' => $this->nextInspectionNumber(),
                    'casting_lot_id' => $lot->id, 'inspection_type' => 'FINAL', 'status' => 'COMPLETED',
                    'overall_disposition' => $this->disposition($part), 'sample_size' => $checked, 'quantity_inspected' => $checked,
                    'quantity_accepted' => $part['accepted'], 'quantity_rejected' => $part['rejected'], 'quantity_rework' => $part['rework'], 'quantity_pending' => 0,
                    'inspector_name' => $inspector, 'inspected_at' => now(), 'remarks' => 'Quick QC tally',
                ]);
                $sequence = 0;
                foreach ($lotDefects as $defectId => $n) {
                    $defect = QualityDefect::find($defectId);
                    QualityInspectionResult::create([
                        'quality_inspection_id' => $inspection->id, 'sequence' => ++$sequence, 'test_code' => substr((string) $defect?->code, 0, 50),
                        'test_name' => substr(($defect?->name ?? 'Defect').' × '.$n, 0, 150), 'result_type' => 'PASS_FAIL', 'passed' => false,
                        'quality_defect_id' => $defectId, 'text_result' => (string) $n, 'tested_by' => $inspector, 'tested_at' => now(),
                    ]);
                }

                foreach ($lotMarks as $mark) {
                    QualityDefectMark::create([
                        'quality_inspection_id' => $inspection->id, 'casting_lot_id' => $lot->id, 'product_revision_id' => $productRevisionId, 'quality_defect_id' => $mark['defect_id'],
                        'pattern_revision_id' => $mark['pattern_revision_id'] ?? null, 'cavity_number' => $mark['cavity_number'] ?? null, 'location' => $mark['location'] ?? null,
                        'quantity' => $mark['quantity'], 'disposition' => $mark['disposition'], 'piece_ids' => $mark['piece_ids'] ?? null,
                    ]);
                }

                $ncr = null;
                if ($raiseNcr && $part['rejected'] + $part['rework'] > 0) {
                    $firstDefect = $lotDefects ? QualityDefect::find(array_key_first($lotDefects)) : null;
                    $ncr = $this->raiseNcr($inspection, $lot, $firstDefect, $part['rejected'] + $part['rework'], $part['rejected'] > 0, $inspector,
                        'Quick QC: '.$part['rejected'].' rejected, '.$part['rework'].' to rework out of '.$checked.' checked.');
                    $ncrs++;
                }

                $this->lots->refresh($lot);
                $fresh = $lot->fresh();
                if ($fresh->status === 'IN_PRODUCTION' && ! $ncr && $fresh->quantity_good > 0) {
                    $fresh->update(['status' => 'READY']);
                }
                $made++;
            }

            return ['inspections' => $made, 'ncrs' => $ncrs];
        });
    }

    /**
     * Defect marks cannot add up to more than the pieces rejected or sent to rework, and under the piece ID rule each mark names
     * its pieces.
     *
     * @param  list<array{quantity: int, disposition: string, piece_ids?: ?string}>  $marks
     */
    private function checkMarks(array $marks, int $rejected, int $rework): void
    {
        $sum = fn (string $disposition) => (int) collect($marks)->where('disposition', $disposition)->sum('quantity');
        if ($sum('REJECT') > $rejected) {
            throw new InvalidArgumentException('The defect counts add up to more than the rejected castings.');
        }
        if ($sum('REWORK') > $rework) {
            throw new InvalidArgumentException('The defect counts add up to more than the castings sent to rework.');
        }
        if (QualityRules::get('defect_piece_ids') === 'REQUIRED') {
            foreach ($marks as $mark) {
                if (count(self::pieceIds($mark['piece_ids'] ?? null)) !== (int) $mark['quantity']) {
                    throw new InvalidArgumentException("Each defect line needs one piece ID per piece: {$mark['quantity']} on one line.");
                }
            }
        }
    }

    /** @return list<string> piece IDs typed with commas, spaces or new lines between them */
    public static function pieceIds(?string $text): array
    {
        return array_values(array_filter(preg_split('/[\s,;]+/', (string) $text) ?: [], fn ($id) => $id !== ''));
    }

    public function nextInspectionNumber(): string
    {
        $date = now()->format('ymd');
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['inspection-number-'.$date]);
        $prefix = "INS-{$date}-";
        $n = (QualityInspection::where('inspection_number', 'like', $prefix.'%')->pluck('inspection_number')->map(fn ($x) => (int) substr($x, strlen($prefix)))->max() ?? 0) + 1;

        return $prefix.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }

    public function raiseNcr(QualityInspection $inspection, CastingLot $lot, ?QualityDefect $defect, int $affected, bool $anyRejected, ?string $reportedBy, string $description, ?string $title = null): NonconformanceReport
    {
        $date = now()->format('ymd');
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['ncr-number-'.$date]);
        $prefix = "NCR-{$date}-";
        $n = (NonconformanceReport::where('ncr_number', 'like', $prefix.'%')->pluck('ncr_number')->map(fn ($x) => (int) substr($x, strlen($prefix)))->max() ?? 0) + 1;

        return NonconformanceReport::create([
            'ncr_number' => $prefix.str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'casting_lot_id' => $lot->id,
            'quality_inspection_id' => $inspection->id, 'quality_defect_id' => $defect?->id,
            'title' => $title ?: ($defect ? $defect->name : 'Failed inspection').' — '.$lot->lot_number,
            'description' => $description,
            'severity' => $defect->default_severity ?? ($anyRejected ? 'MAJOR' : 'MINOR'), 'status' => 'OPEN',
            'quantity_affected' => $affected, 'reported_by' => $reportedBy, 'reported_at' => now(),
        ]);
    }

    /** @param array{accepted: int, rework: int, rejected: int} $part */
    private function disposition(array $part): string
    {
        return match (true) {
            $part['rejected'] + $part['rework'] === 0 => 'ACCEPT',
            $part['accepted'] === 0 && $part['rework'] === 0 => 'REJECT',
            $part['accepted'] === 0 && $part['rejected'] === 0 => 'REWORK',
            default => 'PARTIAL',
        };
    }
}
