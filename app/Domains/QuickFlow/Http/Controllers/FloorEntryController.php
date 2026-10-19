<?php

namespace App\Domains\QuickFlow\Http\Controllers;

use App\Domains\QuickFlow\Models\QuickFloorEntry;
use App\Domains\QuickFlow\Services\NextHeatPlan;
use App\Http\Controllers\Controller;
use App\Models\Heat;
use App\Models\MouldingRun;
use App\Models\Pour;
use App\Models\ProductionOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Typed-in counts: moulds made for an order, and moulds poured from a heat. Each also writes the usual moulding run or pour record. */
class FloorEntryController extends Controller
{
    private const OPEN = ['RELEASED', 'IN_PROGRESS'];

    public function index(NextHeatPlan $plan): View
    {
        $orders = $plan->orders();

        return view('quickflow::floor.index', [
            'orders' => $orders->filter(fn ($o) => $o['ordered'] > $o['poured']), 'awaiting' => $orders->filter(fn ($o) => $o['awaiting'] > 0),
            'heats' => Heat::with(['ironGrade', 'furnace', 'thermalTests'])->whereNotIn('status', ['CANCELLED', 'CLOSED'])->orderByDesc('created_at')->limit(15)->get(),
            'lastPerMould' => QuickFloorEntry::orderBy('created_at')->get()->pluck('pieces_per_mould', 'production_order_id'),
            'recent' => QuickFloorEntry::with(['productionOrder.product', 'heat'])->orderByDesc('created_at')->limit(15)->get(),
        ]);
    }

    public function made(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'production_order_id' => ['required', 'uuid', Rule::exists('production_orders', 'id')->whereIn('status', self::OPEN)],
            'moulds' => ['required', 'integer', 'min:1', 'max:100000'],
            'pieces_per_mould' => ['required', 'integer', 'min:1', 'max:1000'],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'entered_by' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        DB::transaction(function () use ($data) {
            $order = ProductionOrder::lockForUpdate()->findOrFail($data['production_order_id']);
            $run = MouldingRun::create([
                'run_number' => MouldingRun::nextRunNumber(), 'production_order_id' => $order->id, 'run_date' => $data['entry_date'],
                'operator_name' => $data['entered_by'] ?? null, 'planned_mould_qty' => $data['moulds'], 'moulds_made_qty' => $data['moulds'],
                'accepted_mould_qty' => $data['moulds'], 'status' => 'COMPLETED', 'completed_at' => now(), 'notes' => $data['notes'] ?? null,
            ]);
            QuickFloorEntry::create(['kind' => QuickFloorEntry::MADE, 'moulding_run_id' => $run->id] + $data);
            if ($order->status === 'RELEASED') {
                $order->update(['status' => 'IN_PROGRESS', 'actual_started_at' => now()]);
            }
        });

        return back()->with('success', "{$data['moulds']} moulds recorded.");
    }

    public function poured(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'heat_id' => ['required', 'uuid', Rule::exists('heats', 'id')->whereNotIn('status', ['CANCELLED', 'CLOSED'])],
            'production_order_id' => ['required', 'uuid', Rule::exists('production_orders', 'id')->whereIn('status', self::OPEN)],
            'moulds' => ['required', 'integer', 'min:1', 'max:100000'],
            'pieces_per_mould' => ['required', 'integer', 'min:1', 'max:1000'],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'entered_by' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);
        $heat = Heat::with(['thermalTests', 'ironGrade'])->findOrFail($data['heat_id']);
        $advice = $this->thermalAdvice($heat);

        DB::transaction(function () use ($data, $advice) {
            $prefix = 'P-'.now()->format('Ymd').'-';
            $last = Pour::where('pour_number', 'like', $prefix.'%')->lockForUpdate()->pluck('pour_number')->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;
            $pour = Pour::create([
                'pour_number' => $prefix.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT), 'heat_id' => $data['heat_id'], 'status' => 'COMPLETED',
                'started_at' => now(), 'completed_at' => now(), 'operator_name' => $data['entered_by'] ?? null, 'notes' => $data['notes'] ?? null,
                'gate_mode' => 'ADVISORY', 'gate_note' => $advice,
            ]);
            QuickFloorEntry::create(['kind' => QuickFloorEntry::POURED, 'pour_id' => $pour->id] + $data);
        });

        return back()->with('success', "{$data['moulds']} moulds poured.")->with('warning', $advice);
    }

    /** What the latest thermal test on the heat says. Shown and recorded on the pour, never a reason to refuse it. */
    private function thermalAdvice(Heat $heat): ?string
    {
        $latest = $heat->thermalTests->first();
        if (! $latest) {
            return "No thermal test has been recorded on {$heat->heat_number}.";
        }
        if ($latest->result === 'PASS') {
            return null;
        }
        if ($latest->result === 'FAIL') {
            return 'The latest thermal test on '.$heat->heat_number.' is out of limits: '.collect($latest->findings)->take(2)->implode(' ');
        }

        return "The latest thermal test on {$heat->heat_number} could not be judged against a window.";
    }
}
