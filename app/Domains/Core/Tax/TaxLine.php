<?php

namespace App\Domains\Core\Tax;

/**
 * One document line as tax sees it: its value before tax, the percent chosen on it (if any), and what it is: goods or a service,
 * its inventory class and its tariff or service code.
 */
final class TaxLine
{
    public const GOODS = 'GOODS';

    public const SERVICE = 'SERVICE';

    public function __construct(
        public readonly float $net,
        public readonly ?float $percent = null,
        public readonly string $supplyType = self::GOODS,
        public readonly ?string $inventoryClass = null,
        public readonly ?string $tariffCode = null,
    ) {}
}
