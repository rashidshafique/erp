<?php

namespace App\Domains\Development\Http\Controllers;

use App\Domains\Development\Models\PatternDevelopment;
use App\Domains\Development\Models\PatternDevelopmentTool;
use App\Domains\Development\Services\ToolRegister;
use App\Domains\Development\Support\DevelopmentRules;
use App\Domains\Development\Services\DevelopmentOpener;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryEvent;
use App\Http\Controllers\Controller;
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
        return view('development::index', ['rows' => $this->rows($request)]);
    }

    /** The list with the dialog open over it. `from` says where it was opened: enquiry:ID, product:ID, pattern:ID, customer:ID or products. */
    public function create(Request $request): View
    {
        [$preset, $openedFrom] = $this->preset((string) $request->query('from'));

        return view('development::create', [
            'rows' => $this->rows($request),
            'preset' => $preset,
            'openedFrom' => $openedFrom,
            'backUrl' => $this->backUrl((string) $request->query('from')),
            'types' => $this->typesFor((string) $request->query('from'), $preset['type']),
            'customers' => Customer::active()->orderedByName()->get(),
            'enquiries' => Enquiry::with('customer:id,party_id')->whereIn('status', ['NEW', 'FEASIBILITY', 'QUOTED', 'WON'])->latest()->limit(200)->get(['id', 'code', 'title', 'customer_id']),
            'products' => Product::orderBy('code')->get(['id', 'code', 'name']),
            'patterns' => Pattern::orderBy('code')->get(['id', 'code', 'name', 'owner_customer_id']),
            'coreBoxes' => app(ToolRegister::class)->toolingOn() ? CoreBox::orderBy('code')->get(['id', 'code', 'name']) : collect(),
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
        ]);
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
            $this->log($development, 'CREATED', 'Opened'.(filled($d['opened_from'] ?? null) ? ' from '.$d['opened_from'] : '').'. First stage: '.$development->stageName().'.');

            return $development;
        });

        return redirect()->route('developments.show', $development)->with('success', "{$development->code} opened.");
    }

    public function show(PatternDevelopment $development): View
    {
        $development->load('enquiry:id,code', 'customer', 'product:id,code,name', 'pattern:id,code,name', 'events', 'tools');

        return view('development::show', ['d' => $development]);
    }

    public function advance(Request $request, PatternDevelopment $development): RedirectResponse
    {
        abort_unless($development->isOpen(), 422);
        $next = $development->nextStageName();
        abort_if($next === null, 422);

        $stuck = $development->toolsInTheWay();
        $override = null;
        if ($stuck->isNotEmpty() && ($rule = DevelopmentRules::get('tool_gate')) !== 'OFF') {
            $needs = strtolower(config('pattern_developments.tool_statuses.'.$development->gateStatus()));
            $names = $stuck->pluck('label')->implode(', ');
            $message = $names.($stuck->count() > 1 ? ' have' : ' has').' not reached '.$needs.' yet.';
            if ($rule === 'BLOCK') {
                return back()->withErrors(['gate' => $message.' A rule in settings holds the development here.']);
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
        $tools = $development->tools;
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

    public function toolUpdate(Request $request, PatternDevelopment $development, PatternDevelopmentTool $tool): RedirectResponse
    {
        abort_unless($tool->pattern_development_id === $development->id && $development->isOpen(), 404);
        $d = $request->validate([
            'status' => ['sometimes', Rule::in(array_keys(config('pattern_developments.tool_statuses')))],
            'actual_cost' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999'],
            'quoted_cost' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999'],
            'maker' => ['sometimes', 'nullable', 'string', 'max:150'],
        ]);
        $before = $tool->status;
        $tool->update($d + (isset($d['status']) ? ['status_note' => null] : []));
        app(ToolRegister::class)->sync($tool->fresh());
        if (array_key_exists('actual_cost', $d)) {
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
        $d = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        $development->update(['status' => 'CANCELLED']);
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
        return PatternDevelopment::query()->with('customer', 'product:id,code,name', 'pattern:id,code,name')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%'.trim($request->input('search')).'%';
                $q->where(fn ($w) => $w->where('code', 'ilike', $s)->orWhere('title', 'ilike', $s));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->latest()->paginate(20)->withQueryString();
    }

    /**
     * What the dialog starts with, from where it was opened.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
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
