<?php

namespace App\Http\Controllers;

use App\Models\Flask;
use App\Models\FlaskSize;
use App\Models\MouldAssembly;
use App\Models\ToolingLifeLimit;
use App\Models\ToolingMaintenanceRecord;
use App\Services\Codes\CodeGenerator;
use App\Services\Production\FlaskManager;
use App\Support\FlaskRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** The flask board and register: how many flasks of each size are free, in moulds or in repair, and each flask's record. */
class FlaskController extends Controller
{
    public function index(Request $request): View
    {
        $sizes = FlaskSize::ordered()->get();
        $counts = Flask::selectRaw('flask_size_id, position, status, count(*) n')->groupBy('flask_size_id', 'position', 'status')->get()->groupBy('flask_size_id');
        $flasks = Flask::with('size', 'currentMould', 'currentRun', 'partner')
            ->when($request->filled('size'), fn ($q) => $q->where('flask_size_id', $request->input('size')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('position'), fn ($q) => $q->where('position', $request->input('position')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('code', 'ilike', '%'.$request->input('search').'%')
                ->orWhere('mark_code', 'ilike', '%'.$request->input('search').'%')->orWhere('location', 'ilike', '%'.$request->input('search').'%')))
            ->orderBy('code')->paginate(50)->withQueryString();

        return view('flasks.index', ['manager' => app(FlaskManager::class), 'sizes' => $sizes, 'counts' => $counts, 'flasks' => $flasks, 'rules' => collect(FlaskRules::RULES)->map(fn ($r, $k) => FlaskRules::get($k))->all()]);
    }

    /** Adds a batch of identical flasks, e.g. 20 drags of one size; marks may be listed one per line, the rest are generated. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'flask_size_id' => ['required', 'uuid', Rule::exists('flask_sizes', 'id')->whereNull('deleted_at')->where('is_block', false)],
            'position' => ['required', Rule::in(array_keys(Flask::POSITIONS))],
            'quantity' => ['required', 'integer', 'min:1', 'max:200'],
            'construction' => ['nullable', Rule::in(array_keys(Flask::CONSTRUCTIONS))],
            'material' => ['nullable', 'string', 'max:50'],
            'weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
            'location' => ['nullable', 'string', 'max:100'],
            'marks' => ['nullable', 'string', 'max:5000'],
        ], ['flask_size_id.required' => 'Choose the flask size.']);
        $marks = collect(preg_split('/[\s,;]+/', strtoupper((string) ($data['marks'] ?? '')), -1, PREG_SPLIT_NO_EMPTY))->values();
        if ($marks->count() > $data['quantity']) {
            throw ValidationException::withMessages(['marks' => "{$marks->count()} marks were listed for {$data['quantity']} flasks."]);
        }
        foreach ($marks as $mark) {
            if (! preg_match('/^[A-Z0-9]{3,20}$/', $mark)) {
                throw ValidationException::withMessages(['marks' => "Mark {$mark} is not 3 to 20 letters and digits."]);
            }
        }
        $taken = DB::table('tool_marks')->whereIn('mark_code', $marks)->pluck('mark_code');
        if ($taken->isNotEmpty() || $marks->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['marks' => 'Already used: '.$taken->merge($marks->duplicates())->unique()->implode(', ').'.']);
        }

        $made = DB::transaction(function () use ($data, $marks) {
            $made = collect();
            for ($i = 0; $i < $data['quantity']; $i++) {
                $made->push(Flask::create(collect($data)->only(['flask_size_id', 'position', 'construction', 'material', 'weight_kg', 'location'])->all()
                    + ['mark_code' => $marks[$i] ?? null, 'status' => 'AVAILABLE']));
            }

            return $made;
        });

        return redirect()->route('flasks.index', ['size' => $data['flask_size_id']])
            ->with('success', $made->count().' '.($made->count() === 1 ? 'flask' : 'flasks').' added: '.$made->first()->code.($made->count() > 1 ? ' to '.$made->last()->code : '').'.');
    }

    public function show(Flask $flask): View
    {
        $flask->load('size', 'partner', 'currentMould.mouldingRun', 'currentRun');

        return view('flasks.show', [
            'flask' => $flask,
            'moulds' => $flask->layers()->with('assembly.mouldingRun.patternRevision.pattern', 'assembly.pourAssignment.pour')->limit(30)->get(),
            'maintenance' => ToolingMaintenanceRecord::where('flask_id', $flask->id)->latest('reported_at')->limit(10)->get(),
            'life' => ToolingLifeLimit::where('flask_id', $flask->id)->where('is_active', true)->first(),
        ]);
    }

    public function edit(Flask $flask): View
    {
        return view('flasks.edit', [
            'flask' => $flask,
            'sizes' => FlaskSize::ordered()->where('is_block', false)->where(fn ($q) => $q->where('status', 'ACTIVE')->orWhere('id', $flask->flask_size_id))->get(),
            'partners' => Flask::where('flask_size_id', $flask->flask_size_id)->whereKeyNot($flask->id)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Flask $flask): RedirectResponse
    {
        $data = $request->validate([
            'flask_size_id' => ['required', 'uuid', Rule::exists('flask_sizes', 'id')->where('is_block', false)],
            'position' => ['required', Rule::in(array_keys(Flask::POSITIONS))],
            'construction' => ['nullable', Rule::in(array_keys(Flask::CONSTRUCTIONS))],
            'material' => ['nullable', 'string', 'max:50'],
            'weight_kg' => ['nullable', 'numeric', 'gt:0', 'max:99999'],
            'location' => ['nullable', 'string', 'max:100'],
            'partner_flask_id' => ['nullable', 'uuid', Rule::exists('flasks', 'id')->where('flask_size_id', $request->input('flask_size_id'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'mark_code' => CodeGenerator::MARK_RULES,
        ], ['partner_flask_id.exists' => 'The partner must be a flask of the same size.']);
        if ($flask->status === 'IN_MOULD' && $data['flask_size_id'] !== $flask->flask_size_id) {
            throw ValidationException::withMessages(['flask_size_id' => 'The flask is in a mould; release it before changing its size.']);
        }
        if (($data['partner_flask_id'] ?? null) === $flask->id) {
            throw ValidationException::withMessages(['partner_flask_id' => 'A flask cannot be its own partner.']);
        }
        DB::transaction(function () use ($flask, $data) {
            $mark = $data['mark_code'];
            unset($data['mark_code']);
            $oldPartner = $flask->partner_flask_id;
            $flask->update($data + ['partner_flask_id' => null]);
            app(CodeGenerator::class)->changeMark('flask', $flask, $mark);
            // Pairs are kept both ways: the partner points back, and a flask left without a partner lets go too.
            if ($oldPartner && $oldPartner !== ($data['partner_flask_id'] ?? null)) {
                Flask::whereKey($oldPartner)->where('partner_flask_id', $flask->id)->first()?->update(['partner_flask_id' => null]);
            }
            if ($partner = Flask::find($data['partner_flask_id'] ?? null)) {
                Flask::where('partner_flask_id', $partner->id)->whereKeyNot($flask->id)->get()->each->update(['partner_flask_id' => null]);
                $partner->update(['partner_flask_id' => $flask->id]);
            }
        });

        return redirect()->route('flasks.show', $flask)->with('success', "{$flask->code} saved.");
    }

    /** Out of service and back: repair (with a maintenance record), back in service, retired, lost, found. */
    public function status(Request $request, Flask $flask, FlaskManager $manager): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['repair', 'back', 'retire', 'lost'])],
            'problem' => ['required_if:action,repair', 'nullable', 'string', 'max:2000'],
        ], ['problem.required_if' => 'Describe what is wrong with the flask.']);
        if ($data['action'] === 'repair') {
            $record = $manager->sendToRepair($flask, $data['problem']);

            return back()->with('success', "{$flask->code} is in repair. Maintenance record {$record->record_number} opened.");
        }
        if ($flask->status === 'IN_MOULD') {
            throw ValidationException::withMessages(['action' => "{$flask->code} is in a mould. Release it first."]);
        }
        $flask->update(['status' => ['back' => 'AVAILABLE', 'retire' => 'RETIRED', 'lost' => 'LOST'][$data['action']]]);

        return back()->with('success', "{$flask->code} is now ".strtolower(Flask::STATUSES[$flask->status]).'.');
    }

    /** Releases a flask from the mould or run holding it, counting one use. */
    public function release(Flask $flask, FlaskManager $manager): RedirectResponse
    {
        $count = DB::transaction(fn () => $flask->current_mould_assembly_id
            ? $manager->releaseMould(MouldAssembly::findOrFail($flask->current_mould_assembly_id))
            : ($flask->currentRun ? $manager->releaseRun($flask->currentRun) : 0));

        return back()->with('success', $count ? "{$count} ".($count === 1 ? 'flask' : 'flasks').' released.' : 'Nothing to release.');
    }

    /** The flask rules, under Settings. */
    public function editRules(): View
    {
        return view('settings.rules', [
            'title' => 'Flask rules', 'intro' => 'How the whole foundry handles flasks.',
            'rules' => FlaskRules::RULES, 'values' => collect(FlaskRules::RULES)->map(fn ($r, $k) => FlaskRules::get($k))->all(),
            'action' => route('settings.flasks'), 'backUrl' => route('flasks.index'), 'backLabel' => 'Flask board',
            'note' => 'Flaskless stations (DISAMATIC, DISA Match) and vertically parted patterns never use flasks. A pattern with no flask size set is moulded without flasks until its sizes are chosen. Scrapped or cancelled moulds always give their flasks back.',
        ]);
    }

    public function rules(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (FlaskRules::RULES as $key => [, , $options]) {
            $rules[$key] = ['required', Rule::in(array_keys($options))];
        }
        foreach ($request->validate($rules) as $key => $value) {
            FlaskRules::set($key, $value);
        }

        return back()->with('success', 'Flask rules saved.');
    }
}
