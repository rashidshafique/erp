<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Models\WithholdingCertificate;
use App\Domains\Finance\Services\WithholdingService;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class WithholdingCertificateController extends Controller
{
    public function index(): View
    {
        return view('finance::withholding.index', ['certificates' => WithholdingCertificate::with('customer')->orderByDesc('certificate_date')->orderByDesc('created_at')->paginate(25)]);
    }

    /** The certificate form: pick the customer (?customer=), then the tax (?tax=), to list the invoices it can settle. */
    public function create(Request $request, WithholdingService $service): View
    {
        $customerId = $request->filled('customer') && Str::isUuid($request->query('customer')) ? $request->query('customer') : null;
        $taxes = $customerId ? $service->taxesFor($customerId) : [];
        $taxCode = $request->query('tax') ?: (array_key_first(array_filter($taxes, fn ($t) => $t['open'] > 0)) ?? array_key_first($taxes));

        return view('finance::withholding.create', [
            'customers' => Customer::orderedByName()->get(), 'customerId' => $customerId, 'taxes' => $taxes,
            'taxCode' => isset($taxes[$taxCode]) ? $taxCode : null,
            'invoices' => $customerId && isset($taxes[$taxCode]) ? $service->invoicesFor($customerId, $taxCode) : collect(),
        ]);
    }

    public function store(Request $request, WithholdingService $service): RedirectResponse
    {
        $d = $request->validate([
            'customer_id' => ['required', 'uuid', 'exists:customers,id'], 'tax_code' => ['required', 'string', 'max:40'],
            'reference' => ['required', 'string', 'max:100'], 'certificate_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:1000'],
            'alloc' => ['required', 'array'], 'alloc.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        try {
            $certificate = $service->record($d, $d['alloc']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['certificate' => $e->getMessage()])->withInput();
        }

        return redirect()->route('withholding-certificates.show', $certificate)->with('success', "Certificate {$certificate->certificate_number} recorded.");
    }

    public function show(WithholdingCertificate $certificate): View
    {
        return view('finance::withholding.show', ['certificate' => $certificate->load(['customer', 'allocations.invoice'])]);
    }

    public function void(Request $request, WithholdingCertificate $certificate, WithholdingService $service): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']]);
        try {
            $service->void($certificate, $d['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['certificate' => $e->getMessage()]);
        }

        return back()->with('success', "{$certificate->certificate_number} voided.");
    }
}
