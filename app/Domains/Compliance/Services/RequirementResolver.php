<?php

namespace App\Domains\Compliance\Services;

use App\Domains\Compliance\Models\RequirementProfile;
use App\Models\Customer;
use Illuminate\Support\Collection;

/** Combines the profiles a customer holds into one set of requirements, taking the strictest value of each setting. */
class RequirementResolver
{
    /** @return array<string, mixed> */
    public function forCustomer(Customer $customer): array
    {
        return $this->merge(RequirementProfile::where('active', true)->whereHas('customers', fn ($q) => $q->whereKey($customer->id))->get());
    }

    /**
     * @param  Collection<int, RequirementProfile>  $profiles
     * @return array<string, mixed>
     */
    public function merge(Collection $profiles): array
    {
        $result = [];
        foreach (RequirementProfile::SETTINGS as $key => [, $type, $options]) {
            $values = $profiles->map(fn (RequirementProfile $p) => $p->value($key));
            $result[$key] = match ($type) {
                'bool' => $values->contains(true),
                'number' => $values->isEmpty() ? 0 : $values->max(),
                default => $values->isEmpty() ? RequirementProfile::neutral($key) : $values->sortBy(fn ($v) => array_search($v, array_keys($options), true))->last(),
            };
        }

        return $result;
    }
}
