<?php

namespace App\Domains\Enquiry\Models;

use App\Models\CastingAnalysis;
use App\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

class EnquiryItem extends Model
{
    use HasUuids;

    public const FEASIBILITY = ['PENDING' => 'Not assessed', 'OK' => 'Feasible', 'CONDITIONAL' => 'Feasible with conditions', 'NOT_FEASIBLE' => 'Not feasible'];

    public const PROCESSES = ['GREEN_SAND' => 'Green sand, HPML', 'NO_BAKE' => 'No-bake', 'SHELL' => 'Shell'];

    public const SUPPLY_CONDITIONS = ['AS_CAST' => 'As cast', 'FETTLED' => 'Fettled', 'MACHINED' => 'Machined', 'MACHINED_PAINTED' => 'Machined, painted'];

    /** The quality requirements the intake screen offers as chips (design 1b). PPAP also carries a level, 1 to 5. */
    public const QUALITY = [
        'PPAP' => 'PPAP', 'SAFETY' => 'Safety-critical (SC/CC)', 'MPI' => 'MPI 100%', 'NODULARITY' => 'Nodularity ≥ 85%', 'RADIOGRAPHY' => 'Radiography',
        'ULTRASONIC' => 'Ultrasonic', 'HYDRO' => 'Hydro / leak test', 'CERT_31' => '3.1 certificate',
    ];

    protected $guarded = [];

    /**
     * The rules for the intake fields, shared by the new-request dialog and the part forms. The ticked chips arrive as quality[],
     * with ppap_level and quality_other beside them; intakeData() folds those three into the quality column.
     *
     * @return array<string, list<mixed>>
     */
    public static function intakeRules(): array
    {
        return [
            'process' => ['nullable', Rule::in(array_keys(self::PROCESSES))],
            'supply_condition' => ['nullable', Rule::in(array_keys(self::SUPPLY_CONDITIONS))],
            'call_off' => ['nullable', 'string', 'max:60'],
            'sop_on' => ['nullable', 'date'],
            'programme_years' => ['nullable', 'integer', 'min:1', 'max:50'],
            'quality' => ['nullable', 'array'],
            'quality.*' => [Rule::in(array_keys(self::QUALITY))],
            'ppap_level' => ['nullable', 'integer', 'between:1,5'],
            'quality_other' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * The validated input with the quality chips folded into the quality column: {tests: [...], ppap_level: n, other: "..."}.
     *
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    public static function intakeData(array $d): array
    {
        $tests = array_values($d['quality'] ?? []);
        $quality = array_filter([
            'tests' => $tests ?: null,
            'ppap_level' => in_array('PPAP', $tests, true) && filled($d['ppap_level'] ?? null) ? (int) $d['ppap_level'] : null,
            'other' => filled($d['quality_other'] ?? null) ? trim($d['quality_other']) : null,
        ]);
        unset($d['quality'], $d['ppap_level'], $d['quality_other']);

        return $d + ['quality' => $quality ?: null];
    }

    /** The quality requirements as words for a summary line: "PPAP level 3, MPI 100%, ...". */
    public function qualityText(): string
    {
        $q = $this->quality ?? [];

        return collect($q['tests'] ?? [])->map(fn ($t) => $t === 'PPAP' && ! empty($q['ppap_level']) ? 'PPAP level '.$q['ppap_level'] : (self::QUALITY[$t] ?? $t))
            ->push($q['other'] ?? null)->filter()->implode(', ');
    }

    protected function casts(): array
    {
        return ['heat_treatment' => 'boolean', 'ndt' => 'boolean', 'weight_kg' => 'float', 'machining_cost' => 'float', 'other_cost' => 'float', 'tooling_cost' => 'float', 'target_price' => 'float', 'sop_on' => 'date', 'quality' => 'array'];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();   // archived products still show on old records
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(CastingAnalysis::class, 'casting_analysis_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(EnquiryItemReview::class);
    }

    /** The worst verdict among the signed departments, or null while none has signed. */
    public function reviewOutcome(): ?string
    {
        $verdicts = $this->reviews->pluck('verdict');
        foreach (['NOT_FEASIBLE', 'CONDITIONAL', 'OK'] as $v) {
            if ($verdicts->contains($v)) {
                return $v;
            }
        }

        return null;
    }
}
