<?php

namespace App\Http\Controllers;

use App\Models\Flask;
use App\Models\MouldAssembly;
use App\Models\MouldConfiguration;
use App\Models\MouldingRun;
use App\Models\MouldingTeam;
use App\Models\MouldLayer;
use App\Models\PatternRevision;
use App\Models\PatternRevisionCavity;
use App\Models\ProductionOrder;
use App\Models\SandSystem;
use App\Services\PatternCavityMap;
use App\Services\Patterns\PatternOwnership;
use App\Services\Production\FlaskManager;
use App\Services\Production\MouldAssembler;
use App\Services\Production\WorkListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MouldingRunController extends Controller
{
    public function index(Request $request): View
    {
        $runs = MouldingRun::query()
            ->with([
                'productionOrder.product',
                'patternRevision.pattern',
                'mouldConfiguration',
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());

                $query->where(function ($query) use ($search) {
                    $query->where('run_number', 'ilike', "%{$search}%")
                        ->orWhere('operator_name', 'ilike', "%{$search}%")
                        ->orWhereHas('productionOrder', function ($orderQuery) use ($search) {
                            $orderQuery->where(
                                'production_order_number',
                                'ilike',
                                "%{$search}%"
                            );
                        });
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status'))
            )
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('run_date', '>=', $request->input('date_from'))
            )
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('run_date', '<=', $request->input('date_to'))
            )
            ->orderByDesc('run_date')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $todayRuns = MouldingRun::whereDate('run_date', today())->count();

        $inProgressRuns = MouldingRun::where('status', 'IN_PROGRESS')->count();

        $todayMoulds = MouldingRun::whereDate('run_date', today())
            ->sum('moulds_made_qty');

        $todayDamaged = MouldingRun::whereDate('run_date', today())
            ->sum('damaged_mould_qty');

        return view('moulding-runs.index', compact(
            'runs',
            'todayRuns',
            'inProgressRuns',
            'todayMoulds',
            'todayDamaged'
        ));
    }

    public function create(Request $request): View
    {
        $productionOrders = ProductionOrder::query()
            ->with(['product', 'productRevision'])
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->orderByDesc('created_at')
            ->get();

        $patternRevisions = PatternRevision::query()
            ->with('pattern')
            ->orderByDesc('created_at')
            ->get();

        $activePatternRevisionIdsByProduct = PatternRevisionCavity::query()
            ->join('pattern_revisions', 'pattern_revisions.id', '=', 'pattern_revision_cavities.pattern_revision_id')
            ->join('patterns', 'patterns.id', '=', 'pattern_revisions.pattern_id')
            ->join('product_revisions', 'product_revisions.id', '=', 'pattern_revision_cavities.product_revision_id')
            ->where('pattern_revisions.is_current', true)
            ->where('patterns.status', 'ACTIVE')
            ->select('product_revisions.product_id', 'pattern_revisions.id as pattern_revision_id')
            ->distinct()
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->pluck('pattern_revision_id')->values()->all())
            ->all();

        $mouldConfigurations = MouldConfiguration::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedProductionOrderId = old(
            'production_order_id',
            $request->query('production_order_id', '')
        );

        $defaults = [
            'run_date' => today()->toDateString(),
            'shift' => 'General',
            'moulding_method' => 'Manual',
            'sand_system' => 'Greensand',
            'planned_mould_qty' => 0,
            'moulds_made_qty' => 0,
            'accepted_mould_qty' => 0,
            'damaged_mould_qty' => 0,
            'downtime_minutes' => 0,
            'status' => 'IN_PROGRESS',
        ];

        return view('moulding-runs.create', compact(
            'productionOrders',
            'patternRevisions',
            'activePatternRevisionIdsByProduct',
            'mouldConfigurations',
            'selectedProductionOrderId',
            'defaults'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'production_order_id' => [
                'required',
                'uuid',
                Rule::exists('production_orders', 'id')
                    ->whereNull('deleted_at'),
            ],
            'pattern_revision_id' => [
                'nullable',
                'uuid',
                Rule::exists('pattern_revisions', 'id'),
            ],
            'mould_configuration_id' => [
                'nullable',
                'uuid',
                Rule::exists('mould_configurations', 'id')
                    ->whereNull('deleted_at'),
            ],
            'run_date' => ['required', 'date'],
            'shift' => ['nullable', 'string', 'max:30'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'moulding_method' => ['nullable', 'string', 'max:100'],
            'sand_system' => ['nullable', 'string', 'max:100'],
            'operator_name' => ['nullable', 'string', 'max:150'],
            'team_name' => ['nullable', 'string', 'max:150'],
            'planned_mould_qty' => ['nullable', 'integer', 'min:0'],
            'moulds_made_qty' => ['required', 'integer', 'min:0'],
            'accepted_mould_qty' => ['required', 'integer', 'min:0'],
            'damaged_mould_qty' => ['required', 'integer', 'min:0'],
            'downtime_minutes' => ['nullable', 'integer', 'min:0'],
            'downtime_reason' => ['nullable', 'string', 'max:2000'],
            'status' => [
                'required',
                Rule::in(['IN_PROGRESS', 'COMPLETED', 'ON_HOLD', 'CANCELLED']),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        app(PatternCavityMap::class)->assertPatternMakesOrderProduct(
            $validated['pattern_revision_id'] ?? null,
            $validated['production_order_id']
        );

        if (
            $validated['moulds_made_qty']
            < $validated['accepted_mould_qty'] + $validated['damaged_mould_qty']
        ) {
            return back()
                ->withErrors([
                    'accepted_mould_qty' => 'Accepted moulds plus damaged moulds cannot exceed total moulds made.',
                ])
                ->withInput();
        }

        $ownershipWarning = empty($validated['pattern_revision_id']) ? null : app(PatternOwnership::class)->checkUse(
            PatternRevision::with('pattern')->findOrFail($validated['pattern_revision_id']),
            ProductionOrder::find($validated['production_order_id']),
        );
        $run = DB::transaction(function () use ($validated) {
            $validated['run_number'] = MouldingRun::nextRunNumber();

            if (($validated['status'] ?? null) === 'COMPLETED'
                && empty($validated['completed_at'])) {
                $validated['completed_at'] = now();
            }

            return MouldingRun::create($validated);
        });

        return redirect()
            ->route('moulding-runs.show', $run)
            ->with('success', "Moulding run {$run->run_number} was created.")
            ->with('warning', $ownershipWarning);
    }

    public function show(MouldingRun $mouldingRun): View
    {
        $mouldingRun->load([
            'productionOrder.product',
            'productionOrder.productRevision',
            'patternRevision.pattern',
            'mouldConfiguration',
            'mouldAssemblies.pourAssignment.pour',
            'mouldAssemblies.layers.flask',
            'station',
        ]);
        $flasks = app(FlaskManager::class);
        $flaskInfo = ['flaskless' => $flasks->isFlaskless($mouldingRun->station, $mouldingRun->patternRevision), 'sizes' => collect(), 'free' => null,
            'held' => Flask::where('current_moulding_run_id', $mouldingRun->id)->orderBy('code')->get()];
        if (! $flaskInfo['flaskless'] && $mouldingRun->patternRevision) {
            $flaskInfo['sizes'] = $flasks->sizesFor($mouldingRun->patternRevision, $mouldingRun->station);
            $sections = (int) ($mouldingRun->mouldConfiguration?->nominal_layer_count ?? $mouldingRun->patternRevision->layers_per_mould ?? 2);
            $open = max(0, (int) $mouldingRun->accepted_mould_qty - $mouldingRun->mouldAssemblies->count());
            $flaskInfo['free'] = $open ? count($flasks->pick($flaskInfo['sizes'], $open, $flasks->layerTypes($sections))['sets']) : null;
            $flaskInfo['open'] = $open;
        }
        $configurations = MouldConfiguration::where('is_active', true)->orderBy('code')->get();

        $perMould = null;
        if ($mouldingRun->patternRevision && $mouldingRun->productionOrder) {
            $map = app(PatternCavityMap::class);
            if ($map->hasMap($mouldingRun->pattern_revision_id)) {
                $perMould = $map->expectedProductsPerMould(
                    $mouldingRun->patternRevision,
                    $mouldingRun->productionOrder->product_id
                );
            }
        }

        return view('moulding-runs.show', [
            'run' => $mouldingRun,
            'flaskInfo' => $flaskInfo,
            'teams' => MouldingTeam::where('status', 'ACTIVE')->orWhere('id', $mouldingRun->moulding_team_id)->orderBy('code')->get(),
            'sands' => SandSystem::choices($mouldingRun->sand_system),
            'perMould' => $perMould,
            'configurations' => $configurations,
        ]);
    }

    /** Puts the flask actually used into a mould layer, found by its code or stamped mark. */
    public function swapFlask(Request $request, MouldingRun $mouldingRun, MouldLayer $layer, FlaskManager $flasks): RedirectResponse
    {
        abort_unless($layer->assembly?->moulding_run_id === $mouldingRun->id, 404);
        $code = strtoupper(trim((string) $request->validate(['flask' => ['required', 'string', 'max:40']])['flask']));
        $flask = Flask::whereRaw('upper(code) = ? or upper(mark_code) = ?', [$code, $code])->first();
        if (! $flask) {
            return back()->withErrors(['flask' => "No flask with the code or mark {$code}."]);
        }
        DB::transaction(fn () => $flasks->swap($layer, $flask));

        return back()->with('success', "{$flask->label()} is now the ".strtolower($layer->layer_type)." of {$layer->assembly->assembly_number}.");
    }

    /** Releases the flasks of one mould, when it was knocked out without a shakeout record. */
    public function releaseMouldFlasks(MouldingRun $mouldingRun, MouldAssembly $assembly, FlaskManager $flasks): RedirectResponse
    {
        abort_unless($assembly->moulding_run_id === $mouldingRun->id, 404);
        $count = DB::transaction(fn () => $flasks->releaseMould($assembly));

        return back()->with('success', "{$count} ".($count === 1 ? 'flask' : 'flasks')." of {$assembly->assembly_number} released.");
    }

    /** Releases the flasks a run holds by count (the "runs claim by count" rule). */
    public function releaseRunFlasks(MouldingRun $mouldingRun, FlaskManager $flasks): RedirectResponse
    {
        $count = DB::transaction(fn () => $flasks->releaseRun($mouldingRun));

        return back()->with('success', "{$count} ".($count === 1 ? 'flask' : 'flasks')." of run {$mouldingRun->run_number} released.");
    }

    /** Corrects the team and sand of a run; the bulk editor takes them from the station. */
    public function updateCrew(Request $request, MouldingRun $mouldingRun): RedirectResponse
    {
        $data = $request->validate([
            'moulding_team_id' => ['nullable', 'uuid', Rule::exists('moulding_teams', 'id')],
            'sand_system' => ['nullable', Rule::in(SandSystem::allNames())],
        ]);
        $team = filled($data['moulding_team_id'] ?? null) ? MouldingTeam::find($data['moulding_team_id']) : null;
        $mouldingRun->update(['moulding_team_id' => $team?->id, 'team_name' => $team?->name, 'sand_system' => $data['sand_system'] ?? null]);

        return back()->with('success', "Team and sand saved for {$mouldingRun->run_number}.");
    }

    public function assemble(Request $request, MouldingRun $mouldingRun, MouldAssembler $assembler): RedirectResponse
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:500'],
            'mould_configuration_id' => ['required', 'uuid', Rule::exists('mould_configurations', 'id')],
            'layers' => ['required', 'integer', 'min:1', 'max:12'],
            'assembled_by' => ['nullable', 'string', 'max:100'],
            'checked_by' => ['nullable', 'string', 'max:100'],
        ]);
        try {
            $made = $assembler->assemble($mouldingRun, (int) $data['count'], $data['mould_configuration_id'], (int) $data['layers'], $data['assembled_by'] ?? null, $data['checked_by'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['assemble' => $e->getMessage()])->withInput();
        }

        $response = back()->with('success', $made->count().' mould assembl'.($made->count() === 1 ? 'y' : 'ies').' created ('.$made->first()->assembly_number.($made->count() > 1 ? ' to '.$made->last()->assembly_number : '').').');

        return $assembler->warning() ? $response->with('warning', $assembler->warning()) : $response;
    }

    public function recordOutput(Request $request, MouldingRun $mouldingRun, WorkListService $svc): RedirectResponse
    {
        $d = $request->validate([
            'moulds_made_qty' => ['required', 'integer', 'min:0'], 'accepted_mould_qty' => ['required', 'integer', 'min:0'], 'damaged_mould_qty' => ['required', 'integer', 'min:0'],
            'downtime_minutes' => ['nullable', 'integer', 'min:0'], 'downtime_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $d['downtime_minutes'] = $d['downtime_minutes'] ?? 0;
        try {
            $svc->recordOutput($mouldingRun, $d, $request->boolean('complete'));
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['output' => $e->getMessage()])->withInput();
        }

        return back()->with('success', $request->boolean('complete') ? 'Run completed.' : 'Output saved.');
    }

    public function reopen(MouldingRun $mouldingRun, WorkListService $svc): RedirectResponse
    {
        try {
            $svc->reopenRun($mouldingRun);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['output' => $e->getMessage()]);
        }

        return back()->with('success', 'Run reopened.');
    }
}
