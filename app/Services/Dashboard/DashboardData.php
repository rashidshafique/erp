<?php

namespace App\Services\Dashboard;

use App\Models\ApprovalRequest;
use App\Models\Attachment;
use App\Models\Enquiry;
use App\Models\Furnace;
use App\Models\Gauge;
use App\Models\Heat;
use App\Models\MeasurementSet;
use App\Models\MouldingRun;
use App\Models\MouldingStation;
use App\Models\NonconformanceReport;
use App\Models\ProductionOrder;
use App\Models\Quotation;
use App\Models\SandSystem;
use App\Support\CalibrationRules;
use App\Support\Formats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

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
        $hasEnquiries = Route::has('enquiries.index');
        $enqOpen = $hasEnquiries ? Enquiry::whereNotIn('status', ['WON', 'LOST', 'CANCELLED', 'CLOSED'])->count() : 0;
        $enqSpark = $hasEnquiries ? $this->perDay(Enquiry::class, 'created_at') : [];
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
            'enquiries' => ['open' => $enqOpen, 'today' => $hasEnquiries ? Enquiry::whereDate('created_at', today())->count() : 0, 'spark' => $enqSpark, 'quotes' => $hasEnquiries ? Quotation::where('status', 'ISSUED')->count() : 0],
            'due' => ['count' => $due->count(), 'qty' => (float) (clone $due)->sum('planned_qty'), 'overdue' => $overdue],
            'heats' => ['total' => $heatsToday->count(), 'done' => $heatsDone, 'tonnes' => $tapped / 1000, 'running' => Heat::where('status', 'IN_PROGRESS')->count()],
            'scrap' => ['rate' => $scrap, 'delta' => $scrap !== null && $scrapPrev !== null ? $scrap - $scrapPrev : null, 'spark' => array_map(fn ($d) => $d['scrap'] ?? 0, $daily)],
            'ncrs' => ['open' => (clone $ncrOpen)->count(), 'major' => (clone $ncrOpen)->whereIn('severity', ['MAJOR', 'CRITICAL'])->count(), 'minor' => (clone $ncrOpen)->whereNotIn('severity', ['MAJOR', 'CRITICAL'])->count()],
        ];
    }

    /**
     * The four headline numbers of the dashboard design: open quotes, order book, on-time dispatch, internal rejection.
     *
     * @param  array<int, array<string, mixed>>  $daily
     * @return array<int, array{label: string, value: string, note: string, tone: string}>
     */
    public function headline(array $daily): array
    {
        $quotes = Quotation::where('status', 'ISSUED');
        $expiring = (clone $quotes)->whereDate('valid_until', '>=', today())->whereDate('valid_until', '<=', today()->addDays(7))->count();

        $orders = \App\Domains\Sales\Models\SalesOrder::whereNotIn('status', ['COMPLETED', 'CANCELLED', 'CLOSED', 'DRAFT'])->count();
        $openWork = ProductionOrder::open()->count();

        $sent = \App\Domains\Dispatch\Models\Dispatch::whereNotNull('dispatched_at')->where('dispatched_at', '>=', now()->subDays(30))->get(['planned_dispatch_at', 'dispatched_at']);
        $onTime = $sent->filter(fn ($d) => $d->planned_dispatch_at === null || $d->dispatched_at->lte($d->planned_dispatch_at->copy()->endOfDay()))->count();

        $kpis = $this->kpis($daily)['scrap'];
        $target = 3.0;

        return [
            ['label' => 'Open quotes', 'value' => Formats::number((int) (clone $quotes)->count()), 'note' => $expiring.' expire this week', 'tone' => ''],
            ['label' => 'Order book', 'value' => Formats::number($orders), 'note' => $openWork.' production order'.($openWork === 1 ? '' : 's').' open', 'tone' => ''],
            ['label' => 'On-time dispatch, 30 days', 'value' => $sent->isEmpty() ? '—' : number_format(100 * $onTime / $sent->count(), 1).'%', 'note' => $sent->isEmpty() ? 'No dispatches yet' : $onTime.' of '.$sent->count().' on time', 'tone' => $sent->isNotEmpty() && $onTime === $sent->count() ? 'ok' : ''],
            ['label' => 'Internal rejection', 'value' => $kpis['rate'] === null ? '—' : number_format($kpis['rate'], 1).'%', 'note' => $kpis['rate'] === null ? 'Nothing poured in 7 days' : 'Target '.number_format($target, 1).'%'.($kpis['delta'] !== null ? ', '.($kpis['delta'] > 0 ? 'up ' : 'down ').number_format(abs($kpis['delta']), 1).' pts' : ''), 'tone' => $kpis['rate'] !== null && $kpis['rate'] > $target ? 'bad' : ($kpis['rate'] !== null ? 'ok' : '')],
        ];
    }

    /**
     * Records on their way from enquiry to dispatch, a few per stage, newest first.
     *
     * @return array<int, array{label: string, count: int, cards: array<int, array{code: string, name: string, sub: string, tone: string, url: string}>}>
     */
    public function pipeline(int $per = 3): array
    {
        $stage = fn (string $label, $query, \Closure $card, string $order = 'created_at') => [
            'label' => $label, 'count' => (clone $query)->count(), 'cards' => (clone $query)->orderByDesc($order)->limit($per)->get()->map($card)->all(),
        ];

        return [
            $stage('Enquiry', Enquiry::where('status', 'NEW')->with('customer'),
                fn ($e) => ['code' => $e->code, 'name' => $e->title ?: 'Enquiry', 'sub' => $e->customer?->name ?? '', 'tone' => '', 'url' => route('enquiries.show', $e)]),
            $stage('Feasibility', Enquiry::where('status', 'FEASIBILITY')->with('customer')->withCount(['items as pending_items_count' => fn ($q) => $q->where('feasibility_status', 'PENDING')]),
                fn ($e) => ['code' => $e->code, 'name' => $e->title ?: 'Enquiry', 'sub' => $e->pending_items_count > 0 ? $e->pending_items_count.' to review' : ($e->customer?->name ?? ''), 'tone' => $e->pending_items_count > 0 ? 'warn' : '', 'url' => route('enquiries.show', $e)]),
            $stage('Quoted', Quotation::where('status', 'ISSUED')->with('enquiry.customer'),
                fn ($q) => ['code' => $q->code, 'name' => $q->enquiry?->title ?: 'Quotation', 'sub' => $q->valid_until ? 'Expires '.$q->valid_until->displayShortDate() : ($q->enquiry?->customer?->name ?? ''), 'tone' => $q->valid_until && $q->valid_until->lte(today()->addDays(7)) ? 'bad' : '', 'url' => route('quotations.show', $q)]),
            $stage('Order', \App\Domains\Sales\Models\SalesOrder::whereIn('status', ['DRAFT', 'UNDER_REVIEW', 'CONFIRMED'])->with('customer'),
                fn ($o) => ['code' => $o->order_number, 'name' => $o->customer?->name ?? 'Sales order', 'sub' => str_replace('_', ' ', ucfirst(strtolower($o->status))), 'tone' => '', 'url' => route('sales-orders.show', $o)]),
            $stage('Production', ProductionOrder::open()->with('product'),
                fn ($o) => ['code' => $o->production_order_number, 'name' => $o->product?->name ?? 'Production order', 'sub' => str_replace('_', ' ', ucfirst(strtolower($o->status))), 'tone' => $o->status === 'IN_PROGRESS' ? 'accent' : '', 'url' => route('production-orders.show', $o)]),
            $stage('QC', \App\Models\QualityInspection::where('status', '!=', 'COMPLETED')->with('lot'),
                fn ($i) => ['code' => $i->inspection_number, 'name' => $i->lot?->lot_number ?? 'Inspection', 'sub' => str_replace('_', ' ', ucfirst(strtolower($i->status))), 'tone' => 'warn', 'url' => route('inspections.show', $i)]),
            $stage('Dispatch', \App\Domains\Dispatch\Models\Dispatch::whereNull('dispatched_at')->with('customer'),
                fn ($d) => ['code' => $d->dispatch_number, 'name' => $d->customer?->name ?? 'Dispatch', 'sub' => $d->planned_dispatch_at ? 'Planned '.$d->planned_dispatch_at->displayShortDate() : '', 'tone' => '', 'url' => route('dispatches.show', $d)]),
        ];
    }

    /**
     * Today's heats per furnace, with the metal each has made so far (poured, or charged for a cupola), and the next heat on the plan.
     *
     * @return array{rows: array<int, array{code: string, grade: string, heats: int, state: string, tone: string, metal: string}>, poured: float, planned: float, charged: float, next: array{number: string, at: \Illuminate\Support\Carbon, furnace: string, url: string}|null}
     */
    public function melt(): array
    {
        $heats = Heat::with(['furnace', 'ironGrade'])->where(fn ($q) => $q->whereDate('planned_start_at', today())->orWhereDate('actual_start_at', today()))->get();
        $done = fn ($h) => $h->tapped_at || in_array($h->status, ['POURED', 'CLOSED', 'COMPLETED'], true);
        $rows = $heats->groupBy('furnace_id')->map(function ($group) use ($done) {
            $finished = $group->filter($done)->count();
            $running = $group->contains(fn ($h) => $h->status === 'IN_PROGRESS');
            $next = $group->reject($done)->sortBy('planned_start_at')->first();
            $cupola = $group->first()->isCupola();
            $kg = $group->sum(fn ($h) => $cupola ? $h->chargedMetalKg() : (float) ($h->actual_tapped_weight_kg ?: 0));

            return [
                'code' => $group->first()->furnace?->code ?? 'Furnace', 'grade' => $group->first()->ironGrade?->code ?? $group->first()->process_type ?? '',
                'heats' => $group->count(), 'state' => $running ? 'Melting' : ($finished === $group->count() || $finished > 0 ? $finished.' done' : ($next?->planned_start_at?->displayTime() ?? 'Planned')),
                'tone' => $running ? 'accent' : ($finished > 0 ? 'ok' : ''),
                'metal' => number_format($kg / 1000, 1).' t '.($cupola ? 'charged' : 'poured'),
            ];
        })->values()->all();

        $isCupola = fn ($h) => $h->isCupola();
        $next = Heat::with('furnace')->whereNull('actual_start_at')->whereNull('tapped_at')->whereNotNull('planned_start_at')->where('planned_start_at', '>=', now())
            ->whereNotIn('status', ['CLOSED', 'CANCELLED', 'POURED', 'COMPLETED'])->orderBy('planned_start_at')->first();

        return [
            'rows' => $rows,
            'poured' => $heats->reject($isCupola)->sum(fn ($h) => (float) ($h->actual_tapped_weight_kg ?: 0)) / 1000,
            'planned' => $heats->reject($isCupola)->sum(fn ($h) => (float) ($h->target_metal_weight_kg ?: 0)) / 1000,
            'charged' => $heats->filter($isCupola)->sum(fn ($h) => $h->chargedMetalKg()) / 1000,
            'next' => $next ? ['number' => $next->heat_number, 'at' => $next->planned_start_at, 'furnace' => $next->furnace?->code ?? '', 'url' => route('heats.show', $next)] : null,
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
            $items[] = ['tag' => 'Overdue', 'tone' => 'amber', 'at' => $o->required_date, 'ref' => $o->production_order_number, 'text' => ($o->product?->name ?? 'Order').' was due '.$o->required_date->displayShortDate().' and is still open.', 'actions' => [['Open orders', route('production-orders.index'), false]]];
        }
        if (Route::has('gauges.index')) {
            $dueDays = (int) CalibrationRules::get('due_soon_days');
            $dueBy = today()->addDays($dueDays);
            $needsCalibration = Gauge::where('status', 'IN_SERVICE')->where('requires_calibration', true);
            $overdue = (clone $needsCalibration)->where(fn ($q) => $q->whereNull('next_due_on')->orWhereDate('next_due_on', '<', today()));
            $dueSoon = (clone $needsCalibration)->whereDate('next_due_on', '>=', today())->whereDate('next_due_on', '<=', $dueBy);
            if (($count = $overdue->count()) > 0) {
                $items[] = ['tag' => 'Calibration', 'tone' => 'red', 'at' => now(), 'ref' => null, 'text' => $count.' instrument'.($count === 1 ? ' is' : 's are').' overdue or never calibrated. Results measured with them will not stand up to an audit.', 'actions' => [['Open register', route('gauges.index', ['state' => 'overdue']), true]]];
            } elseif (($count = $dueSoon->count()) > 0) {
                $items[] = ['tag' => 'Calibration', 'tone' => 'amber', 'at' => now(), 'ref' => null, 'text' => $count.' instrument'.($count === 1 ? ' is' : 's are').' due for calibration within '.$dueDays.' days.', 'actions' => [['Open register', route('gauges.index', ['state' => 'soon']), false]]];
            }
        }
        $reorder = app(\App\Services\Stock\StockLevels::class)->needingReorder();
        if ($reorder->isNotEmpty()) {
            $out = $reorder->where('out', true)->count();
            $items[] = ['tag' => 'Reorder', 'tone' => $out ? 'red' : 'amber', 'at' => now(), 'ref' => null,
                'text' => $reorder->count().' material'.($reorder->count() === 1 ? ' is' : 's are').' at or below minimum stock'.($out ? ", {$out} out of stock" : '').'.',
                'actions' => [['Open reorder list', Route::has('stock-reorder.index') ? route('stock-reorder.index') : route('stock-report.index'), $out > 0]]];
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
                'progress' => null, 'meta' => ($h->target_temperature_c ? 'Target '.Formats::number((float) $h->target_temperature_c).' °C' : '').($h->target_metal_weight_kg ? ' · '.Formats::number($h->target_metal_weight_kg / 1000, 1).' t' : ''), 'url' => route('heats.show', $h)];
        }
        foreach (Furnace::where('status', 'ACTIVE')->orderBy('code')->limit(6)->get() as $f) {
            if (! isset($busy[$f->id]) && count($cards) < 6) {
                $cards[] = ['kind' => 'Furnace', 'name' => $f->name ?? $f->code, 'sub' => $f->furnace_type ?? '', 'state' => 'Idle', 'tone' => 'grey', 'progress' => null, 'meta' => $f->rated_capacity_kg ? Formats::number($f->rated_capacity_kg / 1000, 1).' t rated' : '', 'url' => route('furnaces.index')];
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

    /**
     * One row for every active furnace and every moulding station: what it is doing now, or what is next.
     *
     * @return array<int, array{kind: string, code: string, state: string, tone: string, job: string, progress: ?int, meta: string, url: string}>
     */
    public function board(): array
    {
        $rows = [];
        $heats = Heat::where('status', 'IN_PROGRESS')->orderByDesc('actual_start_at')->get()->keyBy('furnace_id');
        $next = Heat::where('status', 'PLANNED')->where('planned_start_at', '>=', now())->whereDate('planned_start_at', today())
            ->orderBy('planned_start_at')->get()->unique('furnace_id')->keyBy('furnace_id');
        foreach (Furnace::where('status', 'ACTIVE')->orderBy('code')->get() as $f) {
            $h = $heats[$f->id] ?? null;
            $n = $next[$f->id] ?? null;
            $rows[] = $h
                ? ['kind' => 'Furnace', 'code' => $f->code, 'state' => 'Melting', 'tone' => 'amber', 'job' => 'Heat '.$h->heat_number, 'progress' => null,
                    'meta' => $h->actual_start_at ? $h->actual_start_at->diffForHumans(null, true, true) : '', 'url' => route('heats.show', $h)]
                : ['kind' => 'Furnace', 'code' => $f->code, 'state' => 'Idle', 'tone' => 'grey', 'job' => $n ? 'Next: heat '.$n->heat_number.' at '.$n->planned_start_at->displayTime() : '—', 'progress' => null,
                    'meta' => '', 'url' => $n ? route('heats.show', $n) : route('furnaces.index')];
        }
        $runs = MouldingRun::whereIn('status', ['IN_PROGRESS', 'STARTED', 'RUNNING'])->whereNotNull('moulding_station_id')
            ->with('patternRevision.pattern')->orderByDesc('started_at')->get()->unique('moulding_station_id')->keyBy('moulding_station_id');
        foreach (MouldingStation::where('status', '!=', 'INACTIVE')->orderBy('code')->get() as $s) {
            $r = $runs[$s->id] ?? null;
            if ($r) {
                $plan = (int) $r->planned_mould_qty;
                $rows[] = ['kind' => 'Moulding', 'code' => $s->code, 'state' => 'Running', 'tone' => 'blue',
                    'job' => $r->run_number.($r->patternRevision ? ' · '.$r->patternRevision->pattern->code.' · '.$r->patternRevision->shortCode() : ''),
                    'progress' => $plan > 0 ? min(100, (int) round(100 * $r->moulds_made_qty / $plan)) : null,
                    'meta' => $r->moulds_made_qty.'/'.($plan ?: '—').' moulds', 'url' => route('moulding-runs.show', $r)];
            } else {
                $down = $s->status === 'MAINTENANCE';
                $rows[] = ['kind' => 'Moulding', 'code' => $s->code, 'state' => $down ? 'Maintenance' : 'Idle', 'tone' => $down ? 'red' : 'grey',
                    'job' => $s->name, 'progress' => null, 'meta' => '', 'url' => route('moulding-teams.index')];
            }
        }

        return $rows;
    }

    /**
     * Today's heats per furnace and moulding runs per station as bars on a shift window (06:00–18:00, stretched to cover
     * anything outside it). Positions are percentages of the window.
     *
     * @return array{from: Carbon, to: Carbon, now: ?float, hours: array<int, array{label: string, at: float}>, lanes: array<string, array<int, array{label: string, left: float, width: float, tone: string, url: string}>>}
     */
    public function timeline(): array
    {
        $dayStart = today();
        $dayEnd = today()->endOfDay();
        $spans = [];
        foreach (Heat::with('furnace')->whereNotNull('actual_start_at')->where('actual_start_at', '<=', $dayEnd)
            ->where(fn ($q) => $q->whereNull('tapped_at')->orWhere('tapped_at', '>=', $dayStart))->orderBy('actual_start_at')->get() as $h) {
            $end = $h->tapped_at ?? $h->closed_at ?? ($h->status === 'IN_PROGRESS' ? now() : null);
            if ($end && $end->gte($dayStart)) {
                $spans[] = ['lane' => $h->furnace?->code ?? 'Furnace', 'start' => $h->actual_start_at, 'end' => $end, 'label' => $h->heat_number, 'tone' => 'amber', 'url' => route('heats.show', $h)];
            }
        }
        foreach (MouldingRun::with('station')->whereNotNull('started_at')->where('started_at', '<=', $dayEnd)
            ->where(fn ($q) => $q->whereNull('completed_at')->orWhere('completed_at', '>=', $dayStart))->orderBy('started_at')->get() as $r) {
            $end = $r->completed_at ?? (in_array($r->status, ['IN_PROGRESS', 'STARTED', 'RUNNING'], true) ? now() : null);
            if ($end && $end->gte($dayStart)) {
                $spans[] = ['lane' => $r->station?->code ?? 'Moulding', 'start' => $r->started_at, 'end' => $end, 'label' => $r->run_number, 'tone' => 'blue', 'url' => route('moulding-runs.show', $r)];
            }
        }

        $from = today()->setTime(6, 0);
        $to = today()->setTime(18, 0);
        foreach ($spans as $s) {
            $from = $from->min($s['start']->max($dayStart));
            $to = $to->max($s['end']->min($dayEnd));
        }
        $from = $from->copy()->startOfHour();
        $to = $to->copy()->ceilHour();
        $total = max(1, $from->diffInSeconds($to));
        $clip = fn (Carbon $t) => $t->copy()->max($from)->min($to);
        $at = fn (Carbon $t) => round(100 * $from->diffInSeconds($clip($t)) / $total, 2);

        $lanes = [];
        foreach ($spans as $s) {
            $width = round(100 * $clip($s['start'])->diffInSeconds($clip($s['end'])) / $total, 2);
            $lanes[$s['lane']][] = ['label' => $s['label'], 'left' => $at($s['start']), 'width' => max(0.8, $width), 'tone' => $s['tone'], 'url' => $s['url']];
        }
        ksort($lanes);
        $hours = [];
        $step = $from->diffInHours($to) > 12 ? 3 : 2;
        for ($t = $from->copy(); $t->lte($to); $t->addHours($step)) {
            $hours[] = ['label' => $t->displayTime(), 'at' => $at($t)];
        }

        return ['from' => $from, 'to' => $to, 'now' => now()->between($from, $to) ? $at(now()) : null, 'hours' => $hours, 'lanes' => $lanes];
    }

    /** Everything the telemetry layout needs: the shared figures plus the full floor board, the shift timeline and today's moulds. */
    public function telemetry(): array
    {
        return $this->all() + [
            'board' => $this->board(),
            'timeline' => $this->timeline(),
            'mouldsToday' => (int) MouldingRun::whereDate('run_date', today())->sum('moulds_made_qty'),
        ];
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
