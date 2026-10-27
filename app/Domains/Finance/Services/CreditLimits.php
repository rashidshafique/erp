<?php

namespace App\Domains\Finance\Services;

use App\Domains\Core\Contracts\CreditControl;
use App\Domains\Finance\Models\InvoiceLine;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Models\Customer;
use App\Support\Formats;

/**
 * A customer's credit position: what it owes on invoices (less money on account), the open orders not yet invoiced (before tax,
 * when the rule counts them), against its limit. A hold always stops; a limit or long-overdue invoices warn or stop by the rule.
 * A release lets everything through until its date.
 */
class CreditLimits implements CreditControl
{
    public function __construct(private Receivables $receivables) {}

    /** @return array{invoices: float, on_account: float, orders: float, total: float} */
    public function exposure(string $customerId, ?string $excludingOrderId = null): array
    {
        $invoices = round($this->receivables->open($customerId)->sum(fn ($i) => $i->balance()), 2);
        $onAccount = $this->receivables->onAccount($customerId);
        $orders = FinanceRules::creditCountsOrders() ? $this->openOrders($customerId, $excludingOrderId) : 0.0;

        return ['invoices' => $invoices, 'on_account' => $onAccount, 'orders' => $orders, 'total' => round($invoices - $onAccount + $orders, 2)];
    }

    /** What is owed on invoices overdue by more than the given days. */
    public function overdue(string $customerId, int $days): float
    {
        $cutoff = today()->subDays($days);

        return round($this->receivables->open($customerId)->filter(fn ($i) => $i->due_date && $i->due_date->lt($cutoff))->sum(fn ($i) => $i->balance()), 2);
    }

    /** The value before tax of the open orders' lines not yet invoiced (drafts count as invoiced). */
    public function openOrders(string $customerId, ?string $excludingOrderId = null): float
    {
        if (! class_exists(SalesOrder::class)) {
            return 0.0;
        }
        $lines = SalesOrderLine::whereHas('salesOrder', fn ($q) => $q->where('customer_id', $customerId)->whereIn('status', SalesOrder::OPEN)
            ->when($excludingOrderId, fn ($q) => $q->whereKeyNot($excludingOrderId)))->get(['id', 'ordered_qty', 'unit_price']);

        return round($lines->sum(fn ($l) => max(0, (float) $l->ordered_qty - $this->invoiced($l->id)) * (float) $l->unit_price), 2);
    }

    public function check(string $customerId, float $adding = 0, ?string $excludingOrderId = null): array
    {
        $rule = FinanceRules::creditCheck();
        $customer = Customer::find($customerId);
        if ($rule === 'OFF' || ! $customer) {
            return ['severity' => 'PASS', 'messages' => []];
        }
        if ($customer->credit_release_until && $customer->credit_release_until->gte(today())) {
            return ['severity' => 'PASS', 'messages' => ['Credit released until '.$customer->credit_release_until->displayDate().'.']];
        }
        $m = fn (float $v) => Formats::number($v, 2);
        $messages = [];
        $severity = 'PASS';
        if ($customer->credit_hold) {
            $messages[] = "{$customer->name} is on credit hold".($customer->credit_hold_reason ? ": {$customer->credit_hold_reason}" : '').'.';
            $severity = 'BLOCK';
        }
        if ($customer->credit_limit !== null) {
            $exposure = $this->exposure($customerId, $excludingOrderId);
            $after = round($exposure['total'] + $adding, 2);
            if ($after > (float) $customer->credit_limit) {
                $parts = 'owes '.$m($exposure['invoices'] - $exposure['on_account']).($exposure['orders'] > 0 ? ', open orders '.$m($exposure['orders']) : '').($adding > 0 ? ', this '.$m($adding) : '');
                $messages[] = "Over the credit limit of {$m((float) $customer->credit_limit)}: {$parts}, together {$m($after)}.";
                $severity = $severity === 'BLOCK' ? 'BLOCK' : $rule;
            }
        }
        if (($days = FinanceRules::creditOverdueDays()) > 0 && ($late = $this->overdue($customerId, $days)) > 0) {
            $messages[] = "{$m($late)} is overdue by more than {$days} days.";
            $severity = $severity === 'BLOCK' ? 'BLOCK' : $rule;
        }

        return ['severity' => $severity, 'messages' => $messages];
    }

    private function invoiced(string $salesOrderLineId): float
    {
        return (float) InvoiceLine::where('sales_order_line_id', $salesOrderLineId)
            ->whereHas('invoice', fn ($q) => $q->where('kind', 'INVOICE')->where('status', '!=', 'VOID'))->sum('quantity');
    }
}
