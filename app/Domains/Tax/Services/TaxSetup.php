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

        return ['region' => $saved['region'] ?? null, 'registrations' => $saved['registrations'] ?? []];
    }

    /** @param array<string, string|null> $registrations */
    public static function saveCompany(?string $region, array $registrations): void
    {
        AppSetting::setValue(self::COMPANY, json_encode(['region' => $region ?: null, 'registrations' => array_filter($registrations, 'filled')]), "The company's tax region and registration numbers");
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
