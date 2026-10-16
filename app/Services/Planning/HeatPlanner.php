<?php

namespace App\Services\Planning;

use App\Models\CustomerProductRequirement;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\PatternRevisionCavity;
use App\Models\PlannedHeat;
use App\Models\ProductRevision;
use App\Models\ProductSpecification;
use App\Models\ProductionOrder;
use App\Services\FurnaceEligibility;
use App\Services\PatternCavityMap;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Turns open production orders into planned melting heats.
 * Orders are grouped by iron grade, grades are sequenced to limit changeover,
 * and each heat is placed on an eligible furnace with free capacity.
 */
class HeatPlanner
{
    private const SHIFT_HOURS_PER_DAY = 16.0;
    private const HANDLING_HOURS = 0.5;

    public function __construct(private FurnaceEligibility $furnaces, private PatternCavityMap $cavities)
    {
    }

    /** Poured metal needed per finished product (kg), and where the figure came from. */
    public function metalPerProduct(ProductRevision $rev): ?array
    {
        $links = PatternRevisionCavity::with('patternRevision')
            ->whereHas('productRevision', fn ($q) => $q->where('product_id', $rev->product_id))
            ->whereHas('patternRevision', fn ($q) => $q->where('status', 'APPROVED'))->get();

        foreach ($links->groupBy('pattern_revision_id') as $group) {
            $pr = $group->first()->patternRevision;
            $perMould = $this->cavities->expectedProductsPerMould($pr, $rev->product_id);
            if ($perMould <= 0) {
                continue;
            }
            $poured = $group->sum(fn ($c) => (float) ($c->poured_weight_kg ?? 0));
            if ($poured > 0 && $group->every(fn ($c) => $c->poured_weight_kg !== null)) {
                return ['kg' => round($poured / $perMould, 3), 'source' => 'cavity poured weight'];
            }
            $cast = $group->sum(fn ($c) => (float) ($c->casting_weight_kg ?? 0));
            if ($cast > 0 && $group->every(fn ($c) => $c->casting_weight_kg !== null)) {
                return ['kg' => round($cast / $perMould / (float) config('foundry.planner.default_yield'), 3), 'source' => 'cavity casting weight / default yield'];
            }
        }
        if ($rev->net_weight_kg !== null && (float) $rev->net_weight_kg > 0) {
            return ['kg' => round((float) $rev->net_weight_kg / (float) config('foundry.planner.default_yield'), 3), 'source' => 'product net weight / default yield'];
        }

        return null;
    }

    public function gradeFor(ProductRevision $rev, ?string $customerId = null): ?IronGrade
    {
        $spec = ProductSpecification::where('product_revision_id', $rev->id)->whereNotNull('material_grade')
            ->whereNull('superseded_at')->orderByDesc('created_at')->value('material_grade');
        if ($g = IronGrade::resolve($spec)) {
            return $g['grade'];
        }
        $cust = CustomerProductRequirement::where('product_revision_id', $rev->id)->whereNotNull('customer_grade')
            ->whereNull('superseded_at')->orderByDesc('created_at')->value('customer_grade');

        return IronGrade::resolve($cust)['grade'] ?? null;
    }

    /**
     * Rebuild the plan (planned, unreleased heats only) from the given date.
     * @return array{heats: int, skipped: array<int, string>}
     */
    public function rebuild(CarbonInterface $from): array
    {
        return DB::transaction(function () use ($from) {
            $this->clearOpenPlan();
            $skipped = [];
            $demand = $this->demand($skipped);

            // Sequence groups to limit changeover: family order, then grade code.
            $seq = array_flip(config('foundry.planner.family_sequence'));
            uasort($demand, fn ($a, $b) => [$seq[$a['grade']->family] ?? 99, $a['grade']->code] <=> [$seq[$b['grade']->family] ?? 99, $b['grade']->code]);

            $slots = [];          // furnace id => ['day' => int, 'used_h' => float, 'last_family' => ?, 'last_grade' => ?]
            $count = 0;
            foreach ($demand as $d) {
                $furnaces = $this->furnaces->eligible($d['grade'])->sortByDesc(fn ($f) => (float) $f->rated_capacity_kg)->values();
                if ($furnaces->isEmpty()) {
                    $skipped[] = "No active furnace can melt {$d['grade']->code}; {$d['kg']} kg not planned.";

                    continue;
                }
                $remaining = $d['orders'];
                usort($remaining, fn ($a, $b) => ($a['required'] ?? '9999') <=> ($b['required'] ?? '9999'));
                $queue = array_map(fn ($o) => $o + ['left' => $o['kg']], $remaining);

                while ($queue) {
                    $furnace = $this->pickFurnace($furnaces, $slots);
                    $cap = (float) $furnace->rated_capacity_kg * (float) config('foundry.planner.furnace_fill_factor');
                    if ($cap <= 0) {
                        $skipped[] = "{$furnace->code} has no rated capacity; set it on the furnace.";
                        break;
                    }
                    $fill = 0.0;
                    $link = [];
                    foreach ($queue as $i => &$o) {
                        if ($fill >= $cap - 0.001) {
                            break;
                        }
                        $take = min($o['left'], $cap - $fill);
                        $fill += $take;
                        $o['left'] -= $take;
                        $link[] = [$o['id'], $take, $o['required']];
                    }
                    unset($o);
                    $queue = array_values(array_filter($queue, fn ($o) => $o['left'] > 0.001));

                    [$day, $changeover] = $this->place($furnace, $d['grade'], $fill, $slots, $from);
                    $date = $from->copy()->addDays($day);
                    $earliest = collect($link)->pluck(2)->filter()->min();
                    $late = $earliest && $date->diffInDays(\Carbon\Carbon::parse($earliest), false) < (int) config('foundry.planner.late_buffer_days');

                    $heat = PlannedHeat::create([
                        'plan_number' => $this->nextPlanNumber($date),
                        'iron_grade_id' => $d['grade']->id,
                        'furnace_id' => $furnace->id,
                        'planned_date' => $date,
                        'sequence' => $slots[$furnace->id]['seq'],
                        'planned_metal_kg' => round($fill, 3),
                        'changeover_required' => $changeover,
                        'late_risk' => $late,
                        'flags' => trim(($changeover ? 'Furnace changeover before this heat. ' : '').($late ? 'Planned too close to the required date.' : '')) ?: null,
                    ]);
                    foreach ($link as [$poId, $kg]) {
                        $heat->orders()->create(['production_order_id' => $poId, 'metal_kg' => round($kg, 3)]);
                    }
                    $count++;
                }
            }

            return ['heats' => $count, 'skipped' => $skipped];
        });
    }

    /** Release a planned heat as a real melting heat. */
    public function release(PlannedHeat $planned, ?string $operator = null): Heat
    {
        return DB::transaction(function () use ($planned, $operator) {
            $planned = PlannedHeat::lockForUpdate()->findOrFail($planned->id);
            if ($planned->status !== 'PLANNED') {
                throw new InvalidArgumentException('Only a planned heat can be released.');
            }
            if (! $planned->furnace_id) {
                throw new InvalidArgumentException('Choose a furnace before releasing the heat.');
            }
            $furnace = Furnace::findOrFail($planned->furnace_id);
            $grade = IronGrade::findOrFail($planned->iron_grade_id);
            if (! $this->furnaces->canMelt($furnace, $grade)) {
                throw new InvalidArgumentException($this->furnaces->reason($furnace, $grade));
            }
            $date = $planned->planned_date->format('Ymd');
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['heat-number-'.$date]);
            $last = (int) Heat::where('heat_number', 'like', "H-{$date}-%")
                ->selectRaw("COALESCE(MAX(CAST(SPLIT_PART(heat_number, '-', 3) AS INTEGER)), 0) AS m")->value('m');

            $heat = Heat::create([
                'heat_number' => "H-{$date}-".str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT),
                'furnace_id' => $furnace->id,
                'process_type' => $furnace->furnace_type,
                'status' => 'PLANNED',
                'iron_grade_id' => $grade->id,
                'planned_start_at' => $planned->planned_date->copy()->setTime(6, 0),
                'target_metal_weight_kg' => $planned->planned_metal_kg,
                'operator_name' => $operator ?: 'Unassigned',
                'notes' => "Released from plan {$planned->plan_number}.",
            ]);
            $planned->update(['status' => 'RELEASED', 'heat_id' => $heat->id]);

            return $heat;
        });
    }

    private function clearOpenPlan(): void
    {
        $ids = PlannedHeat::where('status', 'PLANNED')->pluck('id');
        DB::table('planned_heat_orders')->whereIn('planned_heat_id', $ids)->delete();
        PlannedHeat::whereIn('id', $ids)->delete();
    }

    /** Remaining metal demand by grade from released production orders. */
    private function demand(array &$skipped): array
    {
        $planned = DB::table('planned_heat_orders')
            ->join('planned_heats', 'planned_heats.id', '=', 'planned_heat_orders.planned_heat_id')
            ->where('planned_heats.status', 'RELEASED')
            ->groupBy('production_order_id')->selectRaw('production_order_id, SUM(metal_kg) as kg')->pluck('kg', 'production_order_id');

        $out = [];
        $orders = ProductionOrder::with(['product', 'productRevision'])->where('status', 'RELEASED')->orderBy('required_date')->get();
        foreach ($orders as $o) {
            $rev = $o->productRevision;
            if (! $rev) {
                continue;
            }
            $grade = $this->gradeFor($rev);
            if (! $grade) {
                $skipped[] = "{$o->production_order_number}: no iron grade is known for {$o->product?->code}.";

                continue;
            }
            $per = $this->metalPerProduct($rev);
            if (! $per) {
                $skipped[] = "{$o->production_order_number}: no weight basis for {$o->product?->code}. Add cavity weights or a net weight.";

                continue;
            }
            $need = max(0.0, (float) $o->planned_qty * $per['kg'] - (float) ($planned[$o->id] ?? 0));
            if ($need < 0.001) {
                continue;
            }
            $out[$grade->id]['grade'] = $grade;
            $out[$grade->id]['kg'] = ($out[$grade->id]['kg'] ?? 0) + $need;
            $out[$grade->id]['orders'][] = ['id' => $o->id, 'kg' => $need, 'required' => $o->required_date?->toDateString()];
        }

        return $out;
    }

    private function pickFurnace($furnaces, array $slots): Furnace
    {
        // The eligible furnace that becomes free earliest; ties go to the larger furnace.
        return $furnaces->sortBy(fn ($f) => [($slots[$f->id]['day'] ?? 0) + (($slots[$f->id]['used_h'] ?? 0) / self::SHIFT_HOURS_PER_DAY)])->first();
    }

    /** @return array{0: int, 1: bool} day offset and whether a changeover is needed */
    private function place(Furnace $f, IronGrade $g, float $kg, array &$slots, CarbonInterface $from): array
    {
        $s = $slots[$f->id] ?? ['day' => 0, 'used_h' => 0.0, 'last_family' => null, 'last_grade' => null, 'seq' => 0];
        $rate = max(1.0, (float) ($f->nominal_melting_rate_kg_per_hour ?: $kg));
        $hours = $kg / $rate + self::HANDLING_HOURS;

        $changeover = $s['last_grade'] !== null
            && ($s['last_family'] !== $g->family || ($g->family === 'DUCTILE' && $s['last_grade'] !== $g->id));
        if ($changeover) {
            $hours += 1.0;      // flush / lining and chemistry reset
        }
        if ($s['used_h'] + $hours > self::SHIFT_HOURS_PER_DAY && $s['used_h'] > 0) {
            $s['day']++;
            $s['used_h'] = 0.0;
        }
        $s['used_h'] += $hours;
        $s['last_family'] = $g->family;
        $s['last_grade'] = $g->id;
        $s['seq']++;
        $slots[$f->id] = $s;

        return [$s['day'], $changeover];
    }

    private function nextPlanNumber(CarbonInterface $date): string
    {
        $prefix = 'PH-'.$date->format('Ymd').'-';
        $last = PlannedHeat::where('plan_number', 'like', $prefix.'%')->pluck('plan_number')
            ->map(fn ($n) => (int) substr($n, strlen($prefix)))->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 3, '0', STR_PAD_LEFT);
    }
}
