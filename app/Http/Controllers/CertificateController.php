<?php

namespace App\Http\Controllers;

use App\Models\CastingLot;
use App\Services\QualityGates;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function show(CastingLot $lot, QualityGates $gates): View
    {
        $lot->load(['product', 'productRevision', 'productionOrder.salesOrderLine.salesOrder.customer', 'inspections.results', 'ncrs']);
        $heat = $lot->sourceHeat();
        $heat?->load('ironGrade');

        return view('lots.certificate', [
            'lot' => $lot, 'heat' => $heat, 'readiness' => $gates->lotReadiness($lot),
            'chemistry' => $heat?->chemistryReadings()->get()->last(),
            'thermal' => $heat?->thermalTests()->limit(2)->get() ?? collect(),
        ]);
    }
}
