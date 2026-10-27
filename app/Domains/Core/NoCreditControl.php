<?php

namespace App\Domains\Core;

use App\Domains\Core\Contracts\CreditControl;

/** The default when no module tracks what customers owe: nobody is held back. */
class NoCreditControl implements CreditControl
{
    public function check(string $customerId, float $adding = 0, ?string $excludingOrderId = null): array
    {
        return ['severity' => 'PASS', 'messages' => []];
    }
}
