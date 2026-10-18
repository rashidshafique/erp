<?php

namespace App\Http\Controllers;

use App\Models\Heat;
use App\Services\Production\HeatBalance;
use App\Services\Production\HeatSamples;
use Illuminate\View\View;

class HeatSummaryController extends Controller
{
    /** One page for a heat: timeline, samples, every pour with its first-iron and containment flags, lots and metal balance. */
    public function show(Heat $heat, HeatBalance $balance, HeatSamples $samples): View
    {
        $heat->load(['furnace', 'ironGrade', 'pours' => fn ($q) => $q->orderBy('started_at'), 'pours.mouldAssemblies.castingLots.product', 'treatments']);

        return view('heats.summary', ['heat' => $heat, 'balance' => $balance->for($heat), 'samples' => $samples->timeline($heat)]);
    }
}
