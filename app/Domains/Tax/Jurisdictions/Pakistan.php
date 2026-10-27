<?php

namespace App\Domains\Tax\Jurisdictions;

use App\Domains\Core\Tax\Jurisdiction;

/**
 * Pakistan: sales tax on goods is federal (FBR, Sales Tax Act 1990); sales tax on services is provincial, collected where the
 * service is supplied (SRB in Sindh, PRA in Punjab, KPRA in Khyber Pakhtunkhwa, BRA in Balochistan; FBR for Islamabad). Some buyers
 * are withholding agents and keep back part of the sales tax and income tax for the authority; when the company is one, it keeps
 * back the same from what it pays its suppliers.
 *
 * The rates here are only a starting set and must be checked against the current Finance Act and SROs before use; each loaded
 * row carries a note saying so, and every rate is edited on the Taxation settings page, not here.
 */
class Pakistan implements Jurisdiction
{
    private const CHECK = 'Starting value: check against the current Finance Act and SROs.';

    private const FROM = '2024-07-01';

    public function code(): string
    {
        return 'PK';
    }

    public function name(): string
    {
        return 'Pakistan';
    }

    public function tariffLabel(): string
    {
        return 'PCT code';
    }

    public function tariffDigits(): int
    {
        return 8;
    }

    public function tariffSplit(): ?int
    {
        return 4;
    }

    public function regions(): array
    {
        return ['PB' => 'Punjab', 'SD' => 'Sindh', 'KP' => 'Khyber Pakhtunkhwa', 'BL' => 'Balochistan', 'IS' => 'Islamabad Capital Territory', 'GB' => 'Gilgit-Baltistan', 'AJK' => 'Azad Jammu and Kashmir'];
    }

    public function partyStatuses(): array
    {
        return [
            'REGISTERED' => 'Registered for sales tax',
            'UNREGISTERED_FILER' => 'Not registered for sales tax, income tax filer',
            'UNREGISTERED' => 'Not registered, not a filer',
        ];
    }

    public function registrationFields(): array
    {
        return ['NTN' => 'NTN', 'STRN' => 'STRN (FBR sales tax)', 'CNIC' => 'CNIC (individuals)', 'SRB' => 'SRB registration (Sindh)', 'PRA' => 'PRA registration (Punjab)', 'KPRA' => 'KPRA registration', 'BRA' => 'BRA registration'];
    }

    public function authorities(): array
    {
        return [
            ['code' => 'FBR', 'name' => 'Federal Board of Revenue', 'level' => 'FEDERAL', 'region' => null, 'registration' => 'STRN'],
            ['code' => 'SRB', 'name' => 'Sindh Revenue Board', 'level' => 'PROVINCIAL', 'region' => 'SD', 'registration' => 'SRB'],
            ['code' => 'PRA', 'name' => 'Punjab Revenue Authority', 'level' => 'PROVINCIAL', 'region' => 'PB', 'registration' => 'PRA'],
            ['code' => 'KPRA', 'name' => 'Khyber Pakhtunkhwa Revenue Authority', 'level' => 'PROVINCIAL', 'region' => 'KP', 'registration' => 'KPRA'],
            ['code' => 'BRA', 'name' => 'Balochistan Revenue Authority', 'level' => 'PROVINCIAL', 'region' => 'BL', 'registration' => 'BRA'],
        ];
    }

    public function defaultComponents(): array
    {
        return array_map(fn (array $c) => $c + ['note' => self::CHECK, 'effective_from' => self::FROM], [
            ['code' => 'ST_GOODS', 'name' => 'Sales tax on goods', 'authority' => 'FBR', 'kind' => 'CHARGED', 'rate' => 18.0, 'base' => 'NET'],
            ['code' => 'FURTHER_TAX', 'name' => 'Further tax (buyer not registered)', 'authority' => 'FBR', 'kind' => 'CHARGED', 'rate' => 4.0, 'base' => 'NET'],
            ['code' => 'ST_SERVICES_SD', 'name' => 'Sindh sales tax on services', 'authority' => 'SRB', 'kind' => 'CHARGED', 'rate' => 15.0, 'base' => 'NET'],
            ['code' => 'ST_SERVICES_PB', 'name' => 'Punjab sales tax on services', 'authority' => 'PRA', 'kind' => 'CHARGED', 'rate' => 16.0, 'base' => 'NET'],
            ['code' => 'ST_SERVICES_KP', 'name' => 'KP sales tax on services', 'authority' => 'KPRA', 'kind' => 'CHARGED', 'rate' => 15.0, 'base' => 'NET'],
            ['code' => 'ST_SERVICES_BL', 'name' => 'Balochistan sales tax on services', 'authority' => 'BRA', 'kind' => 'CHARGED', 'rate' => 15.0, 'base' => 'NET'],
            ['code' => 'ST_SERVICES_IS', 'name' => 'Islamabad sales tax on services', 'authority' => 'FBR', 'kind' => 'CHARGED', 'rate' => 16.0, 'base' => 'NET'],
            ['code' => 'WHT_ST', 'name' => 'Sales tax withheld by the buyer', 'authority' => 'FBR', 'kind' => 'WITHHELD', 'rate' => 20.0, 'base' => 'ST_GOODS'],
            ['code' => 'WHT_IT_GOODS', 'name' => 'Income tax withheld on supply of goods', 'authority' => 'FBR', 'kind' => 'WITHHELD', 'rate' => 5.0, 'base' => 'GROSS'],
            ['code' => 'WHT_IT_SERVICES', 'name' => 'Income tax withheld on services', 'authority' => 'FBR', 'kind' => 'WITHHELD', 'rate' => 9.0, 'base' => 'GROSS'],
        ]);
    }

    public function defaultRules(): array
    {
        $services = [];
        foreach (['SD' => 'Sindh', 'PB' => 'Punjab', 'KP' => 'Khyber Pakhtunkhwa', 'BL' => 'Balochistan', 'IS' => 'Islamabad'] as $region => $name) {
            $services[] = ['name' => "Services supplied in {$name}", 'priority' => 30, 'side' => 'BOTH',
                'conditions' => ['supply_type' => 'SERVICE', 'regions' => [$region]], 'components' => ["ST_SERVICES_{$region}"]];
        }

        return [
            ['name' => 'Goods, party registered for sales tax', 'priority' => 10, 'side' => 'BOTH',
                'conditions' => ['supply_type' => 'GOODS', 'party_status' => ['REGISTERED']], 'components' => ['ST_GOODS']],
            ['name' => 'Goods sold to a buyer not registered', 'priority' => 20, 'side' => 'SALE',
                'conditions' => ['supply_type' => 'GOODS', 'party_status' => ['UNREGISTERED_FILER', 'UNREGISTERED']], 'components' => ['ST_GOODS', 'FURTHER_TAX']],
            ...$services,
            ['name' => 'Sales tax withheld by a withholding agent', 'priority' => 80, 'side' => 'SALE',
                'conditions' => ['supply_type' => 'GOODS', 'party_status' => ['REGISTERED'], 'withholding_agent' => true], 'components' => ['WHT_ST']],
            ['name' => 'Income tax withheld by a withholding agent', 'priority' => 90, 'side' => 'SALE',
                'conditions' => ['supply_type' => 'GOODS', 'withholding_agent' => true], 'components' => ['WHT_IT_GOODS']],
            ['name' => 'Sales tax we withhold from a registered supplier', 'priority' => 80, 'side' => 'PURCHASE',
                'conditions' => ['supply_type' => 'GOODS', 'party_status' => ['REGISTERED'], 'withholding_agent' => true], 'components' => ['WHT_ST']],
            ['name' => 'Income tax we withhold on goods bought', 'priority' => 90, 'side' => 'PURCHASE',
                'conditions' => ['supply_type' => 'GOODS', 'withholding_agent' => true], 'components' => ['WHT_IT_GOODS']],
            ['name' => 'Income tax we withhold on services bought', 'priority' => 91, 'side' => 'PURCHASE',
                'conditions' => ['supply_type' => 'SERVICE', 'withholding_agent' => true], 'components' => ['WHT_IT_SERVICES']],
        ];
    }
}
