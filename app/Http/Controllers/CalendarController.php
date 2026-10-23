<?php

namespace App\Http\Controllers;

use App\Services\Planning\CalendarEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** The plan and the record on one calendar: week, month or year around a chosen day, or a Gantt chart of open orders. */
class CalendarController extends Controller
{
    private const VIEWS = ['week', 'month', 'year', 'gantt'];

    public function index(Request $request, CalendarEvents $calendar): View
    {
        $data = $request->validate(['view' => ['nullable', 'in:'.implode(',', self::VIEWS)], 'date' => ['nullable', 'date']]);
        $view = $data['view'] ?? 'month';
        $anchor = Carbon::parse($data['date'] ?? today())->startOfDay();

        [$start, $end, $previous, $next, $title] = match ($view) {
            'week' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek(), $anchor->copy()->subWeek(), $anchor->copy()->addWeek(),
                $anchor->copy()->startOfWeek()->format('j M').' – '.$anchor->copy()->endOfWeek()->format('j M Y')],
            'gantt' => [$anchor->copy()->startOfWeek()->subWeek(), $anchor->copy()->startOfWeek()->addWeeks(7)->endOfWeek(), $anchor->copy()->subWeeks(4), $anchor->copy()->addWeeks(4),
                $anchor->copy()->startOfWeek()->subWeek()->format('j M').' – '.$anchor->copy()->startOfWeek()->addWeeks(7)->endOfWeek()->format('j M Y')],
            'year' => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear(), $anchor->copy()->subYear(), $anchor->copy()->addYear(), $anchor->format('Y')],
            default => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth(), $anchor->copy()->subMonthNoOverflow(), $anchor->copy()->addMonthNoOverflow(), $anchor->format('F Y')],
        };

        return view('calendar.index', [
            'view' => $view, 'anchor' => $anchor, 'start' => $start, 'end' => $end, 'previous' => $previous, 'next' => $next, 'title' => $title,
            'events' => $view === 'gantt' ? collect() : $calendar->between($start, $end), 'bars' => $view === 'gantt' ? $calendar->orderBars($start, $end) : collect(), 'calendar' => $calendar, 'kinds' => CalendarEvents::KINDS,
        ]);
    }
}
