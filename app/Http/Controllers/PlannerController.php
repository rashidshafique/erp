<?php

namespace App\Http\Controllers;

use App\Services\Planning\HeatPlanner;
use App\Services\Planning\OrderPlanner;
use App\Support\PlannerRules;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/** One screen for completing production orders: what to mould next, when each order will finish, and what is waiting downstream. */
class PlannerController extends Controller
{
    public function index(Request $request, OrderPlanner $planner): View
    {
        $day = Carbon::parse($request->validate(['day' => ['nullable', 'date']])['day'] ?? today());

        return view('planner.index', $planner->plan($day) + [
            'day' => $day, 'shifts' => WorkListController::SHIFTS, 'flaskLoad' => app(\App\Services\Production\FlaskLoad::class)->bySize($day),
            'rules' => collect(PlannerRules::RULES)->map(fn ($r, $key) => ['label' => $r[1], 'min' => $r[2], 'max' => $r[3], 'value' => PlannerRules::get($key)]),
        ]);
    }

    public function fill(Request $request, OrderPlanner $planner, HeatPlanner $heats): RedirectResponse
    {
        $d = $request->validate(['day' => ['required', 'date', 'after_or_equal:today'], 'shift' => ['required', Rule::in(WorkListController::SHIFTS)], 'rebuild_melting' => ['nullable', 'boolean']]);
        $day = Carbon::parse($d['day']);
        try {
            $res = $planner->fill($day, $d['shift']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['plan' => $e->getMessage()]);
        }
        $msg = "{$res['added']} row(s) put on {$res['list']->list_number}.";
        if (! empty($d['rebuild_melting'])) {
            $msg .= ' Melting plan rebuilt: '.$heats->rebuild($day)['heats'].' heat(s).';
        }

        $to = redirect()->route('work-lists.show', $res['list'])->with('success', $msg);

        return $res['skipped'] ? $to->withErrors(['item' => 'Left out: '.implode(' ', $res['skipped'])]) : $to;
    }

    public function saveRules(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (PlannerRules::RULES as $key => [, , $min, $max]) {
            $rules[$key] = ['required', 'integer', "min:{$min}", "max:{$max}"];
        }
        foreach ($request->validate($rules) as $key => $value) {
            PlannerRules::set($key, (int) $value);
        }

        return back()->with('success', 'Planner assumptions saved.');
    }
}
