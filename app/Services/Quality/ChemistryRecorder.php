<?php

namespace App\Services\Quality;

use App\Models\Heat;
use App\Models\HeatChemistryReading;
use Illuminate\Support\Facades\DB;

/** Saves one chemistry reading of a heat, numbered in order and judged against the heat's iron grade. */
class ChemistryRecorder
{
    public function __construct(private ChemistryJudge $judge) {}

    /**
     * @param  array<string, mixed>  $data  reading columns (element percents, stage, method, references, tested_at, notes)
     * @return array{reading: HeatChemistryReading, verdict: array{ok: ?bool, findings: array<int, string>}}
     */
    public function record(Heat $heat, array $data): array
    {
        $heat->loadMissing('ironGrade');
        $ce = $this->judge->carbonEquivalent($data);
        $verdict = $this->judge->judge($heat->ironGrade, $data, $ce);

        $reading = DB::transaction(function () use ($heat, $data, $ce, $verdict) {
            Heat::whereKey($heat->id)->lockForUpdate()->first();
            $next = (int) HeatChemistryReading::where('heat_id', $heat->id)->max('reading_number') + 1;

            return HeatChemistryReading::create($data + [
                'heat_id' => $heat->id,
                'reading_number' => $next,
                'carbon_equivalent' => $ce,
                'is_accepted' => $verdict['ok'],
                'tested_at' => $data['tested_at'] ?? now(),
            ]);
        });

        return ['reading' => $reading, 'verdict' => $verdict];
    }
}
