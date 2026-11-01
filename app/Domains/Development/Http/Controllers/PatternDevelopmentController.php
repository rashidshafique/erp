<?php

namespace App\Domains\Development\Http\Controllers;

use App\Domains\Development\Models\PatternDevelopment;
use App\Domains\Development\Models\PatternDevelopmentTool;
use App\Domains\Development\Services\ToolRegister;
use App\Domains\Development\Support\DevelopmentRules;
use App\Domains\Development\Services\DevelopmentOpener;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryEvent;
use App\Domains\Tooling\Models\ToolingLifeLimit;
use App\Domains\Tooling\Services\ToolingLifeStatusService;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\CoreBox;
use App\Models\Customer;
use App\Models\Pattern;
use App\Models\Product;
use App\Support\Formats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Pattern developments: the list, the dialog that starts one (design 12a), and the record that moves it through its stages. */
class PatternDevelopmentController extends Controller
{
    public function index(Request $request): View
    {
        return view('development::index', ['rows' => $this->rows($request), 'tabs' => $this->tabCounts(), 'customers' => $this->listCustomers()]);
    }

    /** The list with the dialog open over it. `from` says where it was opened: enquiry:ID, product:ID, pattern:ID, customer:ID or products. */
    public function create(Request $request): View
    {
        [$preset, $openedFrom] = $this->preset((string) $request->query('from'));

        return view('development::create', [
            'rows' => $this->rows($request),
            'tabs' => $this->tabCounts(),
            'customers' => $this->listCustomers(),
            'preset' => $preset,
            'openedFrom' => $openedFrom,
            'backUrl' => $this->backUrl((string) $request->query('from')),
            'types' => $this->typesFor((string) $request->query('from'), $preset['type']),
            'customers' => Customer::active()->orderedByName()->get(),
            'enquiries' => Enquiry::with('customer:id,party_id')->whereIn('status', ['NEW', 'FEASIBILITY', 'QUOTED', 'WON'])->latest()->limit(200)->get(['id', 'code', 'title', 'customer_id']),
            'products' => Product::orderBy('code')->get(['id', 'code', 'name']),
            'patterns' => Pattern::orderBy('code')->get(['id', 'code', 'name', 'owner_customer_id']),
            'coreBoxes' => app(ToolRegister::class)->toolingOn() ? CoreBox::orderBy('code')->get(['id', 'code', 'name']) : collect(),
            'life' => $this->lifePercents(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $types = array_keys(config('pattern_developments.types'));
        $d = $request->validate([
            'type' => ['required', Rule::in($types)],
            'title' => ['nullable', 'string', 'max:200'],
            'enquiry_id' => ['nullable', 'exists:enquiries,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'pattern_id' => ['nullable', 'exists:patterns,id'],
            'core_box_id' => ['nullable', 'exists:core_boxes,id'],
            'tool_pick' => ['nullable', 'string', 'max:60'],
            'pattern_by' => ['nullable', Rule::in(array_keys(config('pattern_developments.pattern_by')))],
            'pattern_maker' => ['nullable', 'string', 'max:150'],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'due_on' => ['nullable', 'date'],
            'reason' => ['nullable', Rule::in(array_keys(config('pattern_developments.reasons')))],
            'who_pays' => ['nullable', Rule::in(array_keys(config('pattern_developments.who_pays')))],
            'drawing_change' => ['nullable', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'opened_from' => ['nullable', 'string', 'max:100'],
            'skip_first_tool' => ['nullable', 'boolean'],
            'tools' => ['nullable', 'array', 'max:20'],
            'tools.*.kind' => ['required', Rule::in(array_keys(config('pattern_developments.tool_kinds')))],
            'tools.*.label' => ['required', 'string', 'max:200'],
            'tools.*.route' => ['nullable', Rule::in(array_keys(config('pattern_developments.tool_routes')))],
            'tools.*.made_by' => ['nullable', Rule::in(array_keys(config('pattern_developments.pattern_by')))],
        ], ['tools.*.label.required' => 'Name each tool, e.g. Volute core box.']);
        $type = $d['type'];
        if (filled($d['tool_pick'] ?? null)) {   // the picker lists patterns and core boxes as pattern:ID or box:ID
            [$pickKind, $pickId] = array_pad(explode(':', $d['tool_pick'], 2), 2, null);
            $d[$pickKind === 'box' ? 'core_box_id' : 'pattern_id'] = $pickId;
        }
        $need = fn (string $field, string $message) => blank($d[$field] ?? null) ? throw ValidationException::withMessages([$field => $message]) : null;

        $enquiry = ! empty($d['enquiry_id']) ? Enquiry::with('items')->find($d['enquiry_id']) : null;
        $pattern = ! empty($d['pattern_id']) ? Pattern::find($d['pattern_id']) : null;
        $box = ! empty($d['core_box_id']) ? CoreBox::find($d['core_box_id']) : null;
        $product = ! empty($d['product_id']) ? Product::find($d['product_id']) : null;

        match ($type) {
            'REPLACEMENT' => [$need(filled($d['core_box_id'] ?? null) ? 'core_box_id' : 'pattern_id', 'Choose the pattern or core box to replace.'), $need('reason', 'Say why it is being replaced.'), $need('who_pays', 'Say who pays.')],
            'MODIFICATION' => [$need('product_id', 'Choose the part.'), $need('drawing_change', 'Say what the drawing change is.'), $need('who_pays', 'Say who pays.')],
            default => null,
        };

        $customerId = $d['customer_id'] ?? $enquiry?->customer_id ?? $pattern?->owner_customer_id;
        if ($type !== 'REPLACEMENT' && blank($customerId)) {
            throw ValidationException::withMessages(['customer_id' => 'Choose the customer.']);
        }

        $title = trim((string) ($d['title'] ?? ''));
        if ($title === '') {
            $title = match (true) {
                $type === 'REPLACEMENT' => 'Replace '.($box ?? $pattern)?->code.' '.($box ?? $pattern)?->name,
                $type === 'MODIFICATION' => 'Modify '.$product?->name,
                $enquiry !== null => (string) $enquiry->items->first()?->description ?: $enquiry->title,
                $product !== null => 'Like '.$product->name,
                default => '',
            };
        }
        if (trim($title) === '') {
            throw ValidationException::withMessages(['title' => 'Name the part.']);
        }

        $development = DB::transaction(function () use ($d, $type, $title, $customerId, $enquiry) {
            $development = PatternDevelopment::create([
                'type' => $type, 'title' => $title, 'customer_id' => $customerId,
                'enquiry_id' => $d['enquiry_id'] ?? null, 'enquiry_item_id' => $enquiry?->items->count() === 1 ? $enquiry->items->first()->id : null, 'product_id' => $d['product_id'] ?? null, 'pattern_id' => $d['pattern_id'] ?? null, 'core_box_id' => $d['core_box_id'] ?? null,
                'pattern_by' => $d['pattern_by'] ?? 'DECIDE_LATER', 'pattern_maker' => $d['pattern_maker'] ?? null, 'owner_name' => $d['owner_name'] ?? Auth::user()?->name,
                'due_on' => $d['due_on'] ?? null, 'reason' => $d['reason'] ?? null, 'who_pays' => $d['who_pays'] ?? null,
                'drawing_change' => $d['drawing_change'] ?? null, 'notes' => $d['notes'] ?? null,
                'created_by_id' => Auth::id(), 'created_by_name' => Auth::user()?->name,
            ]);
            $extra = $d['tools'] ?? [];
            if ($extra && ($d['skip_first_tool'] ?? false)) {
                $development->tools()->first()?->delete();
            }
            foreach ($extra as $tool) {
                $development->tools()->create($this->withRegisterLink($tool) + ['route' => 'NEW', 'made_by' => 'DECIDE_LATER']);
            }
            $this->log($development, 'CREATED', 'Opened'.(filled($d['opened_from'] ?? null) ? ' from '.$d['opened_from'] : '').'. First stage: '.$development->stageName().'.');

            return $development;
        });

        return redirect()->route('developments.show', $development)->with('success', "{$development->code} opened.");
    }

    public function show(PatternDevelopment $development): View
    {
        $development->load('enquiry:id,code', 'customer', 'product:id,code,name', 'pattern:id,code,name', 'events', 'tools.pattern:id,code,name', 'tools.coreBox', 'tools.sharedFrom.development:id,code', 'tools.sharedFrom.pattern:id,code', 'tools.sharedFrom.coreBox:id,code', 'tools.sharedBy.development:id,code', 'enquiryItem.reviews');
        $item = $development->enquiryItem;
        $files = $development->enquiry_id ? Attachment::where('attachable_type', 'enquiry')->where('attachable_id', $development->enquiry_id)->current()->orderByDesc('created_at')->get() : collect();

        return view('development::show', [
            'd' => $development,
            'item' => $item,
            'preview' => $files->first(fn ($f) => $f->isInlineViewable()),
            'signed' => $item?->reviews->whereNotNull('signed_at')->sortByDesc('signed_at')->first(),
            'registerPatterns' => Pattern::orderBy('code')->get(['id', 'code', 'name']),
            'registerBoxes' => app(ToolRegister::class)->toolingOn() ? CoreBox::orderBy('code')->get(['id', 'code', 'name']) : collect(),
            'shareable' => $development->isOpen() ? $this->shareable($development) : collect(),
        ]);
    }

    /**
     * Patterns and core boxes on other open developments that this one could share.
     *
     * @return \Illuminate\Support\Collection<int, PatternDevelopmentTool>
     */
    private function shareable(PatternDevelopment $development)
    {
        $kinds = app(ToolRegister::class)->toolingOn() ? ['PATTERN', 'CORE_BOX'] : ['PATTERN'];

        return PatternDevelopmentTool::with('development:id,code', 'pattern:id,code', 'coreBox:id,code')
            ->whereIn('kind', $kinds)->whereNull('shared_tool_id')->where('status', '!=', 'NOT_NEEDED')
            ->where('pattern_development_id', '!=', $development->id)
            ->whereHas('development', fn ($q) => $q->where('status', 'OPEN'))
            ->whereNotIn('id', $development->tools->pluck('shared_tool_id')->filter())
            ->latest()->limit(100)->get();
    }

    public function advance(Request $request, PatternDevelopment $development): RedirectResponse
    {
        abort_unless($development->isOpen(), 422);
        $next = $development->nextStageName();
        abort_if($next === null, 422);

        $stuck = $development->toolsInTheWay();
        $override = null;
        if ($stuck->isNotEmpty() && ($rule = DevelopmentRules::get('tool_gate')) !== 'OFF') {
            $message = $development->gateMessage();
            if ($rule === 'BLOCK') {
                return back()->withErrors(['gate' => $message.' Set by a rule in settings.']);
            }
            if (! $request->boolean('override')) {
                return back()->with('gate_warn', $message);
            }
            $override = "Moved on with an override: {$message}";
        }

        DB::transaction(function () use ($development, $next, $override) {
            $development->increment('stage_index');
            $isLast = $development->nextStageName() === null;
            if ($isLast) {
                $development->update(['status' => 'DONE']);
            }
            if ($override) {
                $this->log($development, 'STAGE', $override);
            }
            $this->log($development, 'STAGE', ($isLast ? 'Reached the last stage: ' : 'Moved to ').$next.'.');
            if ($isLast) {
                app(ToolRegister::class)->linkRequirements($development);
                app(ToolRegister::class)->retire($development);
            }
            if ($isLast && $revision = $development->draftRevision()) {
                $revision->update(['status' => 'APPROVED', 'effective_from' => $revision->effective_from ?? today()]);
                $this->log($development, 'STAGE', "Revision {$revision->revision} of {$development->product->code} approved, so orders for it can go ahead.");
            }
        });

        return back()->with('success', 'Moved to '.$next.'.');
    }

    /** Back one stage, as when a trial fails and the tools go back for rework. The reason goes on the log; ticked tools go to Rework, the rest keep their status. */
    public function back(Request $request, PatternDevelopment $development): RedirectResponse
    {
        abort_unless($development->isOpen() && $development->stage_index > 0, 422);
        $d = $request->validate([
            'reason' => ['required', 'string', 'max:300'],
            'tools' => ['nullable', 'array'],
            'tools.*' => ['string', Rule::exists('pattern_development_tools', 'id')->where('pattern_development_id', $development->id)],
        ], ['reason.required' => 'Say why it goes back, e.g. what the trial found.']);
        $from = $development->stageName();
        $tools = $development->tools->reject->isShared();
        $ticked = $tools->count() === 1 ? $tools : $tools->whereIn('id', $d['tools'] ?? []);
        DB::transaction(function () use ($development, $d, $from, $ticked) {
            $development->decrement('stage_index');
            foreach ($ticked as $tool) {
                $tool->update(['status' => 'REWORK', 'status_note' => $d['reason']]);
            }
            $this->log($development, 'STAGE', "Back from {$from} to {$development->stageName()}: {$d['reason']}");
            if ($ticked->isNotEmpty()) {
                $this->log($development, 'NOTE', 'Rework: '.$ticked->pluck('label')->implode(', ').'.');
            }
        });

        return back()->with('success', 'Back to '.$development->stageName().'.');
    }

    /** What the pattern actually cost: set on the development's pattern, or its first tool, and the total follows. */
    public function cost(Request $request, PatternDevelopment $development): RedirectResponse
    {
        $d = $request->validate(['actual_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999']]);
        $tool = $development->tools->firstWhere('kind', 'PATTERN') ?? $development->tools->first();
        $tool?->update(['actual_cost' => $d['actual_cost'] ?? null]);
        $development->syncCosts();
        $this->log($development, 'NOTE', filled($d['actual_cost'] ?? null) ? 'Pattern cost recorded: '.Formats::number($d['actual_cost'], 2).'.' : 'Pattern cost cleared.');

        return back()->with('success', 'Pattern cost saved.');
    }

    public function toolStore(Request $request, PatternDevelopment $development): RedirectResponse
    {
        abort_unless($development->isOpen(), 422);
        if ($request->filled('shared_tool_id')) {
            return $this->toolShare($request, $development);
        }
        $d = $request->validate([
            'kind' => ['required', Rule::in(array_keys(config('pattern_developments.tool_kinds')))],
            'label' => ['required', 'string', 'max:200'],
            'route' => ['nullable', Rule::in(array_keys(config('pattern_developments.tool_routes')))],
            'made_by' => ['nullable', Rule::in(array_keys(config('pattern_developments.pattern_by')))],
            'maker' => ['nullable', 'string', 'max:150'],
            'who_pays' => ['nullable', Rule::in(array_keys(config('pattern_developments.who_pays')))],
            'quoted_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'due_on' => ['nullable', 'date'],
        ], ['label.required' => 'Name the tool, e.g. Volute core box.']);
        $tool = $development->tools()->create($d + ['route' => 'NEW', 'made_by' => 'DECIDE_LATER']);
        $this->log($development, 'NOTE', 'Tool added: '.config('pattern_developments.tool_kinds.'.$tool->kind).' '.$tool->label.'.');

        return back()->with('success', 'Tool added.');
    }

    /**
     * Adds a row standing for a tool made on another open development (design 18c). It has no cost of its own and follows that
     * tool's status, so it counts toward this development's gate once the other one marks it built.
     */
    private function toolShare(Request $request, PatternDevelopment $development): RedirectResponse
    {
        $d = $request->validate(['shared_tool_id' => ['required', 'uuid', Rule::exists('pattern_development_tools', 'id')]]);
        $source = PatternDevelopmentTool::with('development', 'pattern:id,code', 'coreBox:id,code')->findOrFail($d['shared_tool_id']);
        $refusal = match (true) {
            $source->pattern_development_id === $development->id => 'That tool is already on this development.',
            $source->isShared() => 'That row is itself shared. Share the tool from '.$source->sharedFrom?->development?->code.' instead.',
            ! $source->development->isOpen() => "{$source->development->code} is closed, so its tools cannot be shared.",
            $development->tools()->where('shared_tool_id', $source->id)->exists() => 'That tool is already shared with this development.',
            default => null,
        };
        if ($refusal) {
            throw ValidationException::withMessages(['shared_tool_id' => $refusal]);
        }
        $development->tools()->create([
            'shared_tool_id' => $source->id, 'kind' => $source->kind, 'label' => $source->label, 'route' => $source->route,
            'made_by' => $source->made_by, 'status' => $source->status,
        ]);
        $name = config('pattern_developments.tool_kinds.'.$source->kind).' '.($source->registerCode() ?? $source->label);
        $this->log($development, 'NOTE', "{$name} shared from {$source->development->code}.");
        $this->log($source->development, 'NOTE', "{$name} also used by {$development->code}.");

        return back()->with('success', 'Shared tool added.');
    }

    public function toolUpdate(Request $request, PatternDevelopment $development, PatternDevelopmentTool $tool): RedirectResponse
    {
        abort_unless($tool->pattern_development_id === $development->id && $development->isOpen(), 404);
        abort_if($tool->isShared(), 422, 'A shared tool is moved on its own development.');
        $d = $request->validate([
            'status' => ['sometimes', Rule::in(array_keys(config('pattern_developments.tool_statuses')))],
            'actual_cost' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999'],
            'quoted_cost' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999'],
            'maker' => ['sometimes', 'nullable', 'string', 'max:150'],
            'made_by' => ['sometimes', Rule::in(array_keys(config('pattern_developments.pattern_by')))],
            'who_pays' => ['sometimes', 'nullable', Rule::in(array_keys(config('pattern_developments.who_pays')))],
            'due_on' => ['sometimes', 'nullable', 'date'],
        ]);
        $before = $tool->status;
        $tool->update($d + (isset($d['status']) && $d['status'] !== $before ? ['status_note' => null] : []));
        app(ToolRegister::class)->sync($tool->fresh());
        if ($tool->wasChanged('actual_cost')) {
            $development->syncCosts();
            $this->log($development, 'NOTE', "{$tool->label}: cost ".(isset($d['actual_cost']) ? 'recorded as '.Formats::number($d['actual_cost'], 2) : 'cleared').'.');
        }
        if (isset($d['status']) && $d['status'] !== $before) {
            $this->log($development, 'NOTE', "{$tool->label}: {$tool->statusName()}.");
        }

        return back()->with('success', 'Tool saved.');
    }

    /** Adds the planned tool to its register (patterns, or core boxes when Tooling is on) and links the two. */
    public function toolRegister(PatternDevelopment $development, PatternDevelopmentTool $tool): RedirectResponse
    {
        abort_unless($tool->pattern_development_id === $development->id && $development->isOpen(), 404);
        app(ToolRegister::class)->create($tool);
        app(ToolRegister::class)->sync($tool->fresh());

        return back()->with('success', 'Added to the register.');
    }

    /** Links the planned tool to a pattern or core box already in the register. */
    public function toolLink(Request $request, PatternDevelopment $development, PatternDevelopmentTool $tool): RedirectResponse
    {
        abort_unless($tool->pattern_development_id === $development->id && $development->isOpen(), 404);
        $d = $request->validate(['record' => ['required', 'string', 'max:60']], ['record.required' => 'Choose the register record.']);
        app(ToolRegister::class)->link($tool, $d['record']);

        return back()->with('success', 'Linked to the register.');
    }

    public function toolDestroy(PatternDevelopment $development, PatternDevelopmentTool $tool): RedirectResponse
    {
        abort_unless($tool->pattern_development_id === $development->id && $development->isOpen(), 404);
        $tool->delete();
        $development->syncCosts();
        $this->log($development, 'NOTE', "Tool removed: {$tool->label}.");

        return back()->with('success', 'Tool removed.');
    }

    public function note(Request $request, PatternDevelopment $development): RedirectResponse
    {
        $d = $request->validate(['text' => ['required', 'string', 'max:2000']]);
        $this->log($development, 'NOTE', $d['text']);

        return back()->with('success', 'Note added.');
    }

    public function cancel(Request $request, PatternDevelopment $development): RedirectResponse
    {
        abort_unless($development->isOpen(), 422);
        $started = $development->startedTools();
        $d = $request->validate([
            'reason' => ['nullable', 'string', 'max:300'],
            'decisions' => ['nullable', 'array'],
            'decisions.*' => [Rule::in(array_keys(config('pattern_developments.cancel_decisions')))],
        ] + $started->mapWithKeys(fn ($t) => ["decisions.{$t->id}" => ['required', Rule::in(array_keys(config('pattern_developments.cancel_decisions')))]])->all(),
            $started->mapWithKeys(fn ($t) => ["decisions.{$t->id}.required" => "Decide what happens to {$t->label}."])->all());
        DB::transaction(function () use ($development, $started, $d) {
            app(ToolRegister::class)->settle($development, collect($d['decisions'] ?? [])->only($started->pluck('id'))->all());
            $development->update(['status' => 'CANCELLED']);
        });
        $this->log($development, 'CANCELLED', 'Cancelled'.(filled($d['reason'] ?? null) ? ': '.$d['reason'] : '.'));
        if ($development->enquiry) {
            EnquiryEvent::system($development->enquiry, "Pattern development {$development->code} cancelled".(filled($d['reason'] ?? null) ? ': '.$d['reason'] : '.'));
        }

        return back()->with('success', "{$development->code} cancelled.");
    }

    private function log(PatternDevelopment $development, string $kind, string $text): void
    {
        $development->events()->create(['kind' => $kind, 'text' => $text, 'user_name' => Auth::user()?->name]);
    }

    private function rows(Request $request)
    {
        return PatternDevelopment::query()->with('customer', 'product:id,code,name', 'pattern:id,code,name', 'tools.pattern:id,code,name', 'tools.coreBox')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%'.trim($request->input('search')).'%';
                $q->where(fn ($w) => $w->where('code', 'ilike', $s)->orWhere('title', 'ilike', $s)->orWhereHas('tools', fn ($t) => $t->where('label', 'ilike', $s)
                    ->orWhereHas('pattern', fn ($r) => $r->where('code', 'ilike', $s))->orWhereHas('coreBox', fn ($r) => $r->where('code', 'ilike', $s))));
            })
            ->when($request->input('tab') === 'waiting', fn ($q) => $this->waitingOnTools($q))
            ->when($request->input('tab') === 'closed', fn ($q) => $q->where('status', '!=', 'OPEN'))
            ->when($request->filled('made_by'), fn ($q) => $q->whereHas('tools', fn ($t) => $t->where('made_by', $request->input('made_by'))))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->input('customer_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->latest()->paginate(20)->withQueryString();
    }

    /** @return \Illuminate\Support\Collection<int, Customer> the customers that have a development, for the list's filter */
    private function listCustomers()
    {
        return Customer::whereIn('id', PatternDevelopment::query()->whereNotNull('customer_id')->select('customer_id'))->orderedByName()->get();
    }

    /** Open developments with a tool being built or in rework. */
    private function waitingOnTools($query)
    {
        return $query->where('status', 'OPEN')->whereHas('tools', fn ($t) => $t->whereIn('status', ['BUILDING', 'REWORK']));
    }

    /** @return array{open: int, waiting: int, closed: int} counts for the list's tabs */
    private function tabCounts(): array
    {
        return [
            'open' => PatternDevelopment::where('status', 'OPEN')->count(),
            'waiting' => $this->waitingOnTools(PatternDevelopment::query())->count(),
            'closed' => PatternDevelopment::where('status', '!=', 'OPEN')->count(),
        ];
    }

    /**
     * A tool typed in the dialog that names a register record, as "CB-0118 — Volute core", links to that record.
     *
     * @param  array<string, mixed>  $tool
     * @return array<string, mixed>
     */
    private function withRegisterLink(array $tool): array
    {
        $code = trim(explode(' — ', $tool['label'], 2)[0]);
        $record = match ($tool['kind']) {
            'PATTERN' => Pattern::where('code', $code)->first(),
            'CORE_BOX' => app(ToolRegister::class)->toolingOn() ? CoreBox::where('code', $code)->first() : null,
            default => null,
        };

        return $tool + ($record ? [$record instanceof CoreBox ? 'core_box_id' : 'pattern_id' => $record->id] : []);
    }

    /** @return array<string, int> register record id => percent of rated life used, for the replacement picker */
    private function lifePercents(): array
    {
        if (! class_exists(ToolingLifeLimit::class) || ! app(ToolRegister::class)->toolingOn()) {
            return [];
        }
        $service = app(ToolingLifeStatusService::class);

        return ToolingLifeLimit::where('is_active', true)->whereIn('tooling_type', ['PATTERN', 'CORE_BOX'])->get()
            ->mapWithKeys(fn ($limit) => [(string) ($limit->pattern_id ?? $limit->core_box_id) => $service->calculate($limit)['life_percent']])
            ->filter(fn ($percent) => $percent !== null)->map(fn ($percent) => (int) round($percent))->all();
    }

    /**
     * The kinds of development the dialog offers. Opened from an enquiry, only those that can follow from it: the new part or product
     * its pattern decision points to, a customer pattern, and a modification when the enquiry is a change to a part we make. A
     * replacement is started from the worn pattern or core box, not from an enquiry. Opened from anywhere else, all of them.
     *
     * @return array<string, array<string, mixed>>
     */
    private function typesFor(string $from, string $primary): array
    {
        $all = config('pattern_developments.types');
        [$kind, $id] = array_pad(explode(':', $from, 2), 2, null);
        $enquiry = $kind === 'enquiry' ? Enquiry::find($id) : null;
        if (! $enquiry) {
            return $all;
        }
        $keep = array_filter([$primary, 'CUSTOMER_PATTERN', $enquiry->source_type === 'CHANGE' ? 'MODIFICATION' : null]);

        return array_intersect_key($all, array_flip($keep));
    }

    /** Where closing the dialog goes: back to the record it was opened from, or the list. */
    private function backUrl(string $from): string
    {
        [$kind, $id] = array_pad(explode(':', $from, 2), 2, null);
        $back = match ($kind) {
            'enquiry' => Enquiry::find($id) ? route('enquiries.show', $id) : null,
            'product' => Product::find($id) ? route('products.show', $id) : null,
            'pattern' => Pattern::find($id) ? route('patterns.show', $id) : null,
            'core_box' => CoreBox::find($id) ? route('core-boxes.show', $id) : null,
            default => null,
        };

        return $back ?? route('developments.index');
    }

    /**
     * What the dialog starts with, from where it was opened.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function preset(string $from): array
    {
        [$kind, $id] = array_pad(explode(':', $from, 2), 2, null);
        $preset = ['type' => 'NEW_PART'];
        $label = 'Pattern developments';

        if ($kind === 'enquiry' && $e = Enquiry::with('items', 'customer')->find($id)) {
            $firstTime = $e->customer?->isFirstTimeCustomer() !== false;
            $preset = ['type' => DevelopmentOpener::typeFor($e, $firstTime) ?? ($firstTime ? 'NEW_PART' : 'NEW_PRODUCT'), 'enquiry_id' => $e->id, 'customer_id' => $e->customer_id, 'title' => $e->items->first()?->description ?: $e->title];
            $label = 'enquiry '.$e->code;
        } elseif ($kind === 'product' && $p = Product::find($id)) {
            $preset = ['type' => 'MODIFICATION', 'product_id' => $p->id, 'title' => 'Modify '.$p->name];
            $label = 'part '.$p->code;
        } elseif ($kind === 'pattern' && $pt = Pattern::find($id)) {
            $preset = ['type' => 'REPLACEMENT', 'pattern_id' => $pt->id, 'customer_id' => $pt->owner_customer_id, 'title' => 'Replace '.$pt->code.' '.$pt->name];
            $label = 'pattern register '.$pt->code;
        } elseif ($kind === 'core_box' && $cb = CoreBox::find($id)) {
            $preset = ['type' => 'REPLACEMENT', 'core_box_id' => $cb->id, 'title' => 'Replace '.$cb->code.' '.$cb->name];
            $label = 'core box '.$cb->code;
        } elseif ($kind === 'customer' && $c = Customer::find($id)) {
            $preset = ['type' => 'NEW_PRODUCT', 'customer_id' => $c->id];
            $label = 'customer '.$c->name;
        } elseif ($from === 'products') {
            $preset = ['type' => 'NEW_PRODUCT'];
            $label = 'products list';
        }

        return [$preset, $label];
    }
}
