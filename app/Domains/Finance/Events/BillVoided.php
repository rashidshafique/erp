<?php

namespace App\Domains\Finance\Events;

use App\Domains\Finance\Models\SupplierBill;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A posted supplier bill or credit note was voided. Fired after the transaction commits, so a listener that fails cannot undo the document. This is the plug-in point for mail, webhooks and the like; nothing listens yet.
 */
class BillVoided implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly SupplierBill $document) {}
}
