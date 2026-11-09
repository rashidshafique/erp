<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Services\DocumentEmailer;
use App\Domains\Finance\Services\Receivables;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class ReceivablesController extends Controller
{
    public function index(Receivables $receivables): View
    {
        $rows = $receivables->ageing();

        return view('finance::receivables.index', [
            'summary' => $receivables->summary(), 'rows' => $rows, 'buckets' => Receivables::BUCKETS,
            'totals' => collect(array_keys(Receivables::BUCKETS))->mapWithKeys(fn ($k) => [$k => round(array_sum(array_column(array_column($rows, 'buckets'), $k)), 2)])->all(),
            'grand' => round(array_sum(array_column($rows, 'total')), 2),
        ]);
    }

    public function statement(Customer $customer, Receivables $receivables): View
    {
        return view('finance::receivables.statement', ['customer' => $customer, 'statement' => $receivables->statement($customer), 'open' => $receivables->open($customer->id), 'onAccount' => $receivables->onAccount($customer->id)]);
    }

    public function email(Customer $customer, DocumentEmailer $emailer, Receivables $receivables): RedirectResponse
    {
        try {
            $message = $emailer->statement($customer, $receivables);

            return back()->with($emailer->configured() ? 'success' : 'warning', $message);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['email' => $e->getMessage()]);
        }
    }
}
