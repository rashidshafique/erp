<?php

namespace App\Domains\Finance\Events;

use App\Domains\Finance\Models\Receipt;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A customer receipt was recorded. Fired after the transaction commits, so a listener that fails cannot undo the document. This is the plug-in point for mail, webhooks and the like; nothing listens yet.
 */
class ReceiptRecorded implements ShouldDispatchAfterCommit
{
    public function __construct(public readonly Receipt $document) {}
}
