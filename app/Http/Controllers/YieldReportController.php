<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Metal yield and scrap, by heat, team, pattern and defect. */
class YieldReportController extends Controller
{
    public function index(Request $request): View
    {
        $to = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();
        $from = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : $to->copy()->subDays(30)->startOfDay();

        // One row per lot, with the heat, team and pattern it came from.
        $lots = DB::table('casting_lots as l')
            ->join('pour_mould_assemblies as pma', 'pma.id', '=', 'l.pour_mould_assembly_id')
            ->join('pours as p', 'p.id', '=', 'pma.pour_id')
            ->join('heats as h', 'h.id', '=', 'p.heat_id')
            ->join('mould_assemblies as ma', 'ma.id', '=', 'pma.mould_assembly_id')
            ->leftJoin('moulding_runs as r', 'r.id', '=', 'ma.moulding_run_id')
            ->leftJoin('moulding_teams as t', 't.id', '=', 'r.moulding_team_id')
            ->leftJoin('pattern_revisions as pr', 'pr.id', '=', 'r.pattern_revision_id')
            ->leftJoin('patterns as pt', 'pt.id', '=', 'pr.pattern_id')
            ->whereBetween('p.completed_at', [$from, $to])
            ->get(['l.id', 'l.quantity_poured', 'l.quantity_good', 'l.quantity_rejected', 'l.net_casting_weight_kg', 'l.gross_weight_kg', 'p.id as pour_id', 'p.pour_number', 'p.total_poured_weight_kg',
                'h.id as heat_id', 'h.heat_number', 't.code as team_code', 't.lead_name', 'pt.code as pattern_code']);

        $good = fn ($l) => $l->quantity_poured > 0 ? (float) $l->net_casting_weight_kg * $l->quantity_good / $l->quantity_poured : 0.0;

        // By pour/heat. Poured metal is counted once per pour.
        $byHeat = $lots->groupBy('pour_id')->map(function ($g) use ($good) {
            $first = $g->first();
            $poured = (float) $first->total_poured_weight_kg;
            $goodKg = $g->sum($good);

            return ['heat' => $first->heat_number, 'pour' => $first->pour_number, 'poured_kg' => $poured, 'good_kg' => $goodKg, 'yield' => $poured > 0 ? 100 * $goodKg / $poured : null,
                'pieces' => $g->sum('quantity_poured'), 'rejected' => $g->sum('quantity_rejected')];
        })->sortByDesc('poured_kg')->values();

        $rate = fn ($g) => ['pieces' => $g->sum('quantity_poured'), 'rejected' => $g->sum('quantity_rejected'), 'rate' => $g->sum('quantity_poured') > 0 ? 100 * $g->sum('quantity_rejected') / $g->sum('quantity_poured') : null];
        $byTeam = $lots->groupBy(fn ($l) => $l->team_code ?? '—')->map(fn ($g, $k) => ['name' => $k === '—' ? 'No team recorded' : $k.' — '.$g->first()->lead_name] + $rate($g))->sortByDesc('rate')->values();
        $byPattern = $lots->groupBy(fn ($l) => $l->pattern_code ?? '—')->map(fn ($g, $k) => ['name' => $k === '—' ? 'No pattern recorded' : $k] + $rate($g))->sortByDesc('rate')->values();

        // Defect Pareto from the pieces named on NCRs raised in the period
        $defects = DB::table('nonconformance_reports as n')->leftJoin('quality_defects as d', 'd.id', '=', 'n.quality_defect_id')
            ->whereBetween('n.reported_at', [$from, $to])
            ->selectRaw("coalesce(d.name, 'Unclassified') as name, d.category, d.icf_group, sum(n.quantity_affected) as qty, count(*) as ncrs")
            ->groupBy('d.name', 'd.category', 'd.icf_group')->orderByDesc('qty')->get();
        $total = (float) $defects->sum('qty');
        $run = 0.0;
        $pareto = $defects->map(function ($d) use (&$run, $total) {
            $run += $d->qty;

            return ['name' => $d->name, 'category' => $d->category, 'icf' => $d->icf_group, 'qty' => (int) $d->qty, 'ncrs' => (int) $d->ncrs, 'pct' => $total > 0 ? 100 * $d->qty / $total : 0, 'cum' => $total > 0 ? 100 * $run / $total : 0];
        });

        $poured = $byHeat->sum('poured_kg');
        $goodKg = $byHeat->sum('good_kg');

        return view('reports.yield', [
            'from' => $from, 'to' => $to, 'byHeat' => $byHeat, 'byTeam' => $byTeam, 'byPattern' => $byPattern, 'pareto' => $pareto, 'groups' => DefectController::GROUPS,
            'totals' => ['poured_kg' => $poured, 'good_kg' => $goodKg, 'yield' => $poured > 0 ? 100 * $goodKg / $poured : null, 'pieces' => $lots->sum('quantity_poured'), 'rejected' => $lots->sum('quantity_rejected')],
        ]);
    }
}
