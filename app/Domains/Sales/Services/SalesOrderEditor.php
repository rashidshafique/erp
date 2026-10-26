<?php

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Models\ProductionOrder;
use App\Models\ProductRevision;
use App\Services\Audit\Audit;
use App\Support\Formats;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes an open sales order. A line that production has started on keeps its product and cannot be removed or cut below what is
 * scheduled or shipped. Every change is logged by the model events; the edit also gets one summary entry carrying the reason.
 */
class SalesOrderEditor
{
    /** A line whose total grows or shrinks more than this many times is saved only once the user confirms it. */
    public const JUMP = 3;

    public function isEditable(SalesOrder $order): bool
    {
        return in_array($order->status, SalesOrder::OPEN, true);
    }

    public function needsReason(SalesOrder $order): bool
    {
        return $order->status !== 'DRAFT';
    }

    /**
     * @param  array{customer_id: string, customer_reference?: ?string, order_date: string, requested_delivery_date?: ?string, notes?: ?string, confirm_jump?: mixed, lines: array<int, array<string, mixed>>}  $data
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
            $replanned = [];
            $jumps = [];
            $repeating = app(RepeatingLines::class);
            $confirmed = ! empty($data['confirm_jump']);
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
                    if (! $new->repeats() && ! empty($row['staggered'])) {
                        $this->stagger($new, $row['deliveries'] ?? []);
                    }
                    $notes[] = "Line added: {$rev->product?->code} x {$row['ordered_qty']}".($repeat['repeat_every'] ? ' '.strtolower(SalesOrderLine::REPEATS[$repeat['repeat_every']]) : '');
                    $linesChanged = true;

                    continue;
                }
                $kept[] = $line->id;
                $wasRepeating = $line->repeats();
                // A repeating line, or one being stopped, keeps the quantity it has scheduled; the box sets each future delivery instead.
                $quantity = $repeat['repeat_every'] || $line->repeats() ? $line->ordered_qty : $row['ordered_qty'];
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
                // Starting to repeat, or changing how a line repeats, plans its deliveries again; the new total is checked before saving.
                $plan = $line->repeats() && ($line->getOriginal('repeat_every') === null || $line->isDirty(['repeat_every', 'repeat_qty', 'repeat_from', 'repeat_until']))
                    ? $repeating->preview($line, (float) ($shipped[$line->id] ?? 0)) : null;
                $total = $plan['total'] ?? (float) $line->ordered_qty;
                $committed = ['floor' => max((float) $started->get($line->id, collect())->sum('planned_qty'), (float) ($shipped[$line->id] ?? 0)),
                    'released' => (float) $started->get($line->id, collect())->sum('planned_qty'), 'shipped' => (float) ($shipped[$line->id] ?? 0),
                    'orders' => $started->get($line->id, collect())->pluck('production_order_number')->all()];
                if ($message = $repeating->belowFloor($line, $total, $committed)) {
                    $this->refuse($message);
                }
                $was = (float) $line->getOriginal('ordered_qty');
                if ($was > 0 && ($total > $was * self::JUMP || $total < $was / self::JUMP)) {
                    $jumps[] = "line {$line->line_number} goes from ".Formats::number($was).' to '.Formats::number($total);
                }
                if ($line->isDirty()) {
                    foreach ($line->getDirty() as $field => $value) {
                        $notes[] = "Line {$line->line_number} {$field}: ".($line->getOriginal($field) ?? '—').' → '.($value ?? '—');
                    }
                    $linesChanged = $linesChanged || $line->isDirty(['ordered_qty', 'product_revision_id', 'repeat_every', 'repeat_qty']) || $plan !== null;
                    $line->save();
                }
                // A one-off line's staggered deliveries are on the form; a line just stopped repeating keeps the deliveries it had.
                if (! $wasRepeating && ! $line->repeats() && array_key_exists('staggered', $row)) {
                    $staggerNotes = $this->stagger($line, ! empty($row['staggered']) ? ($row['deliveries'] ?? []) : []);
                    $notes = [...$notes, ...$staggerNotes];
                    $linesChanged = $linesChanged || $staggerNotes !== [];
                }
                if ($plan) {
                    $replanned[] = [$line, $plan];
                    $notes[] = "Line {$line->line_number} ".($plan['converting'] ? 'now repeats' : 'replanned').': '.count($plan['dates']).' deliveries of '.Formats::number((float) $line->repeat_qty)
                        .($plan['dates'] ? ' from '.$plan['dates'][0]->format('j M Y') : '').'; ordered '.Formats::number($was).' → '.Formats::number($total);
                } elseif ($line->repeats()) {
                    $repeated[] = $line;
                }
            }
            if ($jumps !== [] && ! $confirmed) {
                throw ValidationException::withMessages(['confirm_jump' => 'Please check: '.implode('; ', $jumps).'. If that is right, tick "Yes, these quantities are right" and save again.']);
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
            foreach ($replanned as [$line, $plan]) {
                $repeating->apply($line, $plan);
            }
            foreach ($repeated as $line) {
                $repeating->topUp($line);
            }

            if ($notes !== []) {
                app(Audit::class)->record('EDITED', 'SalesOrder', $order->id, $order->order_number, ['summary' => ['new' => implode('; ', $notes)]], $reason);
            }

            return $linesChanged && $order->hasApprovedReview();
        });
    }

    /**
     * Sets a one-off line's staggered deliveries from the form: a row with a saved delivery's id changes it, a new row adds one, and
     * saved deliveries left off are removed, so no rows means the line is delivered in one go on its date. The deliveries cannot add
     * up to more than the line's ordered quantity; what is left over stays to schedule.
     *
     * @param  array<int, array{id?: ?string, due_date: string, quantity: mixed, notes?: ?string}>  $rows
     * @return list<string> What changed, for the order's history.
     */
    public function stagger(SalesOrderLine $line, array $rows): array
    {
        $scheduled = round(collect($rows)->sum(fn ($row) => (float) $row['quantity']), 3);
        if ($scheduled > (float) $line->ordered_qty + 0.0005) {
            $this->refuse("Line {$line->line_number}'s deliveries add up to ".Formats::number($scheduled).', more than the '.Formats::number((float) $line->ordered_qty).' ordered.');
        }
        $saved = $line->deliveries()->get()->keyBy('id');
        $label = fn ($delivery) => Formats::number((float) $delivery->quantity).' for '.$delivery->due_date->format('j M Y');
        $notes = [];
        $kept = [];
        foreach ($rows as $row) {
            $values = ['due_date' => $row['due_date'], 'quantity' => $row['quantity'], 'notes' => ($row['notes'] ?? null) ?: null];
            $delivery = $saved->get($row['id'] ?? '');
            if (! $delivery) {
                $notes[] = "Line {$line->line_number}: delivery of ".$label($line->deliveries()->create($values)).' added';

                continue;
            }
            $kept[] = $delivery->id;
            $was = $label($delivery);
            $delivery->fill($values);
            if ($delivery->isDirty(['due_date', 'quantity'])) {
                $notes[] = "Line {$line->line_number}: delivery of {$was} → ".$label($delivery);
            }
            $delivery->save();
        }
        foreach ($saved->except($kept) as $gone) {
            $gone->delete();
            $notes[] = "Line {$line->line_number}: delivery of ".$label($gone).' removed';
        }

        return $notes;
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
