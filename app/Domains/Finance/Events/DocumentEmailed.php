<?php

namespace App\Domains\Finance\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Someone pressed Send on an invoice, credit note or customer statement. `$sent` is false while email is not set up (the message only went to the log), so a listener
 * can tell a real send from the placeholder. Nothing listens yet.
 */
class DocumentEmailed
{
    public function __construct(public readonly Model $document, public readonly string $to, public readonly bool $sent) {}
}
