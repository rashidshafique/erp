<?php

namespace App\Services\Dashboard;

use App\Models\ApprovalRequest;
use App\Models\Attachment;
use App\Models\Enquiry;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\MeasurementSet;
use App\Models\MouldingRun;
use App\Models\NonconformanceReport;
use App\Models\ProductionOrder;
use App\Models\SandSystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Everything the dashboard layouts show, worked out once so each layout only decides where things go. */
class DashboardData
{
    public const DAYS = 14;

    /** Per-day totals for the last N days, oldest first, from casting lots by the day their pour finished. */
    public function dailyPours(int $days = self::DAYS): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = DB::table('casting_lots as l')
            ->join('pour_mould_assemblies as pma', 'pma.id', '=', 'l.pour_mould_assembly_id')
            ->join('pours as p', 'p.id', '=', 'pma.pour_id')
            ->where('p.completed_at', '>=', $from)
            ->get(['l.quantity_poured', 'l.quantity_good', 'l.quantity_rejected', 'l.net_casting_weight_kg', 'p.completed_at', 'p.id as pour_id', 'p.total_poured_weight_kg']);
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i);
            $out[$d->toDateString()] = ['date' => $d, 'poured' => 0, 'rejected' => 0, 'good_kg' => 0.0, 'poured_kg' => 0.0, 'pours' => []];
        }
        foreach ($rows as $r) {
            $k = Carbon::parse($r->completed_at)->toDateString();
            if (! isset($out[$k])) {
                continue;
            }
            $o = &$out[$k];
            $o['poured'] += (int) $r->quantity_poured;
            $o['rejected'] += (int) $r->quantity_rejected;
            $o['good_kg'] += $r->quantity_poured > 0 ? (float) $r->net_casting_weight_kg * $r->quantity_good / $r->quantity_poured : 0;
            if (! isset($o['pours'][$r->pour_id])) {
                $o['pours'][$r->pour_id] = true;
                $o['poured_kg'] += (float) $r->total_poured_weight_kg;
            }
            unset($o);
        }

        return array_values(array_map(function ($o) {
            $o['yield'] = $o['poured_kg'] > 0 ? round(100 * $o['good_kg'] / $o['poured_kg'], 1) : null;
            $o['scrap'] = $o['poured'] > 0 ? round(100 * $o['rejected'] / $o['poured'], 2) : null;
            unset($o['pours']);

            return $o;
        }, $out));
    }

    public function kpis(array $daily): array
    {
        $enqOpen = Enquiry::whereNotIn('status', ['WON', 'LOST', 'CANCELLED', 'CLOSED'])->count();
        $enqSpark = $this->perDay(Enquiry::class, 'created_at');
        $week = now()->addDays(7)->toDateString();
        $due = ProductionOrder::open()->whereNotNull('required_date')->whereDate('required_date', '<=', $week)->whereDate('required_date', '>=', today()->subDays(0));
        $overdue = ProductionOrder::open()->whereDate('required_date', '<', today())->count();

        $heatsToday = Heat::where(fn ($q) => $q->whereDate('planned_start_at', today())->orWhereDate('actual_start_at', today()))->get();
        $heatsDone = $heatsToday->filter(fn ($h) => $h->tapped_at || in_array($h->status, ['POURED', 'CLOSED', 'COMPLETED'], true))->count();
        $tapped = (float) $heatsToday->sum(fn ($h) => (float) ($h->actual_tapped_weight_kg ?: 0));

        $sum = fn (array $rows, string $k) => array_sum(array_column($rows, $k));
        $last7 = array_slice($daily, -7);
        $prev7 = array_slice($daily, -14, 7);
        $rate = fn (array $rows) => $sum($rows, 'poured') > 0 ? 100 * $sum($rows, 'rejected') / $sum($rows, 'poured') : null;
        $scrap = $rate($last7);
        $scrapPrev = $rate($prev7);

        $ncrOpen = NonconformanceReport::where('status', '!=', 'CLOSED');

        return [
            'enquiries' => ['open' => $enqOpen, 'today' => Enquiry::whereDate('created_at', today())->count(), 'spark' => $enqSpark, 'quotes' => \App\Models\Quotation::where('status', 'ISSUED')->count()],
            'due' => ['count' => $due->count(), 'qty' => (float) (clone $due)->sum('planned_qty'), 'overdue' => $overdue],
            'heats' => ['total' => $heatsToday->count(), 'done' => $heatsDone, 'tonnes' => $tapped / 1000, 'running' => Heat::where('status', 'IN_PROGRESS')->count()],
            'scrap' => ['rate' => $scrap, 'delta' => $scrap !== null && $scrapPrev !== null ? $scrap - $scrapPrev : null, 'spark' => array_map(fn ($d) => $d['scrap'] ?? 0, $daily)],
            'ncrs' => ['open' => (clone $ncrOpen)->count(), 'major' => (clone $ncrOpen)->whereIn('severity', ['MAJOR', 'CRITICAL'])->count(), 'minor' => (clone $ncrOpen)->whereNotIn('severity', ['MAJOR', 'CRITICAL'])->count()],
        ];
    }

    /** Record counts per day over the window, as a plain list for a sparkline. */
    private function perDay(string $model, string $col, int $days = self::DAYS): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $c = $model::where($col, '>=', $from)->pluck($col)->map(fn ($v) => Carbon::parse($v)->toDateString())->countBy();

        return collect(range(0, $days - 1))->map(fn ($i) => (int) ($c[$from->copy()->addDays($i)->toDateString()] ?? 0))->all();
    }

    /** Things that need someone to act, newest first: chemistry that failed, open NCRs, approvals, late orders. */
    public function attention(): array
    {
        $items = [];
        $bad = DB::table('heat_chemistry_readings as r')->join('heats as h', 'h.id', '=', 'r.heat_id')
            ->where('r.is_accepted', false)->where('h.status', '!=', 'CLOSED')->where('r.created_at', '>=', now()->subDays(3))
            ->orderByDesc('r.created_at')->limit(3)->get(['h.id as heat_id', 'h.heat_number', 'r.reading_number', 'r.created_at']);
        foreach ($bad as $b) {
            $items[] = ['tag' => 'Melt deviant', 'tone' => 'red', 'at' => Carbon::parse($b->created_at), 'ref' => 'Heat '.$b->heat_number, 'text' => 'Chemistry reading '.$b->reading_number.' is outside the grade limits. Correct the melt before it is tapped.', 'actions' => [['Review heat', route('heats.show', $b->heat_id), true]]];
        }
        foreach (NonconformanceReport::where('status', '!=', 'CLOSED')->orderByRaw("case when severity in ('CRITICAL','MAJOR') then 0 else 1 end")->orderByDesc('reported_at')->limit(3)->get() as $n) {
            $items[] = ['tag' => 'Quality NCR', 'tone' => 'red', 'at' => $n->reported_at ?? $n->created_at, 'ref' => $n->ncr_number, 'text' => $n->title.($n->quantity_affected ? ' — '.$n->quantity_affected.' pieces affected.' : ''), 'actions' => [['View NCR', route('ncrs.show', $n), false]]];
        }
        $u = auth()->user();
        $apr = ApprovalRequest::where('status', 'PENDING')->when($u && ! $u->isAdmin(), fn ($q) => $q->where('requested_by_id', '!=', $u->id)->whereIn('permission', $u->permissions()))->orderByDesc('created_at')->limit(3)->get();
        foreach ($apr as $a) {
            $items[] = ['tag' => 'Approval', 'tone' => 'blue', 'at' => $a->created_at, 'ref' => null, 'text' => $a->actionLabel().($a->subject_label ? ' — '.$a->subject_label : '').' is waiting for a decision.', 'actions' => [['Review and approve', route('approvals.index'), false]]];
        }
        $late = ProductionOrder::open()->whereDate('required_date', '<', today())->orderBy('required_date')->limit(2)->with('product')->get();
        foreach ($late as $o) {
            $items[] = ['tag' => 'Overdue', 'tone' => 'amber', 'at' => $o->required_date, 'ref' => $o->production_order_number, 'text' => ($o->product?->name ?? 'Order').' was due '.$o->required_date->format('d M').' and is still open.', 'actions' => [['Open orders', route('production-orders.index'), false]]];
        }
        usort($items, fn ($a, $b) => $b['at'] <=> $a['at']);

        return array_slice($items, 0, 6);
    }

    /** Moulding lines running, furnaces and what each is doing right now. */
    public function floor(): array
    {
        $cards = [];
        foreach (MouldingRun::whereIn('status', ['IN_PROGRESS', 'STARTED', 'RUNNING'])->orderByDesc('started_at')->limit(3)->get() as $r) {
            $plan = (int) $r->planned_mould_qty;
            $cards[] = ['kind' => 'Moulding', 'name' => $r->run_number, 'sub' => trim(($r->moulding_method ?? '').' '.($r->sand_system ?? '')), 'state' => 'Running', 'tone' => 'blue',
                'progress' => $plan > 0 ? min(100, round(100 * $r->moulds_made_qty / $plan)) : null, 'meta' => $r->moulds_made_qty.' of '.($plan ?: '—').' moulds', 'url' => route('moulding-runs.show', $r)];
        }
        $heats = Heat::where('status', 'IN_PROGRESS')->with('furnace')->orderByDesc('actual_start_at')->limit(3)->get();
        $busy = [];
        foreach ($heats as $h) {
            $busy[$h->furnace_id] = true;
            $cards[] = ['kind' => 'Furnace', 'name' => $h->furnace?->name ?? $h->furnace?->code ?? 'Furnace', 'sub' => 'Heat '.$h->heat_number, 'state' => 'Melting', 'tone' => 'amber',
                'progress' => null, 'meta' => ($h->target_temperature_c ? 'Target '.number_format((float) $h->target_temperature_c).' °C' : '').($h->target_metal_weight_kg ? ' · '.number_format($h->target_metal_weight_kg / 1000, 1).' t' : ''), 'url' => route('heats.show', $h)];
        }
        foreach (Furnace::where('status', 'ACTIVE')->orderBy('code')->limit(6)->get() as $f) {
            if (! isset($busy[$f->id]) && count($cards) < 6) {
                $cards[] = ['kind' => 'Furnace', 'name' => $f->name ?? $f->code, 'sub' => $f->furnace_type ?? '', 'state' => 'Idle', 'tone' => 'grey', 'progress' => null, 'meta' => $f->rated_capacity_kg ? number_format($f->rated_capacity_kg / 1000, 1).' t rated' : '', 'url' => route('furnaces.index')];
            }
        }

        return array_slice($cards, 0, 6);
    }

    public function queue(int $n = 6)
    {
        $rows = ProductionOrder::open()->with(['product', 'salesOrderLine.salesOrder.customer'])->orderByRaw('required_date is null')->orderBy('required_date')->limit($n)->get();
        $rows->each(function ($o) {
            $o->cover = $o->product_id ? Attachment::coverFor('product', $o->product_id) : null;
        });

        return $rows;
    }

    /** Latest sand test for each sand system that has been tested, newest first. */
    public function sand(): array
    {
        $out = [];
        $names = SandSystem::pluck('name', 'id');
        $seen = [];
        foreach (MeasurementSet::with('measurements')->where('kind', 'SAND')->whereNotNull('sand_system_id')->orderByDesc('taken_at')->limit(20)->get() as $set) {
            if (isset($seen[$set->sand_system_id]) || ! isset($names[$set->sand_system_id])) {
                continue;
            }
            $seen[$set->sand_system_id] = true;
            $out[] = ['system' => $names[$set->sand_system_id], 'set' => $set];
            if (count($out) === 2) {
                break;
            }
        }

        return $out;
    }

    public function all(): array
    {
        $daily = $this->dailyPours();
        $sum = fn ($k) => array_sum(array_column($daily, $k));
        $poured = $sum('poured_kg');

        return [
            'daily' => $daily, 'kpis' => $this->kpis($daily), 'attention' => $this->attention(), 'floor' => $this->floor(), 'queue' => $this->queue(), 'sand' => $this->sand(),
            'yieldTotal' => $poured > 0 ? round(100 * $sum('good_kg') / $poured, 1) : null,
            'queueTotal' => ProductionOrder::open()->count(),
            'u' => auth()->user(),
        ];
    }
}
