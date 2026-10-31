<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        return view('customers.index');
    }

    public function create(): View
    {
        return view('customers.create');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        if (filled($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);
        }
        $data = $request->validate(Customer::rules());
        if ($request->boolean('prospect')) {   // the enquiry's Add prospect dialog: stop a second record for someone already on file
            $same = Customer::withTrashed()->whereHas('party', fn ($p) => $p->whereRaw('lower(name) = ?', [mb_strtolower(trim($data['name']))]))->first();
            if ($same) {
                throw ValidationException::withMessages(['name' => "{$same->name} is already on file as {$same->code}"
                    .($same->is_prospect ? ' (a prospect)' : '').'. Choose it from the list.']);
            }
        }
        $data['code'] = filled($data['code'] ?? null) ? $data['code'] : null;
        $data['active'] = true;
        $data['is_prospect'] = true;
        $customer = Customer::create($data);

        if ($request->wantsJson()) {
            return response()->json(['id' => $customer->id, 'label' => $request->boolean('prospect') ? $customer->name.' · prospect' : $customer->code.' — '.$customer->name], 201);
        }

        return redirect()->route('customers.index')->with('success', "Customer {$customer->code} created.");
    }
}
