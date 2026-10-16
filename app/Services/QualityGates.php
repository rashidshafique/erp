<?php

namespace App\Services;

use App\Models\CastingLot;
use App\Models\Heat;
use App\Models\ThermalWindow;
use Illuminate\Support\Facades\DB;

/**
 * Stage gates that stop a heat from being poured, or a lot from shipping, without evidence.
 * Heats without an iron grade are legacy records and are not gated.
 */
class QualityGates
{
    /** @return array{ok: bool, reasons: array<int, string>, checks: array<int, array{label: string, ok: bool|null, detail: string}>} */
    public function pourGate(Heat $heat): array
    {
        $checks = [];
        $grade = $heat->ironGrade;
        if (! config('foundry.enforce_thermal_gate') || ! $grade) {
            return ['ok' => true, 'reasons' => [], 'checks' => [['label' => 'Gate', 'ok' => null, 'detail' => $grade ? 'Thermal gate is switched off.' : 'The heat has no iron grade, so no gate applies.']]];
        }

        // Chemistry
        $chem = $heat->chemistryReadings()->orderByDesc('reading_number')->first();
        $hasChemWindow = collect(array_keys(\App\Models\IronGrade::ELEMENTS))->contains(fn ($e) => $grade->{$e.'_min'} !== null || $grade->{$e.'_max'} !== null) || $grade->ce_min !== null || $grade->ce_max !== null;
        if ($hasChemWindow) {
            if (! $chem) {
                $checks[] = ['label' => 'Chemistry', 'ok' => false, 'detail' => 'No chemistry reading is recorded for the heat.'];
            } else {
                $f = $grade->chemistryFindings([
                    'c' => $chem->carbon_percent, 'si' => $chem->silicon_percent, 'mn' => $chem->manganese_percent, 'p' => $chem->phosphorus_percent,
                    's' => $chem->sulphur_percent, 'cr' => $chem->chromium_percent, 'cu' => $chem->copper_percent, 'mo' => $chem->molybdenum_percent,
                    'mg' => $chem->magnesium_percent, 'ce' => $chem->carbon_equivalent,
                ]);
                $checks[] = ['label' => 'Chemistry', 'ok' => $f === [], 'detail' => $f ? implode(' ', $f) : "Reading {$chem->reading_number} is within the {$grade->code} window."];
            }
        }

        // Thermal analysis
        $window = ThermalWindow::where('iron_grade_id', $grade->id)->get()->first(fn ($w) => ! $w->isEmpty());
        if ($window) {
            $t = $heat->thermalTests()->first();
            // The latest sample may have two cups (white and grey). Judge them together: any failing cup fails the gate,
            // and a cup with no acceptance window is simply not counted.
            $cups = $t ? collect([$t])->merge($t->sample_group_id ? $t->siblings()->get() : []) : collect();
            $judged = $cups->where('result', '!=', 'NOT_JUDGED');
            $failed = $cups->firstWhere('result', 'FAIL');
            $max = (int) config('foundry.thermal.max_test_age_minutes');
            if (! $t) {
                $checks[] = ['label' => 'Thermal analysis', 'ok' => false, 'detail' => 'No thermal analysis test is recorded for the heat.'];
            } elseif ($failed) {
                $checks[] = ['label' => 'Thermal analysis', 'ok' => false, 'detail' => "Latest test {$failed->sample_reference} ({$failed->cup_type} cup) is FAIL: ".implode(' ', $failed->findings ?? [])];
            } elseif ($judged->isEmpty()) {
                $checks[] = ['label' => 'Thermal analysis', 'ok' => false, 'detail' => "Latest test {$t->sample_reference} is NOT JUDGED: ".implode(' ', $t->findings ?? [])];
            } elseif ($t->taken_at->lt(now()->subMinutes($max))) {
                $checks[] = ['label' => 'Thermal analysis', 'ok' => false, 'detail' => "The passing test is older than {$max} minutes; take a new sample."];
            } else {
                $checks[] = ['label' => 'Thermal analysis', 'ok' => true, 'detail' => 'Latest test passed within the time limit.'];
            }
        }

        // Treatment: magnesium treatment and inoculation, and the time since magnesium went in (fade)
        if ($grade->needs_mg_treatment || $grade->needs_inoculation) {
            $enforce = \App\Http\Controllers\ProcessControlController::enforceTreatment();
            $maxFade = \App\Http\Controllers\ProcessControlController::maxFadeMinutes();
            $tr = $heat->treatments()->get();
            $verdict = fn (bool $bad) => $bad ? ($enforce ? false : null) : true;
            $note = $enforce ? '' : ' (recorded as a warning; not enforced)';
            if ($grade->needs_mg_treatment) {
                $mg = $tr->where('treatment_type', 'MG_TREATMENT')->sortBy('treated_at')->last();   // latest by time, not by row order
                $checks[] = ['label' => 'Magnesium treatment', 'ok' => $verdict($mg === null), 'phase' => 'POUR',
                    'detail' => $mg ? "Recorded at {$mg->treated_at->format('H:i')}".($mg->material ? " ({$mg->material})" : '').'.' : 'No magnesium treatment is recorded for the heat'.$note.'.'];
                if ($mg) {
                    $mins = (int) $mg->treated_at->diffInMinutes(now());
                    $checks[] = ['label' => 'Treatment fade', 'ok' => $verdict($mins > $maxFade), 'phase' => 'POUR',
                        'detail' => $mins > $maxFade ? "{$mins} minutes since magnesium treatment; the limit is {$maxFade}. Re-treat or re-sample{$note}." : "{$mins} minutes since magnesium treatment (limit {$maxFade})."];
                }
            }
            if ($grade->needs_inoculation) {
                $inoc = $tr->first(fn ($x) => $x->isInoculation());
                $checks[] = ['label' => 'Inoculation', 'ok' => $verdict($inoc === null), 'phase' => 'POUR',
                    'detail' => $inoc ? 'Recorded: '.\App\Models\HeatTreatment::TYPES[$inoc->treatment_type].'.' : 'No inoculation is recorded for the heat'.$note.'.'];
            }
        }

        $reasons = collect($checks)->filter(fn ($c) => $c['ok'] === false)->pluck('detail')->values()->all();

        return ['ok' => $reasons === [], 'reasons' => $reasons, 'checks' => $checks];
    }

    /** Evidence a lot needs before dispatch. */
    public function lotReadiness(CastingLot $lot): array
    {
        $checks = [];
        $heat = $lot->pourMouldAssembly?->pour?->heat;

        if ($heat && $heat->ironGrade) {
            $gate = $this->pourGate($heat);
            foreach ($gate['checks'] as $c) {
                if ($c['ok'] !== null && ($c['phase'] ?? null) !== 'POUR') {
                    // The age limit is a pour-time rule; for dispatch only the result matters.
                    $okEvidence = $c['ok'] || str_contains($c['detail'], 'older than');
                    $checks[] = ['label' => $c['label'].' (heat '.$heat->heat_number.')', 'ok' => $okEvidence, 'detail' => $c['detail']];
                }
            }
        } else {
            $checks[] = ['label' => 'Heat traceability', 'ok' => $heat ? null : false, 'detail' => $heat ? 'The heat has no iron grade; no material gate applies.' : 'The lot cannot be traced to a heat.'];
        }

        $insp = DB::table('quality_inspections')->where('casting_lot_id', $lot->id)->get();
        if ($insp->isEmpty()) {
            $checks[] = ['label' => 'Final inspection', 'ok' => false, 'detail' => 'No inspection is recorded for the lot.'];
        } else {
            $open = $insp->filter(fn ($i) => ! in_array($i->status, ['COMPLETED', 'APPROVED', 'CLOSED'], true))->count();
            $pending = (int) $insp->sum('quantity_pending');
            $checks[] = ['label' => 'Final inspection', 'ok' => $open === 0 && $pending === 0, 'detail' => $open === 0 && $pending === 0 ? $insp->count().' inspection(s) complete.' : "{$open} inspection(s) open and {$pending} piece(s) pending."];
        }

        if ($heat) {
            $req = DB::table('characteristic_limits')->where('subject_type', 'IRON_GRADE')->where('subject_id', $heat->iron_grade_id)->where('is_required_for_release', true)->count();
            if ($req > 0) {
                $missing = app(\App\Services\Quality\Measurements::class)->missingForRelease($heat);
                $checks[] = ['label' => 'Required material tests', 'ok' => $missing === [], 'detail' => $missing === [] ? "All {$req} required test result(s) recorded and within limits." : implode(' ', $missing)];
            }
        }

        $openNcr = DB::table('nonconformance_reports')->where('casting_lot_id', $lot->id)->where('status', '!=', 'CLOSED')->count();
        $checks[] = ['label' => 'Nonconformance reports', 'ok' => $openNcr === 0, 'detail' => $openNcr === 0 ? 'No open NCR against the lot.' : "{$openNcr} NCR(s) still open; close them first."];

        $reasons = collect($checks)->filter(fn ($c) => $c['ok'] === false)->pluck('detail')->values()->all();

        return ['ok' => $reasons === [], 'reasons' => $reasons, 'checks' => $checks];
    }
}
