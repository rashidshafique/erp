<?php

namespace App\Domains\Development\Http\Controllers;

use App\Domains\Development\Models\PatternDevelopment;
use App\Domains\Development\Services\DevelopmentOpener;
use App\Domains\Enquiry\Models\Enquiry;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Pattern;
use App\Models\Product;
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
            'types' => config('pattern_developments.types'),
            'customers' => Customer::active()->orderedByName()->get(),
            'enquiries' => Enquiry::with('customer:id,party_id')->whereIn('status', ['NEW', 'FEASIBILITY', 'QUOTED', 'WON'])->latest()->limit(200)->get(['id', 'code', 'title', 'customer_id']),
            'products' => Product::orderBy('code')->get(['id', 'code', 'name']),
            'patterns' => Pattern::orderBy('code')->get(['id', 'code', 'name', 'owner_customer_id']),
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
        $need = fn (string $field, string $message) => blank($d[$field] ?? null) ? throw ValidationException::withMessages([$field => $message]) : null;

        $enquiry = ! empty($d['enquiry_id']) ? Enquiry::with('items')->find($d['enquiry_id']) : null;
        $pattern = ! empty($d['pattern_id']) ? Pattern::find($d['pattern_id']) : null;
        $product = ! empty($d['product_id']) ? Product::find($d['product_id']) : null;

        match ($type) {
            'REPLACEMENT' => [$need('pattern_id', 'Choose the pattern to replace.'), $need('reason', 'Say why it is being replaced.'), $need('who_pays', 'Say who pays.')],
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
                $type === 'REPLACEMENT' => 'Replace '.$pattern?->code.' '.$pattern?->name,
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
                'enquiry_id' => $d['enquiry_id'] ?? null, 'enquiry_item_id' => $enquiry?->items->count() === 1 ? $enquiry->items->first()->id : null, 'product_id' => $d['product_id'] ?? null, 'pattern_id' => $d['pattern_id'] ?? null,
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
        $development->load('enquiry:id,code', 'customer', 'product:id,code,name', 'pattern:id,code,name', 'events');

        return view('development::show', ['d' => $development]);
    }

    public function advance(PatternDevelopment $development): RedirectResponse
    {
        abort_unless($development->isOpen(), 422);
        $next = $development->nextStageName();
        abort_if($next === null, 422);

        DB::transaction(function () use ($development, $next) {
            $development->increment('stage_index');
            $isLast = $development->nextStageName() === null;
            if ($isLast) {
                $development->update(['status' => 'DONE']);
            }
            $this->log($development, 'STAGE', ($isLast ? 'Reached the last stage: ' : 'Moved to ').$next.'.');
        });

        return back()->with('success', 'Moved to '.$next.'.');
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
