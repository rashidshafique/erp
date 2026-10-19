<?php

namespace App\Domains\Compliance\Models;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named set of quality requirements a customer or standard imposes: certificate type, PPAP level, capability
 * targets, problem-report format and so on. Customers are given profiles; the strictest value across them applies.
 */
class RequirementProfile extends Model
{
    use HasUuids;

    public const REGIONS = ['GLOBAL' => 'Global', 'ASIA' => 'Asia', 'EUROPE' => 'Europe', 'AMERICAS' => 'Americas'];

    public const CERTIFICATES = ['NONE' => 'None', 'ATTACHED' => 'Any certificate attached', 'EN10204_21' => 'EN 10204 2.1', 'EN10204_22' => 'EN 10204 2.2', 'EN10204_31' => 'EN 10204 3.1', 'EN10204_32' => 'EN 10204 3.2'];

    /**
     * key => [label, type, options, help]. Type is "rank" (the later option is stricter), "bool" or "number".
     *
     * @var array<string, array{0: string, 1: string, 2: array<string, string>|null, 3: string}>
     */
    public const SETTINGS = [
        'material_certificate' => ['Certificate required from our suppliers', 'rank', self::CERTIFICATES, 'What must come with incoming material lots.'],
        'delivery_certificate' => ['Certificate we give the customer', 'rank', self::CERTIFICATES, 'The certificate type issued with each delivery.'],
        'ppap_level' => ['PPAP submission level', 'number', null, '0 for none, 1 to 5 as the customer specifies.'],
        'ppk_initial' => ['Ppk target, initial study', 'number', null, 'Usually 1.67 for automotive. 0 for none.'],
        'cpk_ongoing' => ['Cpk target, ongoing production', 'number', null, 'Usually 1.33 for automotive. 0 for none.'],
        'fmea_format' => ['FMEA format', 'rank', ['NONE' => 'None', 'AIAG_4' => 'AIAG 4th edition', 'AIAG_VDA' => 'AIAG-VDA'], 'The format the customer accepts.'],
        'control_plan' => ['Control plan required', 'bool', null, ''],
        'msa_required' => ['Gauge R&R (MSA) required', 'bool', null, ''],
        'lpa_required' => ['Layered process audits required', 'bool', null, ''],
        'report_8d' => ['Problem reports as 8D', 'bool', null, ''],
        'report_a3' => ['Problem reports as A3', 'bool', null, 'Common with Japanese customers.'],
        'five_s_audit' => ['5S audit required', 'bool', null, ''],
        'imds_required' => ['IMDS material declaration required', 'bool', null, ''],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['settings' => 'array', 'is_system' => 'boolean', 'active' => 'boolean'];
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_requirement_profiles', 'requirement_profile_id', 'customer_id');
    }

    /** The stored value for a setting, or its neutral default. */
    public function value(string $key): mixed
    {
        return $this->settings[$key] ?? self::neutral($key);
    }

    public static function neutral(string $key): mixed
    {
        return match (self::SETTINGS[$key][1]) {
            'rank' => array_key_first(self::SETTINGS[$key][2]),
            'bool' => false,
            default => 0,
        };
    }
}
