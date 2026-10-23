<?php

namespace App\Http\Controllers;

use App\Services\Planning\CalendarEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** The plan and the record on one calendar: week, month or year around a chosen day. */
class CalendarController extends Controller
{
    private const VIEWS = ['week', 'month', 'year'];

    public function index(Request $request, CalendarEvents $calendar): View
    {
        $data = $request->validate(['view' => ['nullable', 'in:'.implode(',', self::VIEWS)], 'date' => ['nullable', 'date']]);
        $view = $data['view'] ?? 'month';
        $anchor = Carbon::parse($data['date'] ?? today())->startOfDay();

        [$start, $end, $previous, $next, $title] = match ($view) {
            'week' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek(), $anchor->copy()->subWeek(), $anchor->copy()->addWeek(),
                $anchor->copy()->startOfWeek()->format('j M').' – '.$anchor->copy()->endOfWeek()->format('j M Y')],
            'year' => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear(), $anchor->copy()->subYear(), $anchor->copy()->addYear(), $anchor->format('Y')],
            default => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth(), $anchor->copy()->subMonthNoOverflow(), $anchor->copy()->addMonthNoOverflow(), $anchor->format('F Y')],
        };

        return view('calendar.index', [
            'view' => $view, 'anchor' => $anchor, 'start' => $start, 'end' => $end, 'previous' => $previous, 'next' => $next, 'title' => $title,
            'events' => $calendar->between($start, $end), 'calendar' => $calendar, 'kinds' => CalendarEvents::KINDS,
        ]);
    }
}
