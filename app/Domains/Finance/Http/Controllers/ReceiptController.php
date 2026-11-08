<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Services\ReceiptService;
use App\Domains\Finance\Services\Receivables;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Finance\Services\ChequeService;
use App\Domains\Finance\Services\PaymentAccounts;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class ReceiptController extends Controller
{
    public function index(Receivables $receivables): View
    {
        return view('finance::receipts.index', ['summary' => $receivables->summary(), 'receipts' => Receipt::with(['customer', 'allocations'])->orderByDesc('received_on')->orderByDesc('created_at')->paginate(25)]);
    }

    /** The receipt form; picking a customer (?customer=) lists the invoices it can pay, oldest due first. */
    public function create(Request $request, Receivables $receivables): View
    {
        $customerId = $request->filled('customer') && Str::isUuid($request->query('customer')) ? $request->query('customer') : null;

        return view('finance::receipts.create', [
            'customers' => Customer::orderedByName()->get(), 'customerId' => $customerId, 'open' => $customerId ? $receivables->open($customerId) : collect(),
            'invoiceId' => $request->query('invoice'),
            'accounts' => PaymentAccounts::options(), 'account' => PaymentAccounts::preferred('receipts'), 'asCheque' => $request->boolean('cheque'), 'methods' => PaymentAccounts::methods('receipts'),
            'orders' => $customerId ? SalesOrder::where('customer_id', $customerId)->whereIn('status', SalesOrder::OPEN)->orderByDesc('order_date')->get(['id', 'order_number', 'order_date']) : collect(),
        ]);
    }

    public function store(Request $request, ReceiptService $service, ChequeService $cheques): RedirectResponse
    {
        $d = $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:customers,id'], 'received_on' => ['required', 'date'], 'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['nullable', 'string', 'max:30'], 'reference' => ['nullable', 'string', 'max:100'], 'account_id' => PaymentAccounts::rule(), 'as_cheque' => ['nullable', 'boolean'], 'cheque_date' => ['nullable', 'required_if_accepted:as_cheque', 'date'], 'drawn_on' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000'],
            'alloc' => ['nullable', 'array'], 'alloc.*' => ['nullable', 'numeric', 'min:0'],
            'sales_order_id' => ['nullable', 'uuid', Rule::exists('sales_orders', 'id')->where('customer_id', $request->input('customer_id'))],
        ]);
        try {
            if ($request->boolean('as_cheque') || ChequeService::isPostDated($d)) {
                $cheque = $cheques->receive($d, $d['alloc'] ?? []);

                return redirect()->route('cheques.show', $cheque)->with('success', 'Post-dated cheque '.($cheque->cheque_number ?? '').' recorded.');
            }
            $receipt = $service->record($d, $d['alloc'] ?? []);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()])->withInput();
        }

        return redirect()->route('receipts.show', $receipt)->with('success', "Receipt {$receipt->receipt_number} recorded.");
    }

    public function show(Receipt $receipt, Receivables $receivables): View
    {
        $receipt->load(['customer', 'allocations.invoice']);

        return view('finance::receipts.show', ['receipt' => $receipt, 'open' => $receipt->unallocated() > 0 ? $receivables->open($receipt->customer_id) : collect()]);
    }

    public function allocate(Request $request, Receipt $receipt, ReceiptService $service): RedirectResponse
    {
        $d = $request->validate(['alloc' => ['required', 'array'], 'alloc.*' => ['nullable', 'numeric', 'min:0']]);
        try {
            $service->allocateRest($receipt, $d['alloc']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['receipt' => $e->getMessage()]);
        }

        return back()->with('success', 'Payment put against the invoices.');
    }
}
