<?php

namespace App\Domains\Purchasing\Http\Controllers;

use App\Domains\Purchasing\Models\Supplier;
use App\Domains\Purchasing\Models\SupplierEvaluation;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        return view('purchasing::suppliers.index');
    }

    public function create(): View
    {
        return view('purchasing::suppliers.form', ['supplier' => new Supplier(['review_interval_days' => 365])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::create($this->validated($request) + ['approval_status' => 'PENDING']);

        return redirect()->route('suppliers.show', $supplier)->with('success', "{$supplier->code} added as pending. Record an evaluation to approve it.");
    }

    public function show(Supplier $supplier): View
    {
        $supplier->load('evaluations');

        return view('purchasing::suppliers.show', ['supplier' => $supplier, 'statuses' => Supplier::STATUSES]);
    }

    public function edit(Supplier $supplier): View
    {
        return view('purchasing::suppliers.form', ['supplier' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier));

        return redirect()->route('suppliers.show', $supplier)->with('success', "{$supplier->code} saved.");
    }

    /** Scores the supplier on quality, delivery and service, then applies the decision as its approval status and sets the next review. */
    public function evaluate(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'evaluated_on' => ['required', 'date', 'before_or_equal:today'],
            'quality_score' => ['required', 'integer', 'between:0,100'],
            'delivery_score' => ['required', 'integer', 'between:0,100'],
            'service_score' => ['required', 'integer', 'between:0,100'],
            'decision' => ['required', Rule::in(array_keys(Supplier::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $overall = round(($data['quality_score'] + $data['delivery_score'] + $data['service_score']) / 3, 1);

        DB::transaction(function () use ($supplier, $data, $overall) {
            SupplierEvaluation::create($data + ['supplier_id' => $supplier->id, 'overall_score' => $overall, 'recorded_by' => Auth::user()?->name]);
            $supplier->update([
                'approval_status' => $data['decision'],
                'latest_score' => $overall,
                'approved_on' => in_array($data['decision'], ['APPROVED', 'CONDITIONAL'], true) ? $data['evaluated_on'] : $supplier->approved_on,
                'next_review_on' => in_array($data['decision'], Supplier::BLOCKED, true) ? null : \Illuminate\Support\Carbon::parse($data['evaluated_on'])->addDays($supplier->review_interval_days),
            ]);
        });

        return redirect()->route('suppliers.show', $supplier)->with($supplier->fresh()->isBlocked() ? 'warning' : 'success', "Evaluation recorded: {$overall}. {$supplier->code} is now ".strtolower(Supplier::STATUSES[$data['decision']]).'.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        // Codes are kept in capitals, so "acme" and "ACME" count as the same code when checking for duplicates.
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);
        }

        $data = $request->validate(Supplier::rules($supplier));
        if (blank($data['code'] ?? null)) {
            unset($data['code']);
        } else {
            $data['code'] = strtoupper(trim($data['code']));
        }

        return $data;
    }
}
