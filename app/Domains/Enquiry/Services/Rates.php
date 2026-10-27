<?php

namespace App\Domains\Enquiry\Services;

use App\Models\AppSetting;

/** The cost rates in use: Settings → Quotation rates over the placeholders in config/quotation.php. */
class Rates
{
    public static function get(): array
    {
        $out = config('quotation.rates');
        foreach ($out as $k => $default) {
            $v = AppSetting::getValue('quotation.'.$k, null);
            if ($v !== null && is_numeric($v)) {
                $out[$k] = (float) $v;
            }
        }

        return $out;
    }

    /** @return list<string> rates still on their placeholder, never saved under Settings → Quotation rates */
    public static function unset(): array
    {
        $saved = AppSetting::whereIn('key', array_map(fn ($k) => 'quotation.'.$k, self::keys()))->pluck('key')->all();

        return array_values(array_filter(self::keys(), fn ($k) => ! in_array('quotation.'.$k, $saved, true)));
    }

    public static function keys(): array
    {
        return array_keys(config('quotation.rates'));
    }

    /** Expected scrap for a manufacturability score. */
    public static function scrapForScore(?int $score, array $rates): float
    {
        if ($score === null) {
            return (float) $rates['scrap_default_pct'];
        }
        $bands = config('quotation.scrap_by_score');
        krsort($bands);
        foreach ($bands as $min => $pct) {
            if ($score >= $min) {
                return (float) $pct;
            }
        }

        return (float) end($bands);
    }
}
