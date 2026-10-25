<?php

namespace App\Domains\Core;

use App\Domains\Core\Contracts\TaxCalculator;
use App\Domains\Core\Tax\TaxAmount;
use App\Domains\Core\Tax\TaxContext;
use App\Domains\Core\Tax\TaxResult;

/** The default when the Taxation module is off: each line is taxed once, at the percent chosen on it, rounded per line. */
class FlatRateTax implements TaxCalculator
{
    public function calculate(TaxContext $context, array $lines): TaxResult
    {
        return new TaxResult(array_map(function ($line) {
            $rate = (float) ($line->percent ?? 0);

            return [new TaxAmount('TAX', 'Tax', $line->net, $rate, round($line->net * $rate / 100, 2))];
        }, array_values($lines)));
    }
}
