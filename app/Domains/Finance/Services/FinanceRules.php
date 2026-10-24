<?php

namespace App\Domains\Finance\Services;

use App\Models\AppSetting;
use App\Support\Formats;

/** How invoicing behaves, set under Settings > Invoicing. */
class FinanceRules
{
    public const DUE_DAYS = 'finance.due_days';

    public const CURRENCY = 'finance.currency';

    /** Days to payment when the customer's terms do not say. */
    public static function dueDays(): int
    {
        return (int) AppSetting::getValue(self::DUE_DAYS, '30');
    }

    /** The currency code on a new invoice; the foundry's display currency unless set here. */
    public static function currency(): string
    {
        return (string) AppSetting::getValue(self::CURRENCY, Formats::all()['currency']);
    }

    /** Days from a customer's payment terms ("Net 45", "60 days"), or the default. */
    public static function daysFromTerms(?string $terms): int
    {
        return $terms !== null && preg_match('/(\d{1,3})/', $terms, $m) ? (int) $m[1] : self::dueDays();
    }
}
