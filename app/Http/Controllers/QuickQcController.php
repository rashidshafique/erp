<?php

namespace App\Http\Controllers;

use App\Models\PatternRevisionCavity;
use App\Models\QualityDefect;
use App\Models\QualityDefectMark;
use App\Services\Quality\QuickInspection;
use App\Support\QualityRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        return view('quick-qc.index', ['onHold' => \App\Models\CastingLot::where('status', 'ON_HOLD')->count(), 'products' => $products, 'defects' => QualityDefect::where('is_active', true)->orderBy('category')->orderBy('name')->get(),
            'cavities' => $this->cavities($products->keys()), 'pieceIds' => QualityRules::get('defect_piece_ids'), 'byCavity' => $this->byCavity()]);
    }

    /**
     * The pattern cavities that make each product, to mark a defect by the cavity it came from.
     *
     * @return Collection<string, Collection<int, array{value: string, label: string}>> product revision id => choices
     */
    private function cavities(Collection $revisionIds): Collection
    {
        return PatternRevisionCavity::with('patternRevision.pattern')->whereIn('product_revision_id', $revisionIds)->orderBy('cavity_number')->get()
            ->groupBy('product_revision_id')
            ->map(fn ($rows) => $rows->map(fn ($c) => ['value' => $c->pattern_revision_id.':'.$c->cavity_number, 'label' => trim(($c->patternRevision?->pattern?->code ?? '').' cavity '.$c->cavity_number)])->values());
    }

    /** Defects marked by cavity over the last 30 days, most first: where a pattern keeps making the same defect. */
    private function byCavity(): Collection
    {
        return QualityDefectMark::with(['patternRevision.pattern', 'defect'])->where('created_at', '>=', now()->subDays(30))->whereNotNull('pattern_revision_id')
            ->get()->groupBy(fn ($m) => $m->pattern_revision_id.':'.$m->cavity_number.':'.$m->quality_defect_id)
            ->map(fn ($marks) => ['mark' => $marks->first(), 'quantity' => (int) $marks->sum('quantity'), 'rejected' => (int) $marks->where('disposition', 'REJECT')->sum('quantity')])
            ->sortByDesc('quantity')->take(15)->values();
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
            'rows.*.marks' => ['nullable', 'array', 'max:50'],
            'rows.*.marks.*.defect_id' => ['nullable', 'uuid', 'exists:quality_defects,id'],
            'rows.*.marks.*.quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'rows.*.marks.*.disposition' => ['nullable', Rule::in(array_keys(QualityDefectMark::DISPOSITIONS))],
            'rows.*.marks.*.cavity' => ['nullable', 'string', 'max:60'],
            'rows.*.marks.*.location' => ['nullable', 'string', 'max:150'],
            'rows.*.marks.*.piece_ids' => ['nullable', 'string', 'max:2000'],
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
            $marks = [];
            foreach ($row['marks'] ?? [] as $n => $m) {
                if (blank($m['defect_id'] ?? null) && blank($m['quantity'] ?? null)) {
                    continue;
                }
                if (blank($m['defect_id'] ?? null) || blank($m['quantity'] ?? null)) {
                    $errors[] = 'Each defect line needs the defect and how many pieces.';

                    continue 2;
                }
                [$patternRevisionId, $cavity] = array_pad(explode(':', (string) ($m['cavity'] ?? ''), 2), 2, null);
                $marks[] = ['defect_id' => $m['defect_id'], 'quantity' => (int) $m['quantity'], 'disposition' => $m['disposition'] ?? 'REJECT',
                    'pattern_revision_id' => filled($patternRevisionId) ? $patternRevisionId : null, 'cavity_number' => filled($cavity) ? (int) $cavity : null,
                    'location' => $m['location'] ?? null, 'piece_ids' => filled($m['piece_ids'] ?? null) ? implode(', ', QuickInspection::pieceIds($m['piece_ids'])) : null];
            }
            try {
                $done = $quick->record($revisionId, $accepted, $rejected, $rework, $defects, $request->input('inspector_name') ?: null, $marks);
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
