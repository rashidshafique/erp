<?php

namespace App\Models;

use App\Domains\Parties\Concerns\HasParty;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    ];

    /** Patterns the customer owns or is paying off, held by the foundry. */
    public function ownedPatterns(): HasMany
    {
        return $this->hasMany(Pattern::class, 'owner_customer_id');
    }

    public function requirementProfiles(): BelongsToMany
    {
        return $this->belongsToMany(\App\Domains\Compliance\Models\RequirementProfile::class, 'customer_requirement_profiles', 'customer_id', 'requirement_profile_id')->withTimestamps();
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }
}
