<?php

namespace App\Services\Quality;

use App\Models\CastingLot;
use App\Models\QualityDefect;
use App\Models\QualityDefectMark;
use App\Models\QualityInspection;
use App\Models\QualityInspectionResult;
use App\Services\Production\LotService;
use App\Support\QualityRules;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * One defect found on a lot, logged on the spot: an in-process inspection of those pieces, the defect and where on the casting,
 * and a nonconformance when the rule says so. Rejected pieces come off the lot's good count.
 */
class LotDefectLog
{
    public function __construct(private LotService $lots, private QuickInspection $quick) {}

    public function log(CastingLot $lot, QualityDefect $defect, int $quantity, string $disposition, ?string $location, ?string $by): QualityDefectMark
    {
        if (! array_key_exists($disposition, QualityDefectMark::DISPOSITIONS)) {
            throw new InvalidArgumentException('Choose reject or rework.');
        }
        if ($quantity < 1) {
            throw new InvalidArgumentException('Enter how many castings have this defect.');
        }
        if (in_array($lot->status, ['CLOSED', 'SCRAPPED', 'DISPATCHED'], true)) {
            throw new InvalidArgumentException("The lot is {$lot->status}; no more defects can be logged on it.");
        }

        return DB::transaction(function () use ($lot, $defect, $quantity, $disposition, $location, $by) {
            $lot = CastingLot::whereKey($lot->id)->lockForUpdate()->firstOrFail();
            $left = $this->lots->pieces($lot);
            if ($quantity > $left) {
                throw new InvalidArgumentException("Only {$left} casting(s) of this lot are left; {$quantity} were entered.");
            }
            $reject = $disposition === 'REJECT';
            $inspection = QualityInspection::create([
                'inspection_number' => $this->quick->nextInspectionNumber(), 'casting_lot_id' => $lot->id, 'inspection_type' => 'IN_PROCESS', 'status' => 'COMPLETED',
                'overall_disposition' => $reject ? 'REJECT' : 'REWORK', 'sample_size' => $quantity, 'quantity_inspected' => $quantity,
                'quantity_accepted' => 0, 'quantity_rejected' => $reject ? $quantity : 0, 'quantity_rework' => $reject ? 0 : $quantity, 'quantity_pending' => 0,
                'inspector_name' => $by, 'inspected_at' => now(), 'remarks' => 'Defect logged on the phone',
            ]);
            QualityInspectionResult::create([
                'quality_inspection_id' => $inspection->id, 'sequence' => 1, 'test_code' => substr((string) $defect->code, 0, 50), 'test_name' => substr($defect->name.' × '.$quantity, 0, 150),
                'result_type' => 'PASS_FAIL', 'passed' => false, 'quality_defect_id' => $defect->id, 'text_result' => (string) $quantity, 'tested_by' => $by, 'tested_at' => now(),
            ]);
            $mark = QualityDefectMark::create([
                'quality_inspection_id' => $inspection->id, 'casting_lot_id' => $lot->id, 'product_revision_id' => $lot->product_revision_id, 'quality_defect_id' => $defect->id,
                'location' => $location, 'quantity' => $quantity, 'disposition' => $disposition,
            ]);
            if (QualityRules::raisesNcr($quantity, $quantity)) {
                $this->quick->raiseNcr($inspection, $lot, $defect, $quantity, $reject, $by, "Logged on the phone: {$quantity} × {$defect->name}".($location ? ", {$location}" : '').', '.strtolower(QualityDefectMark::DISPOSITIONS[$disposition]).'.');
            }
            $this->lots->refresh($lot);
            $mark->setRelation('inspection', $inspection);

            return $mark;
        });
    }
}
