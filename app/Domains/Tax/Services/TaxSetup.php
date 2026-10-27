<?php

namespace App\Domains\Tax\Services;

use App\Domains\Core\Tax\Jurisdiction;
use App\Domains\Tax\Models\TaxAuthority;
use App\Domains\Tax\Models\TaxComponent;
use App\Domains\Tax\Models\TaxRule;
use App\Models\AppSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** The company's own tax registration, loading a country's starting taxes and rules, and changing a rate from a date. */
class TaxSetup
{
    public const COMPANY = 'tax.company';

    /** @return array{region: ?string, registrations: array<string, string>} */
    public static function company(): array
    {
        $saved = json_decode((string) AppSetting::getValue(self::COMPANY, ''), true) ?: [];

        return ['region' => $saved['region'] ?? null, 'registrations' => $saved['registrations'] ?? [], 'withholding_agent' => (bool) ($saved['withholding_agent'] ?? false)];
    }

    /**
     * @param  array<string, string|null>  $registrations
     * @param  bool  $withholdingAgent  whether the company must keep back tax from what it pays suppliers
     */
    public static function saveCompany(?string $region, array $registrations, bool $withholdingAgent = false): void
    {
        AppSetting::setValue(self::COMPANY, json_encode(['region' => $region ?: null, 'registrations' => array_filter($registrations, 'filled'), 'withholding_agent' => $withholdingAgent]),
            "The company's tax region, registration numbers and whether it withholds tax");
    }

    /** Whether the country already has taxes or rules that loading its defaults would replace. */
    public function hasData(string $jurisdiction): bool
    {
        return TaxComponent::where('jurisdiction', $jurisdiction)->exists() || TaxRule::where('jurisdiction', $jurisdiction)->exists();
    }

    /**
     * Loads a country's starting authorities, taxes and rules. Authorities are kept (with whether the company is registered with
     * them); taxes and rules already there are replaced, so this asks for $replace when there are any.
     */
    public function loadDefaults(Jurisdiction $pack, bool $replace = false): void
    {
        $code = $pack->code();
        if (! $replace && $this->hasData($code)) {
            throw new InvalidArgumentException("{$pack->name()} already has taxes or rules. Confirm to replace them with the starting set.");
        }

        DB::transaction(function () use ($pack, $code) {
            $authorities = [];
            foreach ($pack->authorities() as $a) {
                $authorities[$a['code']] = TaxAuthority::updateOrCreate(['jurisdiction' => $code, 'code' => $a['code']],
                    ['name' => $a['name'], 'level' => $a['level'], 'region' => $a['region'], 'registration_label' => $a['registration']]);
            }
            TaxRule::where('jurisdiction', $code)->delete();
            TaxComponent::where('jurisdiction', $code)->delete();
            foreach ($pack->defaultComponents() as $c) {
                TaxComponent::create(['jurisdiction' => $code, 'tax_authority_id' => $authorities[$c['authority']]->id, 'code' => $c['code'], 'name' => $c['name'],
                    'kind' => $c['kind'], 'rate' => $c['rate'], 'base' => $c['base'], 'effective_from' => $c['effective_from'], 'note' => $c['note'] ?? null]);
            }
            foreach ($pack->defaultRules() as $r) {
                TaxRule::create(['jurisdiction' => $code] + $r);
            }
        });
    }

    /**
     * Adds the pack's taxes and rules that a country loaded earlier does not have yet (by tax code and rule name), leaving
     * everything already there as it was edited. Returns how many rows were added.
     */
    public function addMissing(Jurisdiction $pack): int
    {
        $code = $pack->code();
        if (! $this->hasData($code)) {
            return 0;
        }

        return DB::transaction(function () use ($pack, $code) {
            $added = 0;
            $authorities = TaxAuthority::where('jurisdiction', $code)->get()->keyBy('code');
            $have = TaxComponent::where('jurisdiction', $code)->pluck('code')->unique()->all();
            foreach ($pack->defaultComponents() as $c) {
                if (! in_array($c['code'], $have, true) && isset($authorities[$c['authority']])) {
                    TaxComponent::create(['jurisdiction' => $code, 'tax_authority_id' => $authorities[$c['authority']]->id, 'code' => $c['code'], 'name' => $c['name'],
                        'kind' => $c['kind'], 'rate' => $c['rate'], 'base' => $c['base'], 'effective_from' => $c['effective_from'], 'note' => $c['note'] ?? null]);
                    $added++;
                }
            }
            $names = TaxRule::where('jurisdiction', $code)->pluck('name')->all();
            foreach ($pack->defaultRules() as $r) {
                if (! in_array($r['name'], $names, true)) {
                    TaxRule::create(['jurisdiction' => $code] + $r);
                    $added++;
                }
            }

            return $added;
        });
    }

    /**
     * Sets a new rate for a tax from a date: the row in force then is closed the day before and a new one starts, so documents
     * made before the date keep the old rate. A rate already starting on that date is corrected in place.
     */
    public function changeRate(TaxComponent $component, float $rate, CarbonInterface $from, ?string $note = null): TaxComponent
    {
        return DB::transaction(function () use ($component, $rate, $from, $note) {
            $same = TaxComponent::where('jurisdiction', $component->jurisdiction)->where('code', $component->code);
            if ($existing = (clone $same)->whereDate('effective_from', $from->toDateString())->first()) {
                $existing->update(['rate' => $rate, 'note' => $note ?? $existing->note]);

                return $existing;
            }
            if ((clone $same)->whereDate('effective_from', '>', $from->toDateString())->exists()) {
                throw new InvalidArgumentException("{$component->name} already has a rate starting after ".$from->toDateString().'.');
            }
            $current = (clone $same)->on($from)->first();
            $current?->update(['effective_to' => $from->copy()->subDay()->toDateString()]);
            $base = $current ?? $component;

            return TaxComponent::create($base->only(['jurisdiction', 'tax_authority_id', 'code', 'name', 'kind', 'base'])
                + ['rate' => $rate, 'effective_from' => $from->toDateString(), 'note' => $note]);
        });
    }
}
