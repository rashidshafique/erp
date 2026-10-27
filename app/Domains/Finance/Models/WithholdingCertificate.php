<?php

namespace App\Domains\Finance\Models;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's certificate for tax it withheld from our invoices and paid to the authority. It settles the withheld part of each
 * invoice it names; a void puts that part back on the invoices.
 */
class WithholdingCertificate extends Model
{
    use HasUuids;

    /** The tax code of a certificate for tax the invoices did not work out (no tax rules, or a tax the customer added). */
    public const OTHER = 'OTHER';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['certificate_date' => 'date', 'voided_at' => 'datetime', 'amount' => 'float'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function allocations(): HasMany { return $this->hasMany(WithholdingCertificateAllocation::class); }

    public function isVoid(): bool
    {
        return $this->status === 'VOID';
    }
}
