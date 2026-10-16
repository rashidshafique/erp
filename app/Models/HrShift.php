<?php

namespace App\Models;

use App\Models\Concerns\GeneratesCodes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class HrShift extends Model
{
    use GeneratesCodes, HasUuids;

    protected $guarded = [];

    public string $codeKind = 'hr_shift';

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function crossesMidnight(): bool
    {
        return substr($this->ends_at, 0, 5) <= substr($this->starts_at, 0, 5);
    }

    public function startOn($date): Carbon
    {
        return Carbon::parse(Carbon::parse($date)->toDateString().' '.$this->starts_at);
    }

    public function endOn($date): Carbon
    {
        $e = Carbon::parse(Carbon::parse($date)->toDateString().' '.$this->ends_at);

        return $this->crossesMidnight() ? $e->addDay() : $e;
    }

    public function paidMinutes(): int
    {
        return max(0, (int) $this->startOn('2000-01-01')->diffInMinutes($this->endOn('2000-01-01')) - (int) $this->break_minutes);
    }

    public function label(): string
    {
        return $this->name.' ('.substr($this->starts_at, 0, 5).'–'.substr($this->ends_at, 0, 5).')';
    }
}
