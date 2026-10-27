<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Services\TaxReturn;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxReturnController extends Controller
{
    /** What each authority is owed for a period, a calendar month unless dates are given. */
    public function index(Request $request, TaxReturn $return): View
    {
        $month = $request->filled('month') && preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? (string) $request->query('month') : today()->format('Y-m');
        $start = \Illuminate\Support\Carbon::parse($month.'-01');
        $from = ($request->date('from') ?? $start)->toDateString();
        $to = max($from, ($request->date('to') ?? $start->copy()->endOfMonth())->toDateString());

        return view('finance::tax-return.index', ['month' => $month, 'from' => $from, 'to' => $to, 'authorities' => $return->forPeriod($from, $to)]);
    }
}
