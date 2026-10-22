<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Heat extends Model
{
    use HasUuids;

    protected $fillable = [
        'heat_number',
        'furnace_id',
        'charge_batch_id',
        'iron_grade_id',
        'process_type',
        'status',
        'planned_start_at',
        'actual_start_at',
        'melt_completed_at',
        'tapped_at',
        'closed_at',
        'target_metal_weight_kg',
        'actual_tapped_weight_kg',
        'target_temperature_c',
        'actual_tap_temperature_c',
        'operator_name',
        'notes',
        'operating_power_limit_kw',
        'blast_on_at',
        'first_tap_at',
        'dropped_at',
        'hold_reason',
        'hold_note',
        'held_at',
        'charge_rounds',
    ];

    protected function casts(): array
    {
        return [
            'planned_start_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'melt_completed_at' => 'datetime',
            'tapped_at' => 'datetime',
            'closed_at' => 'datetime',
            'blast_on_at' => 'datetime',
            'first_tap_at' => 'datetime',
            'dropped_at' => 'datetime',
            'held_at' => 'datetime',
            'target_metal_weight_kg' => 'decimal:3',
            'actual_tapped_weight_kg' => 'decimal:3',
            'target_temperature_c' => 'decimal:2',
            'actual_tap_temperature_c' => 'decimal:2',
            'operating_power_limit_kw' => 'decimal:3',
        ];
    }

    /** A cupola melts continuously and taps as it goes, so it is run on a timeline rather than as one batch. */
    /** How many times the recipe is charged: the figure set on the heat, else the target metal over the recipe's metal weight (cupolas only). */
    public function chargeRounds(): int
    {
        return max(1, (int) ($this->charge_rounds ?: $this->defaultChargeRounds()));
    }

    public function defaultChargeRounds(): int
    {
        $perRound = (float) $this->chargeBatch?->target_metal_weight_kg;
        $target = (float) $this->target_metal_weight_kg;

        return $this->isCupola() && $perRound > 0 && $target > 0 ? (int) ceil($target / $perRound) : 1;
    }

    /**
     * When the furnace started and stopped running this heat: a cupola from blast on to the drop, other furnaces from the
     * start of the melt to tapping (or closing). The end is null while the heat is still running.
     *
     * @return array{start: ?\Carbon\CarbonInterface, end: ?\Carbon\CarbonInterface, running: bool}
     */
    public function furnaceRun(): array
    {
        $cupola = $this->isCupola();
        $running = in_array($this->status, self::RUNNING_STATUSES, true);
        $start = $cupola ? ($this->blast_on_at ?? $this->actual_start_at) : $this->actual_start_at;
        $end = $running ? null : (($cupola ? $this->dropped_at : null) ?? $this->tapped_at ?? $this->closed_at ?? $this->melt_completed_at);

        return ['start' => $start, 'end' => $start ? $end : null, 'running' => $running && $start !== null];
    }

    /** Minutes the furnace has run (or ran) this heat; null before it starts or when the end was never recorded. */
    public function furnaceRunMinutes(): ?int
    {
        $run = $this->furnaceRun();
        if (! $run['start'] || (! $run['running'] && ! $run['end'])) {
            return null;
        }

        return (int) $run['start']->diffInMinutes($run['end'] ?? now(), true);
    }

    /**
     * A compact heat number for tight places: the day's sequence ("#007") for a heat numbered today, otherwise the day and
     * sequence ("22/10 #007"). A number that is not in the usual H-YYYYMMDD-NNN form is returned whole.
     */
    public function shortNumber(): string
    {
        if (! preg_match('/^H-(\d{4})(\d{2})(\d{2})-(\d+)$/', (string) $this->heat_number, $m)) {
            return (string) $this->heat_number;
        }
        $sequence = '#'.str_pad((string) (int) $m[4], 3, '0', STR_PAD_LEFT);

        return $m[1].$m[2].$m[3] === now()->format('Ymd') ? $sequence : $m[3].'/'.$m[2].' '.$sequence;
    }

    public function isCupola(): bool
    {
        return ($this->furnace?->furnace_type ?? $this->process_type) === 'CUPOLA';
    }

    public function furnace(): BelongsTo
    {
        return $this->belongsTo(Furnace::class);
    }

    public function ironGrade(): BelongsTo
    {
        return $this->belongsTo(IronGrade::class);
    }

    /**
     * Status light: red while held for a scrap or equipment problem, green while pouring, orange while the heat is in progress.
     */
    /** A heat is running from the start of the melt to the end of tapping; induction heats use IN_PROGRESS, cupola heats MELTING and TAPPING. */
    public const RUNNING_STATUSES = ['IN_PROGRESS', 'MELTING', 'TAPPING'];

    public function light(): ?string
    {
        if ($this->hold_reason !== null) {
            return 'red';
        }
        if ($this->pours()->where('status', 'IN_PROGRESS')->exists()) {
            return 'green';
        }

        return in_array($this->status, self::RUNNING_STATUSES, true) ? 'orange' : null;
    }

    /**
     * Heats that show in the top bar: held, pouring or in progress, red first, then green, then orange.
     *
     * @return \Illuminate\Support\Collection<int, Heat>
     */
    public static function withLight(): \Illuminate\Support\Collection
    {
        $rank = ['red' => 0, 'green' => 1, 'orange' => 2];

        return static::query()
            ->with(['pours' => fn ($q) => $q->where('status', 'IN_PROGRESS')])
            ->where(fn ($q) => $q->whereNotNull('hold_reason')->orWhereIn('status', self::RUNNING_STATUSES)
                ->orWhereHas('pours', fn ($p) => $p->where('status', 'IN_PROGRESS')))
            ->get()
            ->filter(fn (Heat $h) => $h->light() !== null)
            ->sortBy(fn (Heat $h) => $rank[$h->light()])
            ->values();
    }

    public function lightLabel(): ?string
    {
        return match ($this->light()) {
            'red' => 'On hold: '.($this->hold_reason === 'SCRAP' ? 'scrap problem' : 'equipment problem'),
            'green' => 'Pouring',
            'orange' => 'Heat in progress',
            default => null,
        };
    }

    public function thermalTests(): HasMany
    {
        return $this->hasMany(ThermalAnalysisTest::class)->orderByDesc('taken_at');
    }

    public function chemistryReadings(): HasMany
    {
        return $this->hasMany(HeatChemistryReading::class)->orderBy('reading_number');
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(HeatTreatment::class)->orderBy('treated_at');
    }

    public function pours(): HasMany
    {
        return $this->hasMany(Pour::class);
    }

    public function powerReadings(): HasMany
    {
        return $this->hasMany(HeatPowerReading::class)
            ->orderBy('recorded_at')
            ->orderBy('reading_number');
    }

    public function processReadings(): HasMany
    {
        return $this->hasMany(HeatProcessReading::class)
            ->orderBy('recorded_at')
            ->orderBy('reading_number');
    }

    public function chargeBatch(): BelongsTo
    {
        return $this->belongsTo(ChargeBatch::class);
    }

    public function materialConsumptions(): HasMany
    {
        return $this->hasMany(HeatMaterialConsumption::class)
            ->orderBy('sequence');
    }

    public function chargePosting(): HasOne
    {
        return $this->hasOne(HeatChargePosting::class);
    }
}
