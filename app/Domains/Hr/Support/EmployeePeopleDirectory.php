<?php

namespace App\Domains\Hr\Support;

use App\Domains\Core\Contracts\PeopleDirectory;
use App\Domains\Hr\Models\Employee;
use Illuminate\Support\Collection;

/** The staff list moulding teams are built from, taken from the employee register. */
class EmployeePeopleDirectory implements PeopleDirectory
{
    public function available(): bool
    {
        return true;
    }

    public function current(): Collection
    {
        return Employee::current()->with(['department', 'designation'])->orderBy('first_name')->orderBy('last_name')->get();
    }

    public function countCurrent(array $ids): int
    {
        return Employee::query()->whereIn('id', $ids)->where('status', '!=', 'EXITED')->count();
    }

    public function findCurrent(string $id): ?object
    {
        return Employee::current()->whereKey($id)->first();
    }

    public function idByName(array $ids, string $name): ?string
    {
        return Employee::query()->whereIn('id', $ids)->get()
            ->first(fn (Employee $employee) => mb_strtolower($employee->full_name) === mb_strtolower($name))?->id;
    }
}
