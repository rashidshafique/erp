<?php

namespace App\Domains\Core\Tax;

use InvalidArgumentException;

/** The country tax packs installed, keyed by country code. The Taxation module registers them; without it the list is empty. */
class TaxJurisdictions
{
    /** @var array<string, class-string<Jurisdiction>> */
    private array $packs = [];

    /** @param class-string<Jurisdiction> $class */
    public function register(string $code, string $class): void
    {
        if (! is_subclass_of($class, Jurisdiction::class)) {
            throw new InvalidArgumentException("{$class} is not a tax jurisdiction.");
        }
        $this->packs[strtoupper($code)] = $class;
    }

    public function find(string $code): ?Jurisdiction
    {
        $class = $this->packs[strtoupper($code)] ?? null;

        return $class ? app($class) : null;
    }

    /** @return array<string, string> code => country name */
    public function options(): array
    {
        return collect($this->packs)->map(fn (string $class) => app($class)->name())->all();
    }
}
