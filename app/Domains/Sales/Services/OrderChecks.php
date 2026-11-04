<?php

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Models\Customer;
use App\Models\ProductRevision;
use App\Support\OrderRules;

/**
 * The checks panel of the order form: what the contract review would say about the order as typed, before anything is saved.
 * It runs the reviewer's own checks on unsaved models and writes nothing, so the panel and the real review never disagree.
 */
class OrderChecks
{
    /** Each row of the panel and the review check codes it gathers. */
    private const ROWS = [
        'Credit' => ['CREDIT'],
        'Price' => ['PRICE', 'DISCOUNT'],
        'Pattern' => ['PATTERN'],
        'Product and spec' => ['QUANTITY', 'REVISION_STATUS', 'PRODUCT_SPEC', 'CUSTOMER_REQUIREMENT', 'GRADE', 'CONTROL_PLAN'],
        'Melting' => ['FURNACE', 'THERMAL_WINDOW'],
        'Delivery' => ['DELIVERY_DATE'],
    ];

    public function __construct(private ContractReviewer $reviewer)
    {
    }

    /**
     * @param  array{customer_id?: ?string, order_date?: ?string, requested_delivery_date?: ?string, lines?: array<int, array<string, mixed>>}  $input
     * @return array{ready: bool, rows: list<array{name: string, status: 'ok'|'warn'|'block', short: string, text: string}>, blocks: int, warns: int, summary: string, primary: string}
     */
    public function run(array $input): array
    {
        $order = new SalesOrder(['order_date' => $input['order_date'] ?? today(), 'requested_delivery_date' => $input['requested_delivery_date'] ?? null]);
        $customer = filled($input['customer_id'] ?? null) ? Customer::find($input['customer_id']) : null;
        $order->customer_id = $customer?->id;
        $order->setRelation('customer', $customer);

        $lines = collect($input['lines'] ?? [])->filter(fn ($l) => filled($l['product_revision_id'] ?? null))->map(function ($l) use ($order) {
            $rev = ProductRevision::fromChoice($l['product_revision_id']);
            $line = new SalesOrderLine([
                'product_id' => $rev->product_id, 'product_revision_id' => $rev->id, 'ordered_qty' => (float) ($l['ordered_qty'] ?? 0),
                'unit_price' => filled($l['unit_price'] ?? null) ? $l['unit_price'] : null, 'discount_percent' => (float) ($l['discount_percent'] ?? 0),
                'uom' => $l['uom'] ?? 'piece', 'requested_delivery_date' => $l['requested_delivery_date'] ?? null,
                'repeat_every' => $l['repeat_every'] ?? null,
            ]);
            $line->setRelation('product', $rev->product)->setRelation('productRevision', $rev)->setRelation('salesOrder', $order);

            return $line;
        })->values();
        $order->setRelation('lines', $lines);

        if (! $customer || $lines->isEmpty()) {
            return ['ready' => false, 'rows' => [], 'blocks' => 0, 'warns' => 0, 'summary' => 'Pick a customer and a product', 'primary' => $this->label(0)];
        }

        $found = collect($this->reviewer->checkOrder($order));
        foreach ($lines as $line) {
            $found = $found->merge($this->reviewer->checkLine($order, $line));
        }

        $rows = [['name' => 'Contract review'] + $this->reviewRow()];
        foreach (self::ROWS as $name => $codes) {
            $items = $found->filter(fn ($i) => in_array($i[0], $codes, true))->values();
            // With the contract review off, the spec, pattern and melting checks are not asked for; credit, price and delivery still are.
            $reviewed = in_array($name, ['Pattern', 'Product and spec', 'Melting'], true);
            if ($items->isEmpty() || ($name === 'Price' && ! OrderRules::showsPrices()) || ($reviewed && ! OrderRules::reviewRequired())) {
                continue;
            }
            $rows[] = ['name' => $name] + $this->row($items->all());
        }
        $blocks = collect($rows)->where('status', 'block')->count();
        $warns = collect($rows)->where('status', 'warn')->count();

        return ['ready' => true, 'rows' => $rows, 'blocks' => $blocks, 'warns' => $warns,
            'summary' => ($blocks ? $blocks.' blocking · ' : '').($warns ? $warns.' to check' : 'All clear'), 'primary' => $this->label($blocks)];
    }

    /** @return array{status: string, short: string, text: string} */
    private function reviewRow(): array
    {
        return match (true) {
            ! OrderRules::reviewRequired() => ['status' => 'ok', 'short' => 'Not needed', 'text' => 'Contract review is off in the order rules.'],
            OrderRules::reviewsAutomatically() => ['status' => 'ok', 'short' => 'Automatic', 'text' => 'Runs when the order is created and approves itself when clean.'],
            default => ['status' => 'warn', 'short' => 'Required', 'text' => 'Goes to contract review before it is released.'],
        };
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $items
     * @return array{status: string, short: string, text: string}
     */
    private function row(array $items): array
    {
        $items = collect($items);
        $bad = $items->where(1, '!=', 'PASS');
        $block = $bad->where(1, 'BLOCK');
        if ($bad->isEmpty()) {
            return ['status' => 'ok', 'short' => 'OK', 'text' => $items->pluck(2)->unique()->take(2)->implode(' ')];
        }

        return ['status' => $block->isNotEmpty() ? 'block' : 'warn', 'short' => $block->isNotEmpty() ? $block->count().' blocking' : $bad->count().' to check',
            'text' => $bad->pluck(2)->unique()->implode(' ')];
    }

    private function label(int $blocks): string
    {
        return match (true) {
            $blocks > 0 => 'Fix '.$blocks.' blocking check'.($blocks > 1 ? 's' : ''),
            OrderRules::sendsStraightToProduction() && ! OrderRules::reviewRequired() => 'Create and release',
            OrderRules::reviewRequired() => 'Create and review',
            default => 'Create order',
        };
    }
}
