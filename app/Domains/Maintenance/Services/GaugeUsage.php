<?php

namespace App\Domains\Maintenance\Services;

use App\Domains\Maintenance\Models\Gauge;
use App\Models\HeatChemistryReading;
use App\Models\HeatTreatment;
use App\Models\MeasurementSet;
use App\Models\Pour;
use App\Models\QualityInspectionResult;
use App\Models\ThermalAnalysisTest;
use App\Support\Formats;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Every result measured with one instrument, newest first, so a failed calibration can be traced to the results it puts in doubt. */
class GaugeUsage
{
    /** @return Collection<int, array{at: CarbonInterface, what: string, record: string, value: ?string, url: string}> */
    public function results(Gauge $gauge, int $limit = 100): Collection
    {
        $chemistry = HeatChemistryReading::with('heat')->where('gauge_id', $gauge->id)->latest()->limit($limit)->get()
            ->map(fn (HeatChemistryReading $r) => ['at' => $r->tested_at ?? $r->created_at, 'what' => 'Chemistry', 'record' => $r->heat?->heat_number.' · analysis '.$r->reading_number,
                'value' => collect(['C' => $r->carbon_percent, 'Si' => $r->silicon_percent])->filter(fn ($v) => $v !== null)->map(fn ($v, $k) => "{$k} {$v}")->implode(', ') ?: null,
                'url' => route('heats.show', $r->heat_id)]);

        $thermal = ThermalAnalysisTest::with('heat')->where('gauge_id', $gauge->id)->latest()->limit($limit)->get()
            ->map(fn (ThermalAnalysisTest $t) => ['at' => $t->taken_at ?? $t->created_at, 'what' => 'Thermal analysis', 'record' => $t->heat?->heat_number ?? $t->sample_reference ?? '—',
                'value' => $t->tl_c !== null ? 'TL '.$t->tl_c.' °C' : null, 'url' => route('thermal.show', $t)]);

        $inspections = QualityInspectionResult::with('inspection')->where('gauge_id', $gauge->id)->latest('updated_at')->limit($limit)->get()
            ->map(fn (QualityInspectionResult $r) => ['at' => $r->tested_at ?? $r->updated_at, 'what' => 'Inspection', 'record' => $r->inspection?->inspection_number.' · '.$r->test_name,
                'value' => $r->measured_value !== null ? trim($r->measured_value.' '.$r->unit) : null, 'url' => route('inspections.show', $r->quality_inspection_id)]);

        $tests = MeasurementSet::with('heat')->where('gauge_id', $gauge->id)->latest('taken_at')->limit($limit)->get()
            ->map(fn (MeasurementSet $s) => ['at' => $s->taken_at, 'what' => Measurements::KINDS[$s->kind] ?? $s->kind, 'record' => $s->heat?->heat_number ?? ($s->sample_reference ?: 'Sand system'),
                'value' => $s->result, 'url' => $s->heat_id ? route('heats.show', $s->heat_id) : route('sand-tests.index', ['system' => $s->sand_system_id])]);

        $pours = Pour::where('gauge_id', $gauge->id)->latest('completed_at')->limit($limit)->get()
            ->map(fn (Pour $p) => ['at' => $p->completed_at ?? $p->updated_at, 'what' => 'Pour temperature', 'record' => $p->pour_number,
                'value' => $p->pour_temperature_c !== null ? Formats::number((float) $p->pour_temperature_c).' °C' : null, 'url' => route('pours.show', $p)]);

        $treatments = HeatTreatment::with('heat')->where('gauge_id', $gauge->id)->latest('treated_at')->limit($limit)->get()
            ->map(fn (HeatTreatment $t) => ['at' => $t->treated_at ?? $t->created_at, 'what' => 'Treatment temperature', 'record' => $t->heat?->heat_number.' · '.(HeatTreatment::TYPES[$t->treatment_type] ?? $t->treatment_type),
                'value' => $t->temperature_c !== null ? Formats::number($t->temperature_c).' °C' : null, 'url' => route('heats.show', $t->heat_id)]);

        return collect([$chemistry, $thermal, $inspections, $tests, $pours, $treatments])->flatten(1)->sortByDesc('at')->take($limit)->values();
    }

    /** When the latest calibration failed: the date of the last good one before it, from which results are in doubt. Null when there is nothing to doubt. */
    public function doubtfulSince(Gauge $gauge): ?CarbonInterface
    {
        $calibrations = $gauge->calibrations;
        if ($calibrations->first()?->result !== 'FAIL') {
            return null;
        }

        return $calibrations->firstWhere('result', '!==', 'FAIL')?->calibrated_on ?? $gauge->created_at->copy()->startOfDay();
    }
}
