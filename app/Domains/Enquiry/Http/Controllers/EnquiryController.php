<?php

namespace App\Domains\Enquiry\Http\Controllers;

use App\Domains\Core\ModuleRegistry;
use App\Domains\Tooling\Models\ToolingLifeLimit;
use App\Domains\Tooling\Services\ToolingLifeStatusService;
use App\Http\Controllers\Controller;
use App\Domains\Enquiry\Services\EnquiryIntake;
use App\Domains\Enquiry\Services\SimilarParts;
use App\Models\Attachment;
use App\Models\CastingAnalysis;
use App\Models\Customer;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryItem;
use App\Domains\Enquiry\Models\EnquiryEvent;
use App\Domains\Enquiry\Models\Quotation;
use App\Models\IronGrade;
use App\Models\Pattern;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Product;
use App\Support\AttachableEntities;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EnquiryController extends Controller
{
    /** Tabs of the list (design 14a): open is everything not yet closed; due this week is open with a reply-by date in the next 7 days. */
    public const TABS = ['open' => 'Open', 'due' => 'Due this week', 'won' => 'Won', 'lost' => 'Lost', 'declined' => 'Declined'];

    /** The enquiries list (design 14a): every RFQ from receipt to won or lost, with the last 90 days and why we lose beside it. */
    public function index(Request $request)
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? $request->query('tab') : 'open';
        $since = today()->subDays(90);
        $closed90 = Enquiry::whereIn('status', ['LOST', 'DECLINED'])->where('closed_at', '>=', $since);
        $quotedIds = Quotation::whereNotNull('issued_at')->where('issued_at', '>=', $since)->distinct()->pluck('enquiry_id');
        $firstIssue = Quotation::whereNotNull('issued_at')->selectRaw('enquiry_id, min(issued_at) as first_issued')->groupBy('enquiry_id');
        $daysToQuote = DB::query()->fromSub($firstIssue, 'q')->join('enquiries', 'enquiries.id', '=', 'q.enquiry_id')
            ->where('q.first_issued', '>=', $since)->selectRaw('avg(extract(epoch from (q.first_issued - enquiries.received_on::timestamptz)) / 86400) as days')->value('days');
        $won90 = Enquiry::where('status', 'WON')->where('closed_at', '>=', $since)->count();

        return view('enquiry::enquiries.index', [
            'rows' => self::rows($request, $tab),
            'tab' => $tab,
            'counts' => collect(self::TABS)->map(fn ($l, $k) => $this->tabQuery($k)->count())->all(),
            'customers' => Customer::whereIn('id', Enquiry::select('customer_id'))->orderedByName()->get(),
            'owners' => User::whereIn('id', Enquiry::whereNotNull('owner_id')->select('owner_id'))->orderBy('name')->get(['id', 'name']),
            'stats' => [
                'received' => Enquiry::where('received_on', '>=', $since)->count(),
                'quoted' => $quotedIds->count(),
                'won' => $won90,
                'wonShare' => $quotedIds->count() > 0 ? round(100 * $won90 / $quotedIds->count()) : null,
                'daysToQuote' => $daysToQuote !== null ? round((float) $daysToQuote, 1) : null,
            ],
            'reasons' => (clone $closed90)->whereNotNull('close_reason')->selectRaw('close_reason, status, count(*) as n')->groupBy('close_reason', 'status')->get()
                ->groupBy('close_reason')->map(fn ($g) => ['lost' => (int) $g->where('status', 'LOST')->sum('n'), 'declined' => (int) $g->where('status', 'DECLINED')->sum('n')])
                ->sortByDesc(fn ($r) => $r['lost'] + $r['declined']),
        ]);
    }

    /** The filtered list as a CSV file. */
    public function export(Request $request): StreamedResponse
    {
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? $request->query('tab') : 'open';
        $rows = self::query($request, $tab)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Code', 'Part', 'Customer', 'RFQ ref', 'Stage', 'Pattern', 'Annual value', 'Reply by', 'Owner']);
            foreach ($rows as $e) {
                fputcsv($out, [$e->code, $e->items->first()?->description ?? $e->title, $e->customer?->name, $e->customer_reference, Enquiry::STAGES[$e->stage()],
                    self::patternText($e), $e->annualValue(), $e->due_on?->toDateString(), $e->owner?->name]);
            }
            fclose($out);
        }, 'enquiries-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }

    /** The enquiries of a tab with the search, customer and owner filters applied, newest first. */
    public static function rows(Request $request, string $tab = 'open'): LengthAwarePaginator
    {
        return self::query($request, $tab)->paginate(20)->withQueryString();
    }

    private static function query(Request $request, string $tab)
    {
        return (new self)->tabQuery($tab)
            ->with('customer', 'owner:id,name', 'pattern:id,code', 'items', 'quotations.lines', 'developments:id,enquiry_id,pattern_by,status')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%'.trim($request->input('search')).'%';
                $q->where(fn ($w) => $w->where('code', 'ilike', $s)->orWhere('title', 'ilike', $s)->orWhere('customer_reference', 'ilike', $s)
                    ->orWhereHas('items', fn ($i) => $i->where('description', 'ilike', $s))
                    ->orWhereHas('customer', fn ($c) => $c->whereHas('party', fn ($p) => $p->where('name', 'ilike', $s))));
            })
            ->when($request->filled('customer'), fn ($q) => $q->where('customer_id', $request->input('customer')))
            ->when($request->filled('owner'), fn ($q) => $q->where('owner_id', $request->input('owner')))
            ->when($request->filled('source'), fn ($q) => $q->where('source_type', $request->input('source')))
            ->orderByRaw('due_on is null')->orderBy('due_on')->orderByDesc('created_at');
    }

    private function tabQuery(string $tab)
    {
        return match ($tab) {
            'due' => Enquiry::whereIn('status', ['NEW', 'FEASIBILITY', 'QUOTED'])->whereBetween('due_on', [today(), today()->addDays(7)]),
            'won' => Enquiry::where('status', 'WON'),
            'lost' => Enquiry::where('status', 'LOST'),
            'declined' => Enquiry::whereIn('status', ['DECLINED', 'CANCELLED']),
            default => Enquiry::whereIn('status', ['NEW', 'FEASIBILITY', 'QUOTED']),
        };
    }

    /** The pattern route as the list shows it: "Existing PT-0302", "New · subcontract", "Customer builds or supplies", or not set. */
    public static function patternText(Enquiry $e): ?string
    {
        $dev = $e->developments->firstWhere('status', 'OPEN') ?? $e->developments->first();

        return match ($e->pattern_route) {
            'EXISTING' => 'Existing'.($e->pattern ? ' '.$e->pattern->code : ''),
            'NEW' => 'New'.($dev && $dev->pattern_by !== 'DECIDE_LATER' ? ' · '.strtolower(config('pattern_developments.pattern_by.'.$dev->pattern_by, '')) : ''),
            'CUSTOMER' => 'Customer builds or supplies',
            default => null,
        };
    }

    /** @return array<string, mixed> */

    /**
     * The service life of the existing pattern chosen for the enquiry, when it is near or past its limit, so a replacement can be
     * started before the order needs it. Null when the Tooling module is off or the pattern is within its limits.
     *
     * @return array{status: string, label: string, percent: ?float}|null
     */
    private static function patternLife(Enquiry $e): ?array
    {
        if ($e->pattern_route !== 'EXISTING' || ! $e->pattern_id || ! class_exists(ToolingLifeLimit::class) || ! app(ModuleRegistry::class)->isEnabled('tooling')) {
            return null;
        }
        $limit = ToolingLifeLimit::where('tooling_type', 'PATTERN')->where('pattern_id', $e->pattern_id)->where('is_active', true)->first();
        $life = $limit ? app(ToolingLifeStatusService::class)->calculate($limit) : null;
        if (! $life || ! in_array($life['status'], ['LIFE_EXCEEDED', 'APPROACHING_LIMIT'], true)) {
            return null;
        }

        return ['status' => $life['status'], 'label' => $life['status_label'], 'percent' => $life['life_percent']];
    }
    private function recordData(Enquiry $e): array
    {
        $departments = config('feasibility.departments');
        $reviews = $e->items->flatMap->reviews;
        $files = Attachment::where('attachable_type', 'enquiry')->where('attachable_id', $e->id)->current()->orderByDesc('created_at')->get();

        return [
            'e' => $e,
            'files' => $files,
            'preview' => $files->first(fn ($f) => $f->isInlineViewable()),
            'patterns' => Pattern::orderBy('code')->get(['id', 'code', 'name']),
            'feasibility' => [
                'signed' => $reviews->count(),
                'expected' => $e->items->count() * count($departments),
                'waiting' => collect($departments)->filter(fn ($d, $k) => $e->items->contains(fn ($i) => ! $i->reviews->contains('department', $k)))->map(fn ($d) => $d[0])->values(),
                'risks' => $reviews->where('verdict', '!=', 'OK'),
                'decided' => $e->items->where('feasibility_status', '!=', 'PENDING')->count(),
                'first' => $e->items->first(),
            ],
            'latestQuote' => $e->quotations->sortByDesc('version')->first(),
            'order' => $e->quotations->firstWhere('sales_order_id')?->salesOrder,
            'development' => $e->developments->sortByDesc('created_at')->first(),
            'patternText' => self::patternText($e),
            'patternLife' => self::patternLife($e),
        ];
    }

    /** The intake page (design 1b) with nothing saved yet. ?customer= and ?scenario= preset the customer and the kind. */
    public function create(Request $request)
    {
        return view('enquiry::enquiries.intake', $this->pageData(null) + [
            'preset' => ['customer' => $request->query('customer'), 'scenario' => array_key_exists((string) $request->query('scenario'), Enquiry::SOURCES) ? $request->query('scenario') : null],
        ]);
    }

    public function store(Request $request, EnquiryIntake $intake)
    {
        $enquiry = $intake->save($request);

        return redirect()->route('enquiries.show', $enquiry)->with('success', $enquiry->status === 'NEW'
            ? "Draft {$enquiry->code} saved for {$enquiry->customer->name}. Send it to feasibility when it is complete."
            : "Enquiry {$enquiry->code} created for {$enquiry->customer->name} and sent to feasibility.");
    }

    /** The same intake page for a saved enquiry: editable while open, read only once won, lost or cancelled. */
    public function show(Enquiry $enquiry)
    {
        $enquiry->load('customer', 'items.product:id,code,name', 'items.analysis', 'quotations.lines');
        // Quotes past their date are marked expired when somebody looks, so no job has to run.
        foreach ($enquiry->quotations as $q) {
            if ($q->status === 'ISSUED' && $q->isExpired()) {
                $q->update(['status' => 'EXPIRED']);
            }
        }

        if ($enquiry->status === 'NEW') {
            return view('enquiry::enquiries.intake', $this->pageData($enquiry) + ['preset' => []]);
        }
        $enquiry->load('owner:id,name', 'pattern:id,code,name', 'questions', 'events', 'developments', 'quotations.salesOrder:id,order_number', 'items.reviews');

        return view('enquiry::enquiries.record', $this->recordData($enquiry));
    }

    /** The intake page (design 1b) for a sent enquiry: Edit details on the record. */
    public function edit(Enquiry $enquiry)
    {
        if (! $enquiry->isOpen() || $enquiry->status === 'NEW') {
            return redirect()->route('enquiries.show', $enquiry);
        }
        $enquiry->load('customer', 'items.product:id,code,name', 'items.analysis', 'quotations.lines');

        return view('enquiry::enquiries.intake', $this->pageData($enquiry) + ['preset' => []]);
    }

    public function update(Request $request, Enquiry $enquiry, EnquiryIntake $intake)
    {
        abort_unless($enquiry->isOpen(), 422);
        $wasDraft = $enquiry->status === 'NEW';
        $enquiry = $intake->save($request, $enquiry);

        return redirect()->route('enquiries.show', $enquiry)->with('success', match (true) {
            $wasDraft && $enquiry->status === 'FEASIBILITY' => "{$enquiry->code} is with feasibility.",
            $enquiry->quotations()->where('status', 'DRAFT')->exists() => 'Enquiry saved. Refresh the draft quotation to pick up the changes.',
            default => 'Enquiry saved.',
        });
    }

    /** The old guided-request address opens the intake page, keeping its presets. */
    public function wizard(Request $request)
    {
        return redirect()->route('enquiries.create', array_filter($request->only(['customer', 'scenario'])));
    }

    /** @return array<string, mixed> */
    private function pageData(?Enquiry $enquiry): array
    {
        $products = Product::with('revisions:id,product_id,revision,status')->orderBy('code')->get(['id', 'code', 'name', 'nominal_weight_kg']);

        return [
            'e' => $enquiry,
            'customers' => Customer::active()->orderedByName()->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products' => $products,
            'productPhotos' => Attachment::coverUrlsFor('product', $products->pluck('id')->all()),
            'grades' => IronGrade::listed(),
            'scenarios' => config('enquiry_scenarios'),
            'categories' => AttachableEntities::CATEGORIES,
            'files' => $enquiry ? Attachment::where('attachable_type', 'enquiry')->where('attachable_id', $enquiry->id)->current()->orderByDesc('created_at')->get() : collect(),
            'similar' => app(SimilarParts::class)->for($enquiry?->items->first()),
            'analyses' => $enquiry ? CastingAnalysis::where('status', 'COMPLETE')->orderByDesc('created_at')->limit(100)->get(['id', 'code', 'name', 'score']) : collect(),
        ];
    }

    /** Sends a saved draft on to feasibility once it has a part. */
    public function send(Enquiry $enquiry)
    {
        abort_unless($enquiry->status === 'NEW', 422);
        if ($enquiry->items()->doesntExist()) {
            return back()->with('error', 'Add the part first.');
        }
        $enquiry->update(['status' => 'FEASIBILITY']);
        EnquiryEvent::system($enquiry, 'Sent to feasibility.');

        return back()->with('success', "{$enquiry->code} is with feasibility.");
    }

    private function itemRules(): array
    {
        return [
            'description' => ['required', 'string', 'max:200'],
            'product_id' => ['nullable', 'exists:products,id'],
            'drawing_ref' => ['nullable', 'string', 'max:100'],
            'drawing_rev' => ['nullable', 'string', 'max:20'],
            'material_text' => ['nullable', 'string', 'max:200'],
            'family' => ['nullable', Rule::in(['GREY', 'DUCTILE'])],
            'annual_qty' => ['nullable', 'integer', 'min:0'],
            'first_batch_qty' => ['nullable', 'integer', 'min:0'],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'heat_treatment' => ['nullable', 'boolean'],
            'ndt' => ['nullable', 'boolean'],
            'cores_count' => ['nullable', 'integer', 'min:0', 'max:200'],
            'machining_cost' => ['nullable', 'numeric', 'min:0'],
            'other_cost' => ['nullable', 'numeric', 'min:0'],
            'tooling_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ] + EnquiryItem::intakeRules();
    }

    private function itemData(Request $request): array
    {
        $d = EnquiryItem::intakeData($request->validate($this->itemRules()));
        $d['heat_treatment'] = $request->boolean('heat_treatment');
        $d['ndt'] = $request->boolean('ndt');
        foreach (['machining_cost', 'other_cost', 'tooling_cost'] as $k) {
            $d[$k] = $d[$k] ?? 0;
        }
        if (empty($d['weight_kg']) && ! empty($d['product_id'])) {
            $d['weight_kg'] = Product::find($d['product_id'])?->nominal_weight_kg;
        }

        return $d;
    }

    public function itemStore(Request $request, Enquiry $enquiry)
    {
        abort_unless($enquiry->isOpen(), 422);
        $d = $this->itemData($request);
        $d['line_no'] = ((int) $enquiry->items()->max('line_no')) + 1;
        $d['feasibility_status'] = 'PENDING';
        $enquiry->items()->create($d);
        if ($enquiry->status === 'NEW') {
            $enquiry->update(['status' => 'FEASIBILITY']);
        }

        return back()->with('success', 'Part added.');
    }

    public function itemUpdate(Request $request, Enquiry $enquiry, EnquiryItem $item)
    {
        abort_unless($item->enquiry_id === $enquiry->id && $enquiry->isOpen(), 404);
        $item->update($this->itemData($request));

        return back()->with('success', 'Part saved. Refresh the draft quotation to pick up the changes.');
    }

    public function itemDestroy(Enquiry $enquiry, EnquiryItem $item)
    {
        abort_unless($item->enquiry_id === $enquiry->id && $enquiry->isOpen(), 404);
        if ($enquiry->quotations()->whereIn('status', ['DRAFT', 'ISSUED'])->whereHas('lines', fn ($q) => $q->where('enquiry_item_id', $item->id))->exists()) {
            return back()->with('error', 'This part is on a live quotation. Reject or supersede that quotation first.');
        }
        $item->delete();

        return back()->with('success', 'Part removed.');
    }

    public function feasibility(Request $request, Enquiry $enquiry, EnquiryItem $item)
    {
        abort_unless($item->enquiry_id === $enquiry->id && $enquiry->isOpen(), 404);
        $d = $request->validate([
            'feasibility_status' => ['required', Rule::in(['OK', 'CONDITIONAL', 'NOT_FEASIBLE'])],
            'feasibility_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($d['feasibility_status'] !== 'OK' && blank($d['feasibility_notes'] ?? null)) {
            return back()->with('error', 'Write the conditions, or the reason it cannot be made.');
        }
        $item->update($d);

        return back()->with('success', 'Feasibility recorded.');
    }

    public function attachAnalysis(Request $request, Enquiry $enquiry, EnquiryItem $item)
    {
        abort_unless($item->enquiry_id === $enquiry->id && $enquiry->isOpen(), 404);
        $d = $request->validate(['casting_analysis_id' => ['nullable', 'exists:casting_analyses,id']]);
        $item->update(['casting_analysis_id' => $d['casting_analysis_id'] ?? null]);

        return back()->with('success', $item->casting_analysis_id ? 'Analysis linked.' : 'Analysis unlinked.');
    }
}
