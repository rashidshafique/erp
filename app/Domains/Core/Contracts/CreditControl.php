<?php

namespace App\Domains\Core\Contracts;

/**
 * Whether a customer may take on more: within its credit limit, not on hold, nothing long overdue. The Finance module provides
 * the real check from receivables; without it every customer passes.
 */
interface CreditControl
{
    /**
     * @param  float  $adding  the value about to be added (an order being reviewed, a dispatch)
     * @param  string|null  $excludingOrderId  an order already counted among open orders that $adding stands for
     * @return array{severity: 'PASS'|'WARN'|'BLOCK', messages: list<string>}
     */
    public function check(string $customerId, float $adding = 0, ?string $excludingOrderId = null): array;
}
