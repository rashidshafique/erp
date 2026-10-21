<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::query()->withCount(['salesOrders', 'ownedPatterns'])
            ->when($request->filled('search'), fn ($q) => $q->matching($request->search))
            ->orderBy('code')->paginate(20)->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', 'unique:customers,code'],
            'name' => ['required', 'string', 'max:200'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'billing_address' => ['nullable', 'string', 'max:1000'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
        ]);
        $data['code'] = filled($data['code'] ?? null) ? strtoupper(trim($data['code'])) : null;
        $data['active'] = true;
        $customer = Customer::create($data);

        if ($request->wantsJson()) {
            return response()->json(['id' => $customer->id, 'label' => $customer->code.' — '.$customer->name], 201);
        }

        return redirect()->route('customers.index')->with('success', "Customer {$customer->code} created.");
    }
}
