<?php

namespace App\Support;

use App\Domains\Core\Tax\Jurisdiction;
use App\Domains\Core\Tax\TaxJurisdictions;
use App\Models\AppSetting;

/**
 * The country whose tax rules apply, and how its customs tariff code is named and written. The Taxation module's country pack
 * answers when one is installed for the chosen country; otherwise Pakistan's PCT code (eight digits, written 7325.1000) is the
 * default, and any other country falls back to an HS code of six to ten digits.
 */
class TaxJurisdiction
{
    /** @var array<string, array{name: string, tariff_label: string, tariff_digits: int, tariff_split: int}> */
    public const COUNTRIES = [
        'PK' => ['name' => 'Pakistan', 'tariff_label' => 'PCT code', 'tariff_digits' => 8, 'tariff_split' => 4],
    ];

    public const SETTING = 'tax_jurisdiction';

    public static function code(): string
    {
        return strtoupper((string) AppSetting::getValue(self::SETTING, 'PK'));
    }

    /** The installed country pack for the chosen country, if any. */
    public static function pack(): ?Jurisdiction
    {
        return app(TaxJurisdictions::class)->find(self::code());
    }

    public static function tariffLabel(): string
    {
        return self::tariffFormat()['tariff_label'] ?? 'HS code';
    }

    /** A hint of the format, for the form. */
    public static function tariffExample(): string
    {
        $f = self::tariffFormat();

        return $f ? self::format(str_repeat('0', $f['tariff_digits'])) : '732510';
    }

    /** Whether a tariff code, with any dots or spaces taken out, has the digits the country's tariff uses. */
    public static function isValidTariff(string $code): bool
    {
        $digits = preg_replace('/[\s.]/', '', $code);
        $f = self::tariffFormat();

        return $f ? preg_match('/^\d{'.$f['tariff_digits'].'}$/', $digits) === 1 : preg_match('/^\d{6,10}$/', $digits) === 1;
    }

    /** The tariff code written the country's way: 73251000 becomes 7325.1000 in Pakistan. */
    public static function format(string $code): string
    {
        $digits = preg_replace('/[\s.]/', '', $code);
        $split = self::tariffFormat()['tariff_split'] ?? null;

        return $split && strlen($digits) > $split ? substr($digits, 0, $split).'.'.substr($digits, $split) : $digits;
    }

    /** @return array{tariff_label: string, tariff_digits: int, tariff_split: ?int}|null */
    private static function tariffFormat(): ?array
    {
        if ($pack = self::pack()) {
            return ['tariff_label' => $pack->tariffLabel(), 'tariff_digits' => $pack->tariffDigits(), 'tariff_split' => $pack->tariffSplit()];
        }

        return self::COUNTRIES[self::code()] ?? null;
    }
}
