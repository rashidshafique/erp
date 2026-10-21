<?php

namespace App\Http\Controllers;

use App\Models\QualityDefect;
use App\Services\Quality\QuickInspection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class QuickQcController extends Controller
{
    public function index(QuickInspection $quick): View
    {
        $products = $quick->waiting()->map(fn ($lots) => [
            'lots' => $lots, 'product' => $lots->first()->product, 'revision' => $lots->first()->productRevision,
            'to_check' => $lots->sum('to_check'), 'has_plan' => $quick->hasApprovedPlan($lots->first()->product_revision_id),
        ]);

        return view('quick-qc.index', ['onHold' => \App\Models\CastingLot::where('status', 'ON_HOLD')->count(), 'products' => $products, 'defects' => QualityDefect::where('is_active', true)->orderBy('category')->orderBy('name')->get()]);
    }

    public function store(Request $request, QuickInspection $quick): RedirectResponse
    {
        $request->validate([
            'inspector_name' => ['nullable', 'string', 'max:150'],
            'rows' => ['required', 'array'],
            'rows.*.accepted' => ['nullable', 'integer', 'min:0'],
            'rows.*.rejected' => ['nullable', 'integer', 'min:0'],
            'rows.*.rework' => ['nullable', 'integer', 'min:0'],
            'rows.*.defects' => ['nullable', 'array'],
            'rows.*.defects.*' => ['nullable', 'integer', 'min:0'],
        ]);
        $defectIds = QualityDefect::pluck('id')->all();
        $inspections = $ncrs = 0;
        $errors = [];

        foreach ($request->input('rows') as $revisionId => $row) {
            $accepted = (int) ($row['accepted'] ?? 0);
            $rejected = (int) ($row['rejected'] ?? 0);
            $rework = (int) ($row['rework'] ?? 0);
            if ($accepted + $rejected + $rework === 0) {
                continue;
            }
            $defects = collect($row['defects'] ?? [])->map(fn ($n) => (int) $n)->filter(fn ($n, $id) => $n > 0 && in_array($id, $defectIds, true))->all();
            try {
                $done = $quick->record($revisionId, $accepted, $rejected, $rework, $defects, $request->input('inspector_name') ?: null);
                $inspections += $done['inspections'];
                $ncrs += $done['ncrs'];
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($errors) {
            return back()->withErrors(['quick' => implode(' ', $errors)])->withInput();
        }
        if ($inspections === 0) {
            return back()->withErrors(['quick' => 'Enter how many castings were accepted, rejected or sent to rework.']);
        }

        return back()->with('success', "Quality check saved ({$inspections} lot record(s))".($ncrs ? ", {$ncrs} nonconformance(s) raised." : '.'));
    }
}
