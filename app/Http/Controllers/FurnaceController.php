<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Furnace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FurnaceController extends Controller
{
    public function index(): View
    {
        $furnaces = Furnace::query()
            ->with('equipment')
            ->withCount('heats')
            ->orderBy('code')
            ->paginate(25);

        return view('furnaces.index', compact('furnaces'));
    }

    /** One furnace: its ratings, photos and files, and its recent heats. */
    public function show(Furnace $furnace): View
    {
        $furnace->load('equipment')->loadCount('heats');

        return view('furnaces.show', [
            'furnace' => $furnace,
            'recentHeats' => $furnace->heats()->latest('created_at')->limit(10)->get(),
        ]);
    }

    public function create(): View
    {
        $equipment = Equipment::query()
            ->whereNotIn('status', ['RETIRED', 'DISPOSED'])
            ->orderBy('code')
            ->get();

        return view('furnaces.create', compact('equipment'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:furnaces,code'],
            'name' => ['required', 'string', 'max:255'],
            'furnace_type' => ['required', Rule::in(['CUPOLA', 'INDUCTION', 'OTHER'])],
            'status' => ['required', Rule::in(['ACTIVE', 'MAINTENANCE', 'INACTIVE'])],
            'rated_capacity_kg' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'nominal_melting_rate_kg_per_hour' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'rated_power_kw' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'equipment_id' => [
                'nullable',
                'uuid',
                Rule::exists('equipment', 'id')->whereNull('deleted_at'),
                Rule::unique('furnaces', 'equipment_id'),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $furnace = Furnace::create($validated);

        return redirect()
            ->route('furnaces.index')
            ->with('success', "Furnace {$furnace->code} created.");
    }
}
