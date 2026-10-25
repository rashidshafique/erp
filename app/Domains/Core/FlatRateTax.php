<?php

namespace App\Domains\Core;

use App\Domains\Core\Contracts\TaxCalculator;
use App\Domains\Core\Tax\TaxAmount;
use App\Domains\Core\Tax\TaxContext;
use App\Domains\Core\Tax\TaxResult;
use Illuminate\Support\Facades\DB;

/** The default when the Taxation module is off: each line is taxed once, at the percent chosen on it, rounded per line. */
class FlatRateTax implements TaxCalculator
{
    public function calculate(TaxContext $context, array $lines): TaxResult
    {
        return new TaxResult(array_map(fn ($line) => $this->flat($line->net, $line->percent), array_values($lines)));
    }

    /** One tax at a percent on a value. @return list<TaxAmount> */
    public function flat(float $net, ?float $percent): array
    {
        $rate = (float) ($percent ?? 0);

        return [new TaxAmount('TAX', 'Tax', $net, $rate, round($net * $rate / 100, 2))];
    }

    public function issueChecks(TaxContext $context, array $lines): array
    {
        return ['blocks' => [], 'warnings' => []];
    }

    public function registrations(?string $partyId): array
    {
        $number = $partyId ? DB::table('parties')->where('id', $partyId)->value('tax_number') : null;

        return ['seller' => [], 'party' => $number ? ['Tax no.' => $number] : []];
    }
}
