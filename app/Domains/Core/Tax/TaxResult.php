<?php

namespace App\Domains\Core\Tax;

/** The taxes on each line of a document, in the order the lines were given. */
final class TaxResult
{
    /** @param list<list<TaxAmount>> $lines */
    public function __construct(public readonly array $lines) {}

    /** @return list<TaxAmount> */
    public function forLine(int $index): array
    {
        return $this->lines[$index] ?? [];
    }

    /** The tax added to a line. */
    public function charged(int $index): float
    {
        return round(array_sum(array_map(fn (TaxAmount $t) => $t->withheld ? 0 : $t->amount, $this->forLine($index))), 2);
    }

    /** The rate of the tax added to a line, when it carries a single one; the sum of rates otherwise. */
    public function chargedRate(int $index): float
    {
        return array_sum(array_map(fn (TaxAmount $t) => $t->withheld ? 0 : $t->rate, $this->forLine($index)));
    }

    /** The tax withheld on a line. */
    public function withheld(int $index): float
    {
        return round(array_sum(array_map(fn (TaxAmount $t) => $t->withheld ? $t->amount : 0, $this->forLine($index))), 2);
    }
}
