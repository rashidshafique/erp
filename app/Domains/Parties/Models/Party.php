<?php

namespace App\Domains\Parties\Models;

use App\Domains\Purchasing\Models\Supplier;
use App\Models\Concerns\GeneratesCodes;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A business partner: one company or person we deal with. Who they are lives here once; what we do with them
 * (sell to them, buy from them) is a role record that points back at the party.
 */
class Party extends Model
{
    use GeneratesCodes, HasUuids, SoftDeletes;

    public string $codeKind = 'party';

    /** The general data every role reads through to. */
    public const FIELDS = ['name', 'short_name', 'contact_name', 'phone', 'email', 'billing_address', 'shipping_address', 'tax_number', 'active'];

    public const ROLES = ['customer' => 'Customer', 'supplier' => 'Supplier'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class);
    }

    /** @return array<string, string> Role key => label for the roles this party holds. */
    public function roles(): array
    {
        return collect(self::ROLES)->filter(fn ($label, $key) => $this->{$key} !== null)->all();
    }
}
