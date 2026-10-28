<?php

namespace App\Domains\Enquiry\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CastingAnalysis;
use App\Models\Customer;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        return view('enquiry::enquiries.index', ['rows' => self::rows($request)]);
    }

    /** The enquiries list with the search, status and kind filters applied; the guided request dialog shows it behind itself. */
    public static function rows(Request $request): LengthAwarePaginator
    {
        return Enquiry::query()->with('customer')->withCount('items')
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%'.trim($request->input('search')).'%';
                $q->where(fn ($w) => $w->where('code', 'ilike', $s)->orWhere('title', 'ilike', $s)->orWhere('customer_reference', 'ilike', $s));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('source'), fn ($q) => $q->where('source_type', $request->input('source')))
            ->orderByDesc('created_at')->paginate(20)->withQueryString();
    }

    public function create()
    {
        return view('enquiry::enquiries.create', ['customers' => Customer::active()->orderedByName()->get()]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:200'],
            'source_type' => ['required', Rule::in(array_keys(Enquiry::SOURCES))],
            'received_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date'],
            'customer_reference' => ['nullable', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:4000'],
        ]);
        $d['received_on'] = $d['received_on'] ?? now()->toDateString();
        $d['status'] = 'NEW';
        $d['created_by_id'] = Auth::id();
        $d['created_by_name'] = Auth::user()?->name;
        $e = Enquiry::create($d);

        return redirect()->route('enquiries.show', $e)->with('success', "Enquiry {$e->code} created. Add the parts the customer wants.");
    }

    public function show(Enquiry $enquiry)
    {
        $enquiry->load('customer', 'items.product:id,code,name', 'items.analysis', 'quotations.lines');
        // Quotes past their date are marked expired when somebody looks, so no job has to run.
        foreach ($enquiry->quotations as $q) {
            if ($q->status === 'ISSUED' && $q->isExpired()) {
                $q->update(['status' => 'EXPIRED']);
            }
        }

        return view('enquiry::enquiries.show', [
            'e' => $enquiry,
            'products' => Product::orderBy('code')->get(['id', 'code', 'name', 'nominal_weight_kg']),
            'analyses' => CastingAnalysis::where('status', 'COMPLETE')->orderByDesc('created_at')->limit(100)->get(['id', 'code', 'name', 'score']),
        ]);
    }

    public function update(Request $request, Enquiry $enquiry)
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'source_type' => ['required', Rule::in(array_keys(Enquiry::SOURCES))],
            'due_on' => ['nullable', 'date'],
            'customer_reference' => ['nullable', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:4000'],
        ]);
        $enquiry->update($d);

        return back()->with('success', 'Enquiry saved.');
    }

    /** Sends a saved draft on to feasibility once it has a part. */
    public function send(Enquiry $enquiry)
    {
        abort_unless($enquiry->status === 'NEW', 422);
        if ($enquiry->items()->doesntExist()) {
            return back()->with('error', 'Add the part first.');
        }
        $enquiry->update(['status' => 'FEASIBILITY']);

        return back()->with('success', "{$enquiry->code} is with feasibility.");
    }

    public function lost(Request $request, Enquiry $enquiry)
    {
        $d = $request->validate(['lost_reason' => ['required', 'string', 'max:500']], ['lost_reason.required' => 'Say why the enquiry was lost or cancelled.']);
        abort_unless($enquiry->isOpen(), 422);
        $enquiry->update(['status' => $request->input('outcome') === 'CANCELLED' ? 'CANCELLED' : 'LOST', 'lost_reason' => $d['lost_reason'], 'closed_at' => now()]);
        $enquiry->quotations()->whereIn('status', ['DRAFT', 'ISSUED'])->update(['status' => 'REJECTED', 'decided_at' => now(), 'decision_note' => $d['lost_reason']]);

        return back()->with('success', 'Enquiry closed.');
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
