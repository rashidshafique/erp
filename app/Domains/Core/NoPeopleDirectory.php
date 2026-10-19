<?php

namespace App\Domains\Core;

use App\Domains\Core\Contracts\PeopleDirectory;
use Illuminate\Support\Collection;

/** The default when no module provides a staff list: nobody to choose from. */
class NoPeopleDirectory implements PeopleDirectory
{
    public function available(): bool
    {
        return false;
    }

    public function current(): Collection
    {
        return collect();
    }

    public function countCurrent(array $ids): int
    {
        return 0;
    }

    public function findCurrent(string $id): ?object
    {
        return null;
    }

    public function idByName(array $ids, string $name): ?string
    {
        return null;
    }
}
