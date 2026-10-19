<?php

namespace App\Domains\Hr\Models;

use App\Models\Concerns\GeneratesCodes;
use App\Models\MouldingTeam;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use GeneratesCodes, HasUuids, SoftDeletes;

    protected $guarded = [];

    public string $codeKind = 'employee';

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'joined_on' => 'date', 'probation_ends_on' => 'date', 'confirmed_on' => 'date', 'contract_ends_on' => 'date', 'exit_on' => 'date'];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mouldingTeams(): BelongsToMany
    {
        return $this->belongsToMany(MouldingTeam::class, 'moulding_team_members', 'employee_id', 'moulding_team_id')->withTimestamps();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(HrDesignation::class, 'designation_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(HrGrade::class, 'grade_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(HrLocation::class, 'location_id');
    }

    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(HrCostCentre::class, 'cost_centre_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(HrShift::class, 'shift_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id')->where('status', '!=', 'EXITED')->orderBy('first_name');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EmployeeEvent::class)->orderByDesc('effective_on')->orderByDesc('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class)->orderBy('kind');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(EmployeeTask::class)->orderBy('sort');
    }

    public function exits(): HasMany
    {
        return $this->hasMany(EmployeeExit::class)->orderByDesc('created_at');
    }

    public function openExit(): ?EmployeeExit
    {
        return $this->exits()->where('status', 'INITIATED')->first();
    }

    public function isCurrent(): bool
    {
        return $this->status !== 'EXITED';
    }

    /** Whole years and months of service, as text. */
    public function service(): string
    {
        $end = $this->exit_on ?? now();
        $m = (int) $this->joined_on->diffInMonths($end);

        return intdiv($m, 12).' y '.($m % 12).' m';
    }

    public function scopeCurrent($q)
    {
        return $q->where('status', '!=', 'EXITED');
    }

    /** Names that would sit above this person in the reporting line (stops at a loop). */
    public function chain(): array
    {
        $out = [];
        $seen = [$this->id => true];
        $m = $this->manager;
        while ($m && ! isset($seen[$m->id]) && count($out) < 12) {
            $out[] = $m;
            $seen[$m->id] = true;
            $m = $m->manager;
        }

        return $out;
    }
}
