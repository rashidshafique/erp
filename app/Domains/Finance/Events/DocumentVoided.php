<?php

namespace App\Domains\Finance\Events;

use Illuminate\Database\Eloquent\Model;

/** An issued invoice or credit note, or a posted supplier bill or credit note, was voided. Fired inside the voiding transaction. */
class DocumentVoided
{
    public function __construct(public readonly Model $document) {}
}
