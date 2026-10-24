<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Models\SupplierPayment;
use App\Domains\Finance\Services\Payables;
use App\Domains\Finance\Services\SupplierPaymentService;
use App\Domains\Purchasing\Models\Supplier;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class SupplierPaymentController extends Controller
{
    public function index(): View
    {
        return view('finance::supplier-payments.index', ['payments' => SupplierPayment::with(['supplier', 'allocations'])->orderByDesc('paid_on')->orderByDesc('created_at')->paginate(25)]);
    }

    /** The payment form; picking a supplier (?supplier=) lists the bills it can settle, oldest due first. */
    public function create(Request $request, Payables $payables): View
    {
        $supplierId = $request->filled('supplier') && Str::isUuid($request->query('supplier')) ? $request->query('supplier') : null;

        return view('finance::supplier-payments.create', [
            'suppliers' => Supplier::orderedByName()->get(), 'supplierId' => $supplierId, 'open' => $supplierId ? $payables->open($supplierId) : collect(), 'billId' => $request->query('bill'),
        ]);
    }

    public function store(Request $request, SupplierPaymentService $service): RedirectResponse
    {
        $d = $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:suppliers,id'], 'paid_on' => ['required', 'date'], 'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['nullable', 'string', 'max:30'], 'reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000'],
            'alloc' => ['nullable', 'array'], 'alloc.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        try {
            $payment = $service->record($d, $d['alloc'] ?? []);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()])->withInput();
        }

        return redirect()->route('supplier-payments.show', $payment)->with('success', "Payment {$payment->payment_number} recorded.");
    }

    public function show(SupplierPayment $payment, Payables $payables): View
    {
        $payment->load(['supplier', 'allocations.bill']);

        return view('finance::supplier-payments.show', ['payment' => $payment, 'open' => $payment->unallocated() > 0 ? $payables->open($payment->supplier_id) : collect()]);
    }

    public function allocate(Request $request, SupplierPayment $payment, SupplierPaymentService $service): RedirectResponse
    {
        $d = $request->validate(['alloc' => ['required', 'array'], 'alloc.*' => ['nullable', 'numeric', 'min:0']]);
        try {
            $service->allocateRest($payment, $d['alloc']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('success', 'Payment put against the bills.');
    }
}
