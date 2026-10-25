<?php

namespace App\Domains\Core\Tax;

use Carbon\CarbonInterface;

/** What a document's tax depends on beyond its lines: whether it is a sale or a purchase, its date, the other party and where it is supplied. */
final class TaxContext
{
    public const SALE = 'SALE';

    public const PURCHASE = 'PURCHASE';

    public function __construct(
        public readonly string $side,
        public readonly CarbonInterface $date,
        public readonly ?string $partyId = null,
        public readonly ?string $placeOfSupply = null,
    ) {}
}
