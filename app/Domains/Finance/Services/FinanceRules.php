<?php

namespace App\Domains\Finance\Services;

use App\Models\AppSetting;
use App\Support\Formats;

/** How invoicing behaves, set under Settings > Invoicing. */
class FinanceRules
{
    public const DUE_DAYS = 'finance.due_days';

    public const CURRENCY = 'finance.currency';

    public const BILL_MATCH = 'finance.bill_match';

    public const BILL_TOLERANCE = 'finance.bill_tolerance';

    /** What happens when a supplier bill line asks for more than was received or more than the order price. */
    public const MATCH_OPTIONS = [
        'OFF' => 'Not checked',
        'WARN' => 'Shown on the bill; it can still be posted',
        'BLOCK' => 'The bill cannot be posted until it matches the order and what was received',
    ];

    public const CREDIT_CHECK = 'finance.credit_check';

    public const CREDIT_ORDERS = 'finance.credit_counts_orders';

    public const CREDIT_OVERDUE_DAYS = 'finance.credit_overdue_days';

    /** What happens when a customer is over its limit or has invoices long overdue, at contract review and at dispatch. */
    public const CREDIT_OPTIONS = [
        'OFF' => 'Not checked',
        'WARN' => 'Shown at contract review and dispatch; they can go ahead',
        'BLOCK' => 'Contract review and dispatch stop until it is paid down or released',
    ];

    /** @return 'OFF'|'WARN'|'BLOCK' */
    public static function creditCheck(): string
    {
        $v = (string) AppSetting::getValue(self::CREDIT_CHECK, 'WARN');

        return isset(self::CREDIT_OPTIONS[$v]) ? $v : 'WARN';
    }

    /** Whether open orders not yet invoiced count towards what a customer owes. */
    public static function creditCountsOrders(): bool
    {
        return (string) AppSetting::getValue(self::CREDIT_ORDERS, '1') === '1';
    }

    /** Invoices overdue by more than this many days count against the customer; 0 leaves overdue invoices out of the check. */
    public static function creditOverdueDays(): int
    {
        return max(0, (int) AppSetting::getValue(self::CREDIT_OVERDUE_DAYS, '0'));
    }

    public const BILL_COST = 'finance.bill_price_to_cost';

    /** Whether a supplier bill's price replaces the order price as the cost of the lots received on that order line. */
    public const BILL_COST_OPTIONS = [
        'ON' => 'The billed price becomes the cost of the lots received on that order line (what is left in stock)',
        'OFF' => 'Lots keep the order price',
    ];

    /** @return 'ON'|'OFF' */
    public static function billPriceToCost(): string
    {
        $v = (string) AppSetting::getValue(self::BILL_COST, 'ON');

        return isset(self::BILL_COST_OPTIONS[$v]) ? $v : 'ON';
    }

    public const ADVANCES = 'finance.apply_advances';

    /** What happens to money already received from a customer or paid to a supplier when their next invoice or bill counts. */
    public const ADVANCE_OPTIONS = [
        'MANUAL' => 'Stays on account until someone uses it (a button on the invoice or bill)',
        'AUTO' => 'Goes on the invoice or bill as soon as it is issued or posted, the same order\'s money first',
    ];

    /** @return 'MANUAL'|'AUTO' */
    public static function advances(): string
    {
        $v = (string) AppSetting::getValue(self::ADVANCES, 'MANUAL');

        return isset(self::ADVANCE_OPTIONS[$v]) ? $v : 'MANUAL';
    }

    /** @return 'OFF'|'WARN'|'BLOCK' */
    public static function billMatch(): string
    {
        $v = (string) AppSetting::getValue(self::BILL_MATCH, 'WARN');

        return isset(self::MATCH_OPTIONS[$v]) ? $v : 'WARN';
    }

    /** How far over the order price or the received quantity a bill line may go before it counts as a mismatch, in percent. */
    public static function billTolerance(): float
    {
        return (float) AppSetting::getValue(self::BILL_TOLERANCE, '2');
    }

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
