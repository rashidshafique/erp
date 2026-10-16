<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Models\Pour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PourController extends Controller
{
    public function index(): View
    {
        $pours = Pour::query()
            ->with('heat.furnace')
            ->withCount('mouldAssemblies')
            ->orderByDesc('created_at')
            ->paginate(20);

        $totalPours = Pour::count();

        $activePours = Pour::whereIn('status', [
            'PLANNED', 'IN_PROGRESS',
        ])->count();

        $completedPours = Pour::where('status', 'COMPLETED')->count();

        return view('pours.index', compact(
            'pours',
            'totalPours',
            'activePours',
            'completedPours'
        ));
    }

    public function create(): View
    {
        $heats = Heat::query()
            ->with('furnace')
            ->whereNull('closed_at')
            ->whereNotIn('status', ['CANCELLED', 'CLOSED'])
            ->orderByDesc('created_at')
            ->get();

        return view('pours.create', [
            'heats' => $heats,
            'defaults' => [
                'status' => 'PLANNED',
                'planned_at' => now()->format('Y-m-d\TH:i'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'heat_id' => [
                'required',
                'uuid',
                Rule::exists('heats', 'id'),
            ],
            'planned_at' => ['nullable', 'date'],
            'operator_name' => ['nullable', 'string', 'max:100'],
            'ladle_reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $pour = DB::transaction(function () use ($validated) {
            $prefix = 'P-' . now()->format('Ymd') . '-';

            $last = Pour::query()
                ->where('pour_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->pluck('pour_number')
                ->map(fn ($number) => (int) substr(
                    $number,
                    strlen($prefix)
                ))
                ->max() ?? 0;

            $validated['pour_number'] = $prefix
                . str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);

            $validated['status'] = 'PLANNED';

            return Pour::create($validated);
        });

        return redirect()
            ->route('pours.show', $pour)
            ->with('success', "Pour {$pour->pour_number} created.");
    }

    public function show(Pour $pour): View
    {
        $candidates = in_array($pour->status, ['PLANNED', 'IN_PROGRESS'], true)
            ? \App\Models\MouldAssembly::with('mouldingRun.productionOrder.product')->whereDoesntHave('pourAssignment')
                ->whereNotIn('status', ['POURED', 'SCRAPPED', 'CANCELLED'])->whereHas('mouldingRun.productionOrder', fn ($q) => $q->whereIn('status', ['RELEASED', 'IN_PROGRESS']))
                ->orderBy('assembly_number')->limit(200)->get()
            : collect();

        $pour->load([
            'heat.furnace',
            'mouldAssemblies.mouldAssembly.configuration',
            'mouldAssemblies.productionOrder.product',
            'mouldAssemblies.castingLots.product',
        ]);

        return view('pours.show', compact('pour', 'candidates'));
    }

    public function start(Pour $pour, \App\Services\QualityGates $gates): RedirectResponse
    {
        if ($pour->status !== 'PLANNED') {
            return back()->withErrors(['gate' => 'Only a planned pour can be started.']);
        }
        $pour->load('heat.ironGrade');
        $gate = $gates->pourGate($pour->heat);
        if (! $gate['ok']) {
            return back()->withErrors(['gate' => 'The pour cannot start. '.implode(' ', $gate['reasons'])]);
        }

        $pour->update(['status' => 'IN_PROGRESS', 'started_at' => now()]);

        return back()->with('success', "Pour {$pour->pour_number} started.");
    }

    public function complete(Request $request, Pour $pour): RedirectResponse
    {
        if ($pour->status !== 'IN_PROGRESS') {
            return back()->withErrors(['complete' => 'Only a pour in progress can be completed.']);
        }
        $data = $request->validate([
            'pour_temperature_c' => ['required', 'numeric', 'between:1000,1700'],
            'total_poured_weight_kg' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'out_of_window_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $grade = $pour->heat?->ironGrade;
        $temp = (float) $data['pour_temperature_c'];
        $notes = $pour->notes;
        if ($grade && (($grade->pour_temp_min_c !== null && $temp < $grade->pour_temp_min_c) || ($grade->pour_temp_max_c !== null && $temp > $grade->pour_temp_max_c))) {
            if (blank($data['out_of_window_reason'] ?? null)) {
                return back()->withErrors(['complete' => "Pour temperature {$temp} °C is outside the {$grade->code} window ({$grade->pour_temp_min_c}-{$grade->pour_temp_max_c} °C). Give a reason to record it."])->withInput();
            }
            $notes = trim($notes."\nPour temperature {$temp} °C outside window, accepted: ".$data['out_of_window_reason']);
        }

        $pour->update([
            'status' => 'COMPLETED', 'completed_at' => now(), 'pour_temperature_c' => $temp,
            'total_poured_weight_kg' => $data['total_poured_weight_kg'], 'notes' => $notes ?: null,
        ]);

        $made = app(\App\Services\Production\LotFactory::class)->createForPour($pour->fresh());
        $msg = "Pour {$pour->pour_number} completed. {$made['lots']} casting lot(s) created.";
        if ($made['warnings']) {
            $msg .= ' '.implode(' ', $made['warnings']);
        }

        return back()->with('success', $msg);
    }

    public function attachAssemblies(Request $request, Pour $pour, \App\Services\Production\PourAssembler $assembler): RedirectResponse
    {
        $data = $request->validate(['assembly_ids' => ['required', 'array', 'min:1'], 'assembly_ids.*' => ['uuid', 'exists:mould_assemblies,id']]);
        try {
            $n = $assembler->attach($pour, $data['assembly_ids']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['assemblies' => $e->getMessage()]);
        }

        return back()->with('success', "{$n} mould(s) added to {$pour->pour_number}.");
    }

    public function detachAssembly(Pour $pour, \App\Models\PourMouldAssembly $row, \App\Services\Production\PourAssembler $assembler): RedirectResponse
    {
        abort_unless($row->pour_id === $pour->id, 404);
        try {
            $assembler->detach($row);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['assemblies' => $e->getMessage()]);
        }

        return back()->with('success', 'Mould removed from the pour.');
    }
}
