<?php

namespace App\Domains\Core\Contracts;

use App\Domains\Core\Tax\TaxContext;
use App\Domains\Core\Tax\TaxLine;
use App\Domains\Core\Tax\TaxResult;

/**
 * Works out the tax on a document's lines. Without the Taxation module each line is taxed at the one percent chosen on it; the
 * module replaces this with rules by jurisdiction, authority, supply type and party, which can put several taxes on a line.
 */
interface TaxCalculator
{
    /** @param list<TaxLine> $lines */
    public function calculate(TaxContext $context, array $lines): TaxResult;

    /**
     * What stops a document being issued (blocks) or should be looked at first (warnings), such as a buyer with no registration number.
     *
     * @param  list<TaxLine>  $lines
     * @return array{blocks: list<string>, warnings: list<string>}
     */
    public function issueChecks(TaxContext $context, array $lines): array;

    /**
     * The registration numbers a tax document prints for the seller and for the other party, as label => number.
     *
     * @return array{seller: array<string, string>, party: array<string, string>}
     */
    public function registrations(?string $partyId): array;
}
