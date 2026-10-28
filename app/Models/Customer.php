<?php

namespace App\Models;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Parties\Concerns\HasParty;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;

class Customer extends Model
{
    use HasParty, HasUuids, SoftDeletes, \App\Models\Concerns\GeneratesCodes;

    public string $codeKind = 'customer';

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'contact_name',
        'phone',
        'email',
        'billing_address',
        'shipping_address',
        'tax_number',
        'payment_terms',
        'active',
        'is_prospect',
    ];

    /** Credit fields are set by the Finance module, price list by Sales; neither is mass-assigned from the customer forms. */
    protected function casts(): array
    {
        return ['credit_limit' => 'float', 'credit_hold' => 'boolean', 'is_prospect' => 'boolean', 'credit_release_until' => 'date'];
    }

    /**
     * The rules a customer is saved under, from the order form's quick add or the customer list's dialog. Codes are kept in
     * capitals, so the caller upper-cases one before checking it is unused.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(?self $customer = null): array
    {
        return [
            'code' => ['nullable', 'string', 'max:40', Rule::unique('customers', 'code')->ignore($customer?->id)],
            'name' => ['required', 'string', 'max:200'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'billing_address' => ['nullable', 'string', 'max:1000'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** Patterns the customer owns or is paying off, held by the foundry. */
    public function ownedPatterns(): HasMany
    {
        return $this->hasMany(Pattern::class, 'owner_customer_id');
    }

    public function requirementProfiles(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domains\Compliance\Models\RequirementProfile::class, 'customer_requirement_profiles', 'customer_id', 'requirement_profile_id')->withTimestamps();
    }

    /** Where the account stands: on hold, a prospect (flagged and no order yet), inactive, or active. */
    public function stage(): string
    {
        return match (true) {
            (bool) $this->credit_hold => 'hold',
            $this->is_prospect && ! $this->salesOrders()->exists() => 'prospect',
            ! $this->active => 'inactive',
            default => 'active',
        };
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }
}
