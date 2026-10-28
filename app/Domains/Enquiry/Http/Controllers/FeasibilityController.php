<?php

namespace App\Domains\Enquiry\Http\Controllers;

use App\Domains\Enquiry\Models\EnquiryItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FeasibilityController extends Controller
{
    public function index(Request $request)
    {
        $items = EnquiryItem::query()
            ->with('enquiry.customer', 'reviews')
            ->whereHas('enquiry', fn ($q) => $q->whereIn('status', ['NEW', 'FEASIBILITY', 'QUOTED']))
            ->when($request->input('show') !== 'all', fn ($q) => $q->where('feasibility_status', 'PENDING'))
            ->orderBy('created_at')->paginate(25)->withQueryString();

        return view('enquiry::feasibility.index', ['items' => $items, 'departments' => config('feasibility.departments')]);
    }

    public function show(EnquiryItem $item)
    {
        $item->load('enquiry.customer', 'reviews', 'analysis', 'product:id,code,name');

        return view('enquiry::feasibility.show', ['item' => $item, 'departments' => config('feasibility.departments')]);
    }

    public function sign(Request $request, EnquiryItem $item, string $department)
    {
        $departments = config('feasibility.departments');
        abort_unless(isset($departments[$department]) && $item->enquiry->isOpen(), 404);
        $d = $request->validate([
            'verdict' => ['required', Rule::in(['OK', 'CONDITIONAL', 'NOT_FEASIBLE'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($d['verdict'] !== 'OK' && blank($d['notes'] ?? null)) {
            return back()->with('error', 'Write the conditions, or the reason it cannot be made.');
        }
        $item->reviews()->updateOrCreate(['department' => $department], $d + [
            'signed_by_id' => Auth::id(), 'signed_by_name' => Auth::user()?->name, 'signed_at' => now(),
        ]);

        $item->load('reviews');
        if ($item->reviews->count() === count($departments)) {
            $item->update([
                'feasibility_status' => $item->reviewOutcome(),
                'feasibility_notes' => $item->reviews->where('verdict', '!=', 'OK')->map(fn ($r) => $departments[$r->department][0].': '.$r->notes)->implode("\n") ?: null,
            ]);
            if ($item->enquiry->status === 'NEW') {
                $item->enquiry->update(['status' => 'FEASIBILITY']);
            }

            return back()->with('success', 'Signed. Every department has answered, so the part is now marked '.strtolower(EnquiryItem::FEASIBILITY[$item->feasibility_status]).'.');
        }

        return back()->with('success', $departments[$department][0].' signed.');
    }
}
