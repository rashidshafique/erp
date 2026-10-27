<?php

namespace App\Domains\Finance\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * An invoice or credit note was issued, a supplier bill or credit note posted, a receipt, supplier payment or withholding certificate recorded. Fired inside
 * the transaction that made it count, so a listener that refuses rolls the document back.
 */
class DocumentPosted
{
    public function __construct(public readonly Model $document) {}
}
