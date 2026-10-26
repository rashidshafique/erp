<?php

namespace App\Domains\Finance\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * An invoice or credit note was issued, a supplier bill or credit note posted, or a receipt or supplier payment recorded. Fired inside
 * the transaction that made it count, so a listener that refuses rolls the document back.
 */
class DocumentPosted
{
    public function __construct(public readonly Model $document) {}
}
