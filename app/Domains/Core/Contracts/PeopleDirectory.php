<?php

namespace App\Domains\Core\Contracts;

use Illuminate\Support\Collection;

/**
 * The people a team or crew can be made from. The People (HR) module provides the real list;
 * without it nothing is available and screens fall back to typing a name and a head count.
 */
interface PeopleDirectory
{
    /** Whether there is a list of people to choose from at all. */
    public function available(): bool;

    /**
     * Everyone currently employed, for a picker.
     *
     * @return Collection<int, object{id: string, code: string, full_name: string, status: string, designation: ?object, department: ?object}>
     */
    public function current(): Collection;

    /** How many of these ids are current staff. @param list<string> $ids */
    public function countCurrent(array $ids): int;

    /** A current person by id, or null. */
    public function findCurrent(string $id): ?object;

    /** The id of the person among these ids whose full name matches, or null. @param list<string> $ids */
    public function idByName(array $ids, string $name): ?string;
}
