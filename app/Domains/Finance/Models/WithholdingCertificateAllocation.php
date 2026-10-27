<?php

namespace App\Domains\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithholdingCertificateAllocation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    public function certificate(): BelongsTo { return $this->belongsTo(WithholdingCertificate::class, 'withholding_certificate_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
}
