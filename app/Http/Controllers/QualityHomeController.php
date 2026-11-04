<?php

namespace App\Http\Controllers;

use App\Domains\Maintenance\Models\Gauge;
use App\Models\CastingLot;
use App\Models\NonconformanceReport;
use App\Models\QualityInspection;
use App\Models\ThermalAnalysisTest;
use App\Services\Quality\PendingInspections;
use Illuminate\View\View;

/** The home of the quality inspector and manager: what waits on them, what failed lately and what is open. */
class QualityHomeController extends Controller
{
    public function __invoke(PendingInspections $pending): View
    {
        $since = now()->subDay();
        $awaiting = $pending->awaitingLots()->with('product')->oldest()->limit(8)->get();

        return view('quality.home', [
            'awaiting' => $awaiting,
            'awaitingCount' => $pending->awaitingLots()->count(),
            'inProgress' => QualityInspection::where('status', 'IN_PROGRESS')->count(),
            'failedThermal' => ThermalAnalysisTest::with('heat')->where('result', 'FAIL')->where('taken_at', '>=', $since)->latest('taken_at')->limit(6)->get(),
            'failedInspections' => QualityInspection::with('lot')->where('status', 'COMPLETED')->where('inspected_at', '>=', $since)->where(fn ($q) => $q->where('quantity_rejected', '>', 0)->orWhere('quantity_rework', '>', 0))->latest('inspected_at')->limit(6)->get(),
            'ncrs' => NonconformanceReport::whereNotIn('status', ['CLOSED', 'CANCELLED'])->orderByDesc('reported_at')->limit(6)->get(),
            'ncrCount' => NonconformanceReport::whereNotIn('status', ['CLOSED', 'CANCELLED'])->count(),
            'onHold' => CastingLot::where('status', 'ON_HOLD')->count(),
            'gaugesDue' => class_exists(Gauge::class) ? Gauge::calibrationDue()->count() : 0,
            'testsToday' => ThermalAnalysisTest::whereDate('created_at', today())->count() + QualityInspection::whereDate('created_at', today())->count(),
        ]);
    }
}
