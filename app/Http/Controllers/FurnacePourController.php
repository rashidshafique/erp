<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Services\Production\MouldCounter;
use App\Services\Production\OneStepPour;
use App\Services\QualityGates;
use App\Support\PouringRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * F4·4 the pour gate and F4·5 the pour by pattern. The gate follows the furnace type's mode exactly as the pour screens do:
 * advisory notes a failed check and lets the pour go on, enforced blocks it. The write is the same one-step pour.
 */
class FurnacePourController extends Controller
{
    /** F4·4 the checks before a pour and the metal in the ladle. */
    public function gate(Heat $heat, QualityGates $gates): View
    {
        $heat->loadMissing('furnace', 'ironGrade');
        [$mode, $gate] = $this->gateOf($heat, $gates);

        return view('floor.furnace.pour-gate', [
            'heat' => $heat, 'mode' => $mode, 'gate' => $gate, 'blocked' => ! $gate['ok'] && $mode === 'ENFORCED',
            'number' => $heat->pours()->where('status', 'COMPLETED')->count() + 1,
        ]);
    }

    /** F4·5 rows per pattern with what is ready and what was poured. */
    public function create(Request $request, Heat $heat, QualityGates $gates, MouldCounter $counter): View|RedirectResponse
    {
        $heat->loadMissing('furnace', 'ironGrade');
        [$mode, $gate] = $this->gateOf($heat, $gates);
        if (! $gate['ok'] && $mode === 'ENFORCED') {
            return redirect()->route('floor.furnace.heat.pour.gate', $heat)->withErrors(['gate' => 'The pour cannot go on. '.implode(' ', $gate['reasons'])]);
        }
        $ladle = (float) $request->query('ladle', 0);
        if ($ladle <= 0) {
            return redirect()->route('floor.furnace.heat.pour.gate', $heat)->withErrors(['ladle_kg' => 'Type the metal in the ladle.']);
        }
        $rows = $counter->readyByOrder();

        return view('floor.furnace.pour', [
            'heat' => $heat, 'ladle' => $ladle, 'rows' => $rows, 'gate' => $gate, 'advice' => $gate['ok'] ? null : implode(' ', $gate['reasons']),
            'number' => $heat->pours()->where('status', 'COMPLETED')->count() + 1,
            'poured' => $heat->pours()->where('status', 'COMPLETED')->with('mouldAssemblies')->get()->sum(fn ($p) => $p->mouldAssemblies->count()),
            'readyTotal' => $rows->sum('ready'),
        ]);
    }

    public function store(Request $request, Heat $heat, QualityGates $gates, OneStepPour $pourer, MouldCounter $counter): RedirectResponse
    {
        $data = $request->validate([
            'ladle_kg' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'poured' => ['required', 'array'],
            'poured.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'pour_temperature_c' => ['nullable', 'numeric', 'between:1000,1700'],
            'out_of_window_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $heat->loadMissing('furnace', 'ironGrade');
        if (in_array($heat->status, ['CLOSED', 'CANCELLED'], true)) {
            throw ValidationException::withMessages(['poured' => 'This heat is closed.']);
        }
        [$mode, $gate] = $this->gateOf($heat, $gates);
        if (! $gate['ok'] && $mode === 'ENFORCED') {
            throw ValidationException::withMessages(['gate' => 'The pour cannot be recorded. '.implode(' ', $gate['reasons'])]);
        }

        // Never more than are ready: the limit is the same count the pour takes its moulds from.
        $ready = $counter->readyByOrder();
        $rows = [];
        foreach ($data['poured'] as $key => $n) {
            $n = (int) $n;
            if ($n < 1) {
                continue;
            }
            $row = $ready->get($key);
            if (! $row || $n > $row['ready']) {
                throw ValidationException::withMessages(['poured' => 'More moulds poured than are ready'.($row ? ' for '.$row['revision']->pattern->code.' ('.$row['ready'].')' : '').'.']);
            }
            $rows[] = ['pattern_revision_id' => $row['revision']->id, 'production_order_id' => $row['order']->id, 'moulds' => $n];
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['poured' => 'Type how many moulds were poured.']);
        }

        $notes = null;
        $temp = isset($data['pour_temperature_c']) ? (float) $data['pour_temperature_c'] : null;
        $grade = $heat->ironGrade;
        if ($temp !== null && $grade && (($grade->pour_temp_min_c !== null && $temp < $grade->pour_temp_min_c) || ($grade->pour_temp_max_c !== null && $temp > $grade->pour_temp_max_c))) {
            if (blank($data['out_of_window_reason'] ?? null)) {
                throw ValidationException::withMessages(['pour_temperature_c' => "Pour temperature {$temp} °C is outside the {$grade->code} window. Give a reason to record it."]);
            }
            $notes = "Pour temperature {$temp} °C outside window, accepted: ".$data['out_of_window_reason'];
        }
        $advice = $gate['ok'] ? null : 'Poured without a clear check: '.implode(' ', $gate['reasons']);

        try {
            $result = $pourer->record($heat, $rows, [
                'operator' => $request->user()?->name, 'gate_mode' => $mode, 'gate_note' => $advice,
                'temperature' => $temp, 'weight' => (float) $data['ladle_kg'], 'notes' => $notes,
            ]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['poured' => $e->getMessage()]);
        }

        return redirect()->route('floor.furnace.heat.show', $heat)
            ->with('success', "Pour {$result['pour']->pour_number} saved. {$result['lots']} casting ".($result['lots'] === 1 ? 'lot' : 'lots').' made for the first check.')
            ->with('warning', trim(implode(' ', array_filter([$advice, ...$result['warnings']]))) ?: null);
    }

    /** @return array{0: string, 1: array{ok: bool, reasons: array<int, string>, checks?: array<int, array<string, mixed>>}} */
    private function gateOf(Heat $heat, QualityGates $gates): array
    {
        $mode = PouringRules::gateMode($heat->furnace?->furnace_type ?? $heat->process_type);

        return [$mode, $mode === 'OFF' ? ['ok' => true, 'reasons' => [], 'checks' => []] : $gates->pourGate($heat)];
    }
}
