<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * The country whose tax rules apply, and how its customs tariff code is named and written. Pakistan is the default: its PCT code
 * (Pakistan Customs Tariff) has eight digits and is written 7325.1000. Other countries fall back to the six-digit HS heading or longer.
 */
class TaxJurisdiction
{
    /** @var array<string, array{name: string, tariff_label: string, tariff_digits: int, tariff_split: int}> */
    public const COUNTRIES = [
        'PK' => ['name' => 'Pakistan', 'tariff_label' => 'PCT code', 'tariff_digits' => 8, 'tariff_split' => 4],
    ];

    public static function code(): string
    {
        return strtoupper((string) AppSetting::getValue('tax_jurisdiction', 'PK'));
    }

    public static function tariffLabel(): string
    {
        return self::COUNTRIES[self::code()]['tariff_label'] ?? 'HS code';
    }

    /** A hint of the format, for the form. */
    public static function tariffExample(): string
    {
        return isset(self::COUNTRIES[self::code()]) ? self::format(str_repeat('0', self::COUNTRIES[self::code()]['tariff_digits'])) : '732510';
    }

    /** Whether a tariff code, with any dots or spaces taken out, has the digits the country's tariff uses. */
    public static function isValidTariff(string $code): bool
    {
        $digits = preg_replace('/[\s.]/', '', $code);
        $country = self::COUNTRIES[self::code()] ?? null;

        return $country ? preg_match('/^\d{'.$country['tariff_digits'].'}$/', $digits) === 1 : preg_match('/^\d{6,10}$/', $digits) === 1;
    }

    /** The tariff code written the country's way: 73251000 becomes 7325.1000 in Pakistan. */
    public static function format(string $code): string
    {
        $digits = preg_replace('/[\s.]/', '', $code);
        $split = self::COUNTRIES[self::code()]['tariff_split'] ?? null;

        return $split && strlen($digits) > $split ? substr($digits, 0, $split).'.'.substr($digits, $split) : $digits;
    }
}
