<?php

namespace App\Domains\Tax\Services;

use App\Models\AppSetting;

/** What is checked before a tax invoice is issued, each Off, Warn or Enforced, set on the Taxation settings page. */
class TaxRules
{
    public const LEVELS = ['OFF' => 'Off', 'WARN' => 'Warn', 'ENFORCED' => 'Enforced: the invoice cannot be issued'];

    /** key => [setting, label] */
    public const RULES = [
        'buyer_registration' => ['tax.check.buyer_registration', 'Buyer has a registration number (NTN, STRN or CNIC) on a taxed invoice'],
        'tariff_code' => ['tax.check.tariff_code', 'Every goods line has its tariff code'],
        'unregistered_authority' => ['tax.check.unregistered_authority', 'A line is taxed by an authority you are not registered with'],
    ];

    public static function get(string $rule): string
    {
        try {
            $value = AppSetting::getValue(self::RULES[$rule][0], 'WARN');
        } catch (\Throwable) {
            return 'WARN';
        }

        return isset(self::LEVELS[$value]) ? $value : 'WARN';
    }

    public static function set(string $rule, string $value): void
    {
        AppSetting::setValue(self::RULES[$rule][0], $value, self::RULES[$rule][1]);
    }
}
