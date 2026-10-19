<?php

namespace App\Domains\Compliance\Http\Controllers;

use App\Domains\Compliance\Models\RequirementProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RequirementProfileController extends Controller
{
    public function index(): View
    {
        return view('compliance::profiles.index', ['profiles' => RequirementProfile::withCount('customers')->orderBy('region')->orderBy('name')->get(), 'regions' => RequirementProfile::REGIONS]);
    }

    public function create(): View
    {
        return view('compliance::profiles.form', ['profile' => new RequirementProfile(['region' => 'ASIA', 'active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = RequirementProfile::create($this->validated($request) + ['is_system' => false]);

        return redirect()->route('requirements.index')->with('success', "{$profile->name} added.");
    }

    public function edit(RequirementProfile $profile): View
    {
        return view('compliance::profiles.form', ['profile' => $profile]);
    }

    public function update(Request $request, RequirementProfile $profile): RedirectResponse
    {
        $profile->update($this->validated($request, $profile));

        return redirect()->route('requirements.index')->with('success', "{$profile->name} saved.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?RequirementProfile $profile = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'region' => ['required', Rule::in(array_keys(RequirementProfile::REGIONS))],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
        if (! $profile?->is_system) {
            $rules['code'] = ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('requirement_profiles', 'code')->ignore($profile?->id)];
        }
        foreach (RequirementProfile::SETTINGS as $key => [, $type, $options]) {
            $rules["settings.$key"] = match ($type) {
                'rank' => ['required', Rule::in(array_keys($options))],
                'bool' => ['nullable', 'boolean'],
                default => ['required', 'numeric', 'min:0', 'max:'.($key === 'ppap_level' ? 5 : 10)],
            };
        }
        $data = $request->validate($rules);
        foreach (RequirementProfile::SETTINGS as $key => [, $type]) {
            $data['settings'][$key] = match ($type) {
                'bool' => (bool) ($data['settings'][$key] ?? false),
                'number' => $data['settings'][$key] + 0,
                default => $data['settings'][$key],
            };
        }
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        return $data + ['active' => $request->boolean('active')];
    }
}
