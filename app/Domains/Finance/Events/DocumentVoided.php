<?php

namespace App\Domains\Finance\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * An issued invoice or credit note, a posted supplier bill or credit note, a withholding certificate, or what a bounced cheque had
 * made, was voided. Fired inside the voiding transaction; the ledger reverses on $date, today when not given.
 */
class DocumentVoided
{
    public function __construct(public readonly Model $document, public readonly ?string $date = null) {}
}
