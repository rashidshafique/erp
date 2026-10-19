<?php

namespace App\Domains\Compliance\Http\Controllers;

use App\Domains\Compliance\Models\RequirementProfile;
use App\Domains\Compliance\Services\RequirementResolver;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerRequirementController extends Controller
{
    public function edit(Customer $customer, RequirementResolver $resolver): View
    {
        return view('compliance::customer', [
            'customer' => $customer, 'profiles' => RequirementProfile::where('active', true)->orderBy('region')->orderBy('name')->get(),
            'held' => $customer->requirementProfiles()->pluck('requirement_profiles.id')->all(), 'merged' => $resolver->forCustomer($customer),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['profiles' => ['nullable', 'array'], 'profiles.*' => ['uuid', Rule::exists('requirement_profiles', 'id')]]);
        $customer->requirementProfiles()->sync($data['profiles'] ?? []);

        return redirect()->route('customer-requirements.edit', $customer)->with('success', "Requirements saved for {$customer->name}.");
    }
}
