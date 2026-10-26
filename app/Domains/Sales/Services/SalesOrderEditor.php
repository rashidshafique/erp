<?php

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Services\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes an open sales order. A line that production has started on keeps its product and cannot be removed or cut below what is
 * scheduled or shipped. Every change is logged by the model events; the edit also gets one summary entry carrying the reason.
 */
class SalesOrderEditor
{
    public function isEditable(SalesOrder $order): bool
    {
        return in_array($order->status, SalesOrder::OPEN, true);
    }

    public function needsReason(SalesOrder $order): bool
    {
        return $order->status !== 'DRAFT';
    }

    /**
     * @param  array{customer_id: string, customer_reference?: ?string, order_date: string, requested_delivery_date?: ?string, notes?: ?string, lines: array<int, array<string, mixed>>}  $data
     * @return bool Whether the lines changed after the contract review was approved, so the review should be run again.
     */
    public function update(SalesOrder $order, array $data, ?string $reason): bool
    {
        return DB::transaction(function () use ($order, $data, $reason) {
            $order = SalesOrder::lockForUpdate()->findOrFail($order->id);
            if (! $this->isEditable($order)) {
                $this->refuse('A '.strtolower(str_replace('_', ' ', $order->status)).' order cannot be edited.');
            }
            $order->load('lines');
            $started = ProductionOrder::whereIn('sales_order_line_id', $order->lines->pluck('id'))->where('status', '!=', 'CANCELLED')->get()->groupBy('sales_order_line_id');
            $shipped = DB::table('dispatch_items')->whereIn('sales_order_line_id', $order->lines->pluck('id'))->selectRaw('sales_order_line_id, sum(quantity_dispatched) q')->groupBy('sales_order_line_id')->pluck('q', 'sales_order_line_id');
            if ($data['customer_id'] !== $order->customer_id && $started->isNotEmpty()) {
                $this->refuse('The customer cannot change once production orders exist.');
            }

            $notes = [];
            $linesChanged = false;
            $kept = [];
            $repeated = [];
            $nextNumber = (int) $order->lines->max('line_number') + 1;
            foreach ($data['lines'] as $row) {
                $line = isset($row['id']) ? $order->lines->firstWhere('id', $row['id']) : null;
                if (isset($row['id']) && ! $line) {
                    $this->refuse('A line on the form does not belong to this order.');
                }
                $repeat = self::repeatAttributes($row, $order);
                if (! $line) {
                    $rev = ProductRevision::fromChoice($row['product_revision_id']);
                    $new = $order->lines()->create([
                        'product_id' => $rev->product_id, 'product_revision_id' => $rev->id, 'line_number' => $nextNumber++,
                        'ordered_qty' => $repeat['repeat_every'] ? 0 : $row['ordered_qty'], 'unit_price' => $row['unit_price'] ?? null, 'uom' => 'piece', 'requested_delivery_date' => $row['requested_delivery_date'] ?? null,
                    ] + $repeat);
                    $kept[] = $new->id;
                    $repeated[] = $new;
                    $notes[] = "Line added: {$rev->product?->code} x {$row['ordered_qty']}".($repeat['repeat_every'] ? ' '.strtolower(SalesOrderLine::REPEATS[$repeat['repeat_every']]) : '');
                    $linesChanged = true;

                    continue;
                }
                $kept[] = $line->id;
                // A repeating line, or one being stopped, keeps the quantity it has scheduled; the box sets each future delivery instead.
                $quantity = $repeat['repeat_every'] || $line->repeats() ? $line->ordered_qty : $row['ordered_qty'];
                $floor = max((float) $started->get($line->id, collect())->sum('planned_qty'), (float) ($shipped[$line->id] ?? 0));
                if ((float) $quantity < $floor - 0.0005) {
                    $this->refuse("Line {$line->line_number} cannot go below {$floor}: that much is scheduled or shipped.");
                }
                $attributes = ['ordered_qty' => $quantity, 'unit_price' => $row['unit_price'] ?? null, 'requested_delivery_date' => $row['requested_delivery_date'] ?? null] + $repeat;
                if ($repeat['repeat_every'] && $line->repeats() && ! (($row['repeat_from'] ?? null) ?: null)) {
                    $attributes['repeat_from'] = $line->repeat_from;
                }
                if (! empty($row['product_revision_id']) && $row['product_revision_id'] !== $line->product_revision_id) {
                    if ($started->has($line->id)) {
                        $this->refuse("Line {$line->line_number} has production orders, so its product cannot change.");
                    }
                    $rev = ProductRevision::fromChoice($row['product_revision_id']);
                    $attributes += ['product_id' => $rev->product_id, 'product_revision_id' => $rev->id];
                }
                $line->fill($attributes);
                if ($line->isDirty()) {
                    foreach ($line->getDirty() as $field => $value) {
                        $notes[] = "Line {$line->line_number} {$field}: ".($line->getOriginal($field) ?? '—').' → '.($value ?? '—');
                    }
                    $linesChanged = $linesChanged || $line->isDirty(['ordered_qty', 'product_revision_id', 'repeat_every', 'repeat_qty']);
                    $line->save();
                }
                if ($line->repeats()) {
                    $repeated[] = $line;
                }
            }
            foreach ($order->lines->whereNotIn('id', $kept) as $gone) {
                if ($started->has($gone->id) || (float) ($shipped[$gone->id] ?? 0) > 0) {
                    $this->refuse("Line {$gone->line_number} has production orders or shipments, so it cannot be removed.");
                }
                $gone->delete();
                $notes[] = "Line {$gone->line_number} removed";
                $linesChanged = true;
            }
            if ($kept === []) {
                $this->refuse('An order needs at least one line.');
            }

            $order->fill(collect($data)->only(['customer_id', 'customer_reference', 'order_date', 'requested_delivery_date', 'notes'])->all());
            foreach ($order->getDirty() as $field => $value) {
                $notes[] = "{$field}: ".($order->getOriginal($field) ?? '—').' → '.($value ?? '—');
            }
            $order->save();
            foreach ($repeated as $line) {
                app(RepeatingLines::class)->topUp($line);
            }

            if ($notes !== []) {
                app(Audit::class)->record('EDITED', 'SalesOrder', $order->id, $order->order_number, ['summary' => ['new' => implode('; ', $notes)]], $reason);
            }

            return $linesChanged && $order->hasApprovedReview();
        });
    }

    /**
     * The repeat columns from one form row. On a repeating line the quantity box is the quantity of each delivery; the first delivery
     * falls on the given start, else the line's or the order's delivery date, else the order date.
     *
     * @param  array<string, mixed>  $row
     * @return array{repeat_every: ?string, repeat_qty: mixed, repeat_from: mixed, repeat_until: mixed}
     */
    public static function repeatAttributes(array $row, SalesOrder $order): array
    {
        if (empty($row['repeat_every'])) {
            return ['repeat_every' => null, 'repeat_qty' => null, 'repeat_from' => null, 'repeat_until' => null];
        }

        return [
            'repeat_every' => $row['repeat_every'], 'repeat_qty' => $row['ordered_qty'],
            'repeat_from' => ($row['repeat_from'] ?? null) ?: (($row['requested_delivery_date'] ?? null) ?: ($order->requested_delivery_date ?? $order->order_date)),
            'repeat_until' => ($row['repeat_until'] ?? null) ?: null,
        ];
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['order' => $message]);
    }
}
