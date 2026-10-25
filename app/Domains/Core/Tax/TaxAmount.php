<?php

namespace App\Domains\Core\Tax;

/**
 * One tax on one line. A charged tax is added to what the buyer pays; a withheld one is kept back by the buyer and paid to the
 * authority on the seller's behalf, so it lowers what the seller is paid but not the invoice total.
 */
final class TaxAmount
{
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly float $base,
        public readonly float $rate,
        public readonly float $amount,
        public readonly bool $withheld = false,
        public readonly ?string $authority = null,
    ) {}
}
