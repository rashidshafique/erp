<?php

namespace App\Services\Approvals;

use App\Models\ApprovalRequest;

/** An approval request was decided on, or cancelled. Fired inside the transaction, so a listener that refuses undoes the decision. */
class ApprovalDecided
{
    public function __construct(public readonly ApprovalRequest $request) {}
}
