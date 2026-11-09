<?php

namespace App\Domains\Maintenance\Http\Controllers;

use App\Domains\Maintenance\Models\EquipmentBreakdown;
use App\Domains\Maintenance\Models\EquipmentMeter;
use App\Domains\Maintenance\Models\MaintenancePlan;
use App\Domains\Maintenance\Models\MaintenanceWorkOrder;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Equipment maintenance (design screen 7k): plans by date or meter, open work orders and breakdowns with downtime.
 * The tables predate this screen; the models and this controller are the new part.
 */
class EquipmentMaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $tabs = ['work-orders' => 'Work orders', 'plans' => 'Plans', 'breakdowns' => 'Breakdowns', 'assets' => 'Assets'];
        $tab = array_key_exists((string) $request->query('tab'), $tabs) ? (string) $request->query('tab') : 'work-orders';

        $equipment = Equipment::query()->with('meters')->orderBy('code')->get();

        $plans = MaintenancePlan::query()->with('equipment.meters')->active()
            ->orderByRaw('next_due_at is null')->orderBy('next_due_at')->orderBy('code')->get();

        $openWorkOrders = MaintenanceWorkOrder::query()->with(['equipment', 'plan.equipment.meters'])->open()
            ->orderByRaw("case priority when 'URGENT' then 0 when 'HIGH' then 1 when 'NORMAL' then 2 else 3 end")
            ->orderByRaw('scheduled_start_at is null')->orderBy('scheduled_start_at')->orderBy('created_at')->get();

        $unresolvedBreakdowns = EquipmentBreakdown::query()->with('equipment')->unresolved()
            ->orderByDesc('started_at')->get();

        $breakdowns = EquipmentBreakdown::query()->with('equipment')->orderByDesc('started_at')->limit(100)->get();

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $monthBreakdowns = $breakdowns->filter(fn (EquipmentBreakdown $breakdown): bool => $breakdown->started_at->betweenIncluded($monthStart, $monthEnd));

        $downtimeHours = $monthBreakdowns->sum(fn (EquipmentBreakdown $breakdown): float => $breakdown->downtimeHours());
        $downtimeTop = $monthBreakdowns
            ->groupBy(fn (EquipmentBreakdown $breakdown): string => $breakdown->equipment?->code ?? '—')
            ->map(fn ($group, string $code): array => ['code' => $code, 'hours' => $group->sum(fn (EquipmentBreakdown $breakdown): float => $breakdown->downtimeHours())])
            ->sortByDesc('hours')
            ->first();

        $meterPlans = $plans
            ->filter(fn (MaintenancePlan $plan): bool => $plan->next_due_meter !== null && $plan->meter() !== null)
            ->sortBy(fn (MaintenancePlan $plan): float => (float) $plan->next_due_meter - (float) $plan->meter()?->current_reading)
            ->values();

        $completedThisMonth = MaintenanceWorkOrder::query()
            ->whereNotNull('actual_end_at')
            ->whereBetween('actual_end_at', [$monthStart, $monthEnd])
            ->get();

        return view('maintenance::equipment.index', [
            'tab' => $tab,
            'tabs' => $tabs,
            'equipment' => $equipment,
            'plans' => $plans,
            'openWorkOrders' => $openWorkOrders,
            'unresolvedBreakdowns' => $unresolvedBreakdowns,
            'breakdowns' => $breakdowns,
            'monthBreakdowns' => $monthBreakdowns,
            'monthName' => $monthStart->format('F'),
            'meterPlans' => $meterPlans,
            'nextMeterPlan' => $meterPlans->first(),
            'overdueCount' => $openWorkOrders->filter(fn (MaintenanceWorkOrder $workOrder): bool => $workOrder->dueState() === 'overdue')->count(),
            'planCompliance' => $plans->isEmpty() ? null : (int) round((1 - $plans->filter(fn (MaintenancePlan $plan): bool => $plan->isDue())->count() / $plans->count()) * 100),
            'complianceTarget' => MaintenancePlan::COMPLIANCE_TARGET,
            'downtimeHours' => $downtimeHours,
            'downtimeTop' => $downtimeTop,
            'partsIssued' => $completedThisMonth->sum(fn (MaintenanceWorkOrder $workOrder): float => (float) $workOrder->parts_cost),
            'contractorCost' => $completedThisMonth->sum(fn (MaintenanceWorkOrder $workOrder): float => (float) $workOrder->other_cost),
            'planTypes' => MaintenancePlan::TYPES,
            'triggers' => MaintenancePlan::TRIGGERS,
            'workOrderTypes' => MaintenanceWorkOrder::TYPES,
            'priorities' => MaintenanceWorkOrder::PRIORITIES,
            'severities' => EquipmentBreakdown::SEVERITIES,
            'categories' => EquipmentBreakdown::CATEGORIES,
            'meterUnits' => EquipmentMeter::UNITS,
            'equipmentTypes' => Equipment::TYPES,
            'equipmentStatuses' => Equipment::STATUSES,
        ]);
    }

    public function storeEquipment(Request $request): RedirectResponse
    {
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('equipment', 'code')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'equipment_type' => ['required', Rule::in(array_keys(Equipment::TYPES))],
            'status' => ['required', Rule::in(array_keys(Equipment::STATUSES))],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'location_description' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $equipment = Equipment::create($data);

        return redirect()->route('equipment-maintenance.index')
            ->with('success', "{$equipment->code} added to the equipment register.");
    }

    public function storePlan(Request $request): RedirectResponse
    {
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('maintenance_plans', 'code')],
            'equipment_id' => ['required', 'uuid', Rule::exists('equipment', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'maintenance_type' => ['required', Rule::in(array_keys(MaintenancePlan::TYPES))],
            'trigger_type' => ['required', Rule::in(array_keys(MaintenancePlan::TRIGGERS))],
            'interval_days' => ['nullable', 'integer', 'min:1', 'max:3650', 'required_if:trigger_type,CALENDAR', 'required_if:trigger_type,BOTH'],
            'interval_meter_units' => ['nullable', 'numeric', 'gt:0', 'required_if:trigger_type,METER', 'required_if:trigger_type,BOTH'],
            'next_due_at' => ['nullable', 'date'],
            'next_due_meter' => ['nullable', 'numeric', 'min:0'],
            'estimated_duration_hours' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'requires_shutdown' => ['nullable', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $equipment = Equipment::with('meters')->findOrFail($data['equipment_id']);
        $meter = $equipment->meters->firstWhere('active', true);

        if (in_array($data['trigger_type'], ['METER', 'BOTH'], true) && $meter === null) {
            throw ValidationException::withMessages([
                'trigger_type' => "{$equipment->code} has no active meter. Add a meter below before scheduling by meter.",
            ]);
        }

        $plan = new MaintenancePlan($data);
        $plan->requires_shutdown = (bool) ($data['requires_shutdown'] ?? true);
        $plan->active = true;

        if ($plan->next_due_at === null && filled($data['interval_days'] ?? null)) {
            $plan->next_due_at = now()->addDays((int) $data['interval_days']);
        }
        if ($plan->next_due_meter === null && filled($data['interval_meter_units'] ?? null) && $meter !== null) {
            $plan->next_due_meter = (float) $meter->current_reading + (float) $data['interval_meter_units'];
        }

        $plan->save();

        return redirect()->route('equipment-maintenance.index')
            ->with('success', "{$plan->code} {$plan->name} added.");
    }

    public function storeWorkOrder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'equipment_id' => ['required', 'uuid', Rule::exists('equipment', 'id')->whereNull('deleted_at')],
            'maintenance_plan_id' => ['nullable', 'uuid', Rule::exists('maintenance_plans', 'id')
                ->where('equipment_id', $request->input('equipment_id'))],
            'maintenance_type' => ['required', Rule::in(array_keys(MaintenanceWorkOrder::TYPES))],
            'priority' => ['required', Rule::in(array_keys(MaintenanceWorkOrder::PRIORITIES))],
            'scheduled_start_at' => ['nullable', 'date'],
            'problem_description' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'string', 'max:150'],
        ]);

        $data['status'] = 'OPEN';
        $data['work_order_number'] = $this->nextNumber('MWO', MaintenanceWorkOrder::class, 'work_order_number');

        if (blank($data['problem_description'] ?? null) && filled($data['maintenance_plan_id'] ?? null)) {
            $data['problem_description'] = MaintenancePlan::find($data['maintenance_plan_id'])?->name;
        }

        $workOrder = MaintenanceWorkOrder::create($data);

        return redirect()->route('equipment-maintenance.index')
            ->with('success', "Work order {$workOrder->work_order_number} opened.");
    }

    public function startWorkOrder(MaintenanceWorkOrder $workOrder): RedirectResponse
    {
        if (! $workOrder->isOpen()) {
            return back()->with('warning', "{$workOrder->work_order_number} is already {$workOrder->status}.");
        }

        DB::transaction(function () use ($workOrder) {
            $workOrder->update([
                'status' => 'IN_PROGRESS',
                'actual_start_at' => $workOrder->actual_start_at ?? now(),
            ]);

            if ($workOrder->equipment && $workOrder->equipment->status === 'AVAILABLE') {
                $workOrder->equipment->update(['status' => 'MAINTENANCE']);
            }
        });

        return back()->with('success', "{$workOrder->work_order_number} started.");
    }

    public function completeWorkOrder(Request $request, MaintenanceWorkOrder $workOrder): RedirectResponse
    {
        if ($workOrder->status === 'COMPLETED') {
            return back()->with('warning', "{$workOrder->work_order_number} is already completed.");
        }

        $data = $request->validate([
            'work_performed' => ['required', 'string', 'max:4000'],
            'findings' => ['nullable', 'string', 'max:4000'],
            'labor_hours' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'parts_cost' => ['nullable', 'numeric', 'min:0'],
            'other_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($workOrder, $data) {
            $ended = now();

            $workOrder->update($data + [
                'status' => 'COMPLETED',
                'actual_start_at' => $workOrder->actual_start_at ?? $ended,
                'actual_end_at' => $ended,
                'completed_by' => Auth::user()?->name,
            ]);

            if ($workOrder->maintenance_plan_id !== null && $workOrder->plan !== null) {
                $plan = $workOrder->plan;
                $planData = ['last_completed_at' => $ended];

                if ($plan->interval_days !== null) {
                    $planData['next_due_at'] = $ended->copy()->addDays($plan->interval_days);
                }

                $meter = $plan->equipment?->meters->firstWhere('active', true);
                if ($plan->interval_meter_units !== null && $meter !== null) {
                    $planData['last_completed_meter'] = $meter->current_reading;
                    $planData['next_due_meter'] = (float) $meter->current_reading + (float) $plan->interval_meter_units;
                }

                $plan->update($planData);
            }

            if ($workOrder->equipment && $workOrder->equipment->status === 'MAINTENANCE') {
                $workOrder->equipment->update(['status' => 'AVAILABLE']);
            }
        });

        return back()->with('success', "{$workOrder->work_order_number} completed.");
    }

    public function storeBreakdown(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'equipment_id' => ['required', 'uuid', Rule::exists('equipment', 'id')->whereNull('deleted_at')],
            'severity' => ['required', Rule::in(array_keys(EquipmentBreakdown::SEVERITIES))],
            'started_at' => ['required', 'date', 'before_or_equal:now'],
            'failure_category' => ['nullable', Rule::in(array_keys(EquipmentBreakdown::CATEGORIES))],
            'failure_description' => ['required', 'string', 'max:2000'],
            'reported_by' => ['nullable', 'string', 'max:150'],
        ]);

        $data['breakdown_number'] = $this->nextNumber('BD', EquipmentBreakdown::class, 'breakdown_number');
        $data['status'] = 'OPEN';
        $data['reported_at'] = now();
        $data['reported_by'] = ($data['reported_by'] ?? null) ?: Auth::user()?->name;

        $breakdown = DB::transaction(function () use ($data) {
            $breakdown = EquipmentBreakdown::create($data);
            $breakdown->equipment?->update(['status' => 'BREAKDOWN']);

            return $breakdown;
        });

        return back()->with('success', "Breakdown {$breakdown->breakdown_number} logged.");
    }

    public function restoreBreakdown(Request $request, EquipmentBreakdown $breakdown): RedirectResponse
    {
        if ($breakdown->restored_at !== null) {
            return back()->with('warning', "{$breakdown->breakdown_number} is already restored.");
        }

        $data = $request->validate([
            'restored_at' => ['nullable', 'date', 'after_or_equal:'.$breakdown->started_at->toDateTimeString(), 'before_or_equal:now'],
            'root_cause' => ['nullable', 'string', 'max:2000'],
            'corrective_action' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($breakdown, $data) {
            $breakdown->update($data + [
                'status' => 'RESTORED',
                'restored_at' => $data['restored_at'] ?? now(),
                'restored_by' => Auth::user()?->name,
            ]);

            if ($breakdown->equipment && $breakdown->equipment->status === 'BREAKDOWN') {
                $breakdown->equipment->update(['status' => 'AVAILABLE']);
            }
        });

        return back()->with('success', "{$breakdown->breakdown_number} restored after ".number_format($breakdown->fresh()->downtimeHours(), 1).' h.');
    }

    public function storeMeter(Request $request): RedirectResponse
    {
        if (is_string($request->input('meter_code'))) {
            $request->merge(['meter_code' => strtoupper(trim($request->input('meter_code')))]);
        }

        $data = $request->validate([
            'equipment_id' => ['required', 'uuid', Rule::exists('equipment', 'id')->whereNull('deleted_at')],
            'meter_code' => ['required', 'string', 'max:40',
                Rule::unique('equipment_meters', 'meter_code')->where('equipment_id', $request->input('equipment_id'))],
            'name' => ['required', 'string', 'max:150'],
            'unit' => ['required', Rule::in(array_keys(EquipmentMeter::UNITS))],
            'current_reading' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['current_reading'] = $data['current_reading'] ?? 0;
        $data['is_cumulative'] = true;
        $data['active'] = true;

        $meter = EquipmentMeter::create($data);

        return back()->with('success', "Meter {$meter->meter_code} added.");
    }

    public function storeReading(Request $request, EquipmentMeter $meter): RedirectResponse
    {
        $data = $request->validate([
            'reading_value' => ['required', 'numeric', 'min:0'],
            'recorded_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($meter->is_cumulative && (float) $data['reading_value'] < (float) $meter->current_reading) {
            throw ValidationException::withMessages([
                'reading_value' => "A cumulative meter cannot go backwards. {$meter->meter_code} last read "
                    .number_format((float) $meter->current_reading, 2).' '.$meter->unit.'.',
            ]);
        }

        $meter->recordReading(
            (float) $data['reading_value'],
            'MANUAL',
            Auth::user()?->name,
            $data['notes'] ?? null,
            isset($data['recorded_at']) ? \Illuminate\Support\Carbon::parse($data['recorded_at']) : null,
        );

        return back()->with('success', "Reading saved for {$meter->meter_code}.");
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    private function nextNumber(string $prefix, string $model, string $column): string
    {
        return \App\Support\DocumentNumbers::next($prefix === 'MWO' ? 'maintenance_work_order' : 'breakdown');
    }
}
