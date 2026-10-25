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
        'skipped_charge_rounds',
        'drop_outcome',
        'drop_reason',
        'drop_expected_minutes',
        'drop_actual_minutes',
    ];

    protected function casts(): array
    {
        return [
            'skipped_charge_rounds' => 'array',
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
    /**
     * How many times the recipe is charged: the target metal weight over the recipe's metal weight per charge, rounded up (cupolas).
     * Never fewer than the charges already recorded. A heat with no target or recipe uses the figure saved on it, else one.
     */
    public function chargeRounds(): int
    {
        $planned = $this->targetChargeRounds() ?? ((int) $this->charge_rounds ?: 1);
        $recorded = (int) ($this->relationLoaded('materialConsumptions') ? $this->materialConsumptions->max('charge_round') : $this->materialConsumptions()->max('charge_round'));

        return max(1, $planned, $recorded);
    }

    /** How the campaign ended, for a closed cupola heat that had its bottom drop judged: "Finished" or "Dropped prematurely". */
    public function outcomeLabel(): ?string
    {
        return $this->drop_outcome === null ? null : ($this->drop_outcome === 'FINISHED' ? 'Finished' : 'Dropped prematurely');
    }

    /** The charges someone removed on purpose; the schedule does not add them back. @return list<int> */
    public function skippedChargeRounds(): array
    {
        return array_values(array_map('intval', $this->skipped_charge_rounds ?? []));
    }

    /** The charges the target needs, or null when the heat is not a cupola or has no target or recipe weight to work it from. */
    public function targetChargeRounds(): ?int
    {
        $perRound = (float) $this->chargeBatch?->metalPerChargeKg();
        $target = (float) $this->target_metal_weight_kg;

        return $this->isCupola() && $perRound > 0 && $target > 0 ? (int) ceil($target / $perRound) : null;
    }

    /** The charges a new heat plans for: what the target needs, else one. */
    public function defaultChargeRounds(): int
    {
        return $this->targetChargeRounds() ?? 1;
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
     * sequence ("22/10 #007"). Both number forms are read: H-2610-25 and H-2610-25-1 (the first and second of 25 October 2026), and the
     * older H-YYYYMMDD-NNN. Any other number is returned whole.
     */
    public function shortNumber(): string
    {
        if (preg_match('/^H-(\d{2})(\d{2})-(\d{2})(?:-(\d+))?$/', (string) $this->heat_number, $m)) {
            [$year, $month, $day, $sequence] = ['20'.$m[1], $m[2], $m[3], (int) ($m[4] ?? 0) + 1];
        } elseif (preg_match('/^H-(\d{4})(\d{2})(\d{2})-(\d+)$/', (string) $this->heat_number, $m)) {
            [$year, $month, $day, $sequence] = [$m[1], $m[2], $m[3], (int) $m[4]];
        } else {
            return (string) $this->heat_number;
        }
        $sequence = '#'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

        return $year.$month.$day === now()->format('Ymd') ? $sequence : $day.'/'.$month.' '.$sequence;
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

    /**
     * The metal charged into the furnace, worked out from the charges recorded: every charge line of a metal material (fuel, flux,
     * sand and binders are left out), returns included.
     */
    public function chargedMetalKg(): float
    {
        return (float) $this->materialConsumptions()->getQuery()->reorder()
            ->join('materials', 'materials.id', '=', 'heat_material_consumptions.material_id')
            ->whereNotIn('materials.material_type', \App\Models\Material::NON_METAL_TYPES)
            ->sum('heat_material_consumptions.actual_qty_kg');
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
