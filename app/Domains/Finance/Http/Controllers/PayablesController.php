<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Services\Payables;
use App\Domains\Finance\Services\Receivables;
use App\Domains\Purchasing\Models\Supplier;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PayablesController extends Controller
{
    public function index(Payables $payables): View
    {
        $rows = $payables->ageing();

        return view('finance::payables.index', [
            'rows' => $rows, 'buckets' => Receivables::BUCKETS,
            'totals' => collect(array_keys(Receivables::BUCKETS))->mapWithKeys(fn ($k) => [$k => round(array_sum(array_column(array_column($rows, 'buckets'), $k)), 2)])->all(),
            'grand' => round(array_sum(array_column($rows, 'total')), 2),
        ]);
    }

    public function statement(Supplier $supplier, Payables $payables): View
    {
        return view('finance::payables.statement', ['supplier' => $supplier, 'statement' => $payables->statement($supplier), 'open' => $payables->open($supplier->id), 'advance' => $payables->advance($supplier->id)]);
    }
}
