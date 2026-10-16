<?php $__env->startSection('title', $q->code); ?>
<?php $__env->startSection('breadcrumb'); ?><a href="<?php echo e(route('enquiries.index')); ?>">Enquiries</a> / <a href="<?php echo e(route('enquiries.show', $q->enquiry)); ?>"><?php echo e($q->enquiry->code); ?></a> / <?php echo e($q->code); ?> <?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<style>.ca-wrap td, .ca-wrap th { white-space: normal; vertical-align: top; } .num { text-align: right; } .q-in { width: 84px; } .bd td { padding: 2px 8px; font-size: 12.5px; }</style>
<?php
    $qcls = ['DRAFT' => 'status-on-hold', 'ISSUED' => 'status-in-progress', 'ACCEPTED' => 'status-completed', 'REJECTED' => 'status-cancelled', 'EXPIRED' => 'status-cancelled', 'SUPERSEDED' => 'status-cancelled'];
    $draft = $q->status === 'DRAFT';
    $n = fn ($v, $d = 2) => number_format((float) $v, $d);
    $total = $q->lines->sum(fn ($l) => $l->unit_price * $l->qty);
    $min = \App\Services\Quotation\Rates::get()['min_margin_pct'];
    $low = $q->lines->min('effective_margin_pct');
?>
<div class="page-heading">
    <div><h1><?php echo e($q->code); ?> <span class="muted" style="font-size:.6em">version <?php echo e($q->version); ?></span></h1>
    <p><?php echo e($q->enquiry->title); ?> · <?php echo e($q->enquiry->customer?->name); ?> · <span class="status <?php echo e($qcls[$q->status] ?? ''); ?>"><?php echo e($q->status); ?></span></p></div>
    <div class="actions">
        <a class="btn" href="<?php echo e(route('enquiries.show', $q->enquiry)); ?>">Back to enquiry</a>
        <?php if(! $draft): ?><a class="btn" href="<?php echo e(route('quotations.print', $q)); ?>" target="_blank">Customer copy</a><?php endif; ?>
    </div>
</div>
<?php if(session('error')): ?><div class="alert alert-error"><?php echo e(session('error')); ?></div><?php endif; ?>
<?php if($q->status === 'ACCEPTED' && $q->salesOrder): ?><div class="alert alert-success">Accepted. Sales order <a href="<?php echo e(route('sales-orders.show', $q->salesOrder)); ?>"><?php echo e($q->salesOrder->order_number); ?></a> was created.</div><?php endif; ?>
<?php if($q->status === 'EXPIRED'): ?><div class="alert alert-error">This quotation passed its valid-until date. Create a new version from the enquiry and issue it again.</div><?php endif; ?>

<div class="tab-marker" data-tab-start="Prices"></div>
<form method="POST" action="<?php echo e(route('quotations.update', $q)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
<div class="panel ca-wrap"><div class="panel-header"><h2>Cost build-up and price</h2><span class="muted small"><?php echo e($q->currency); ?> per casting</span></div>
<div class="table-wrap"><table>
<thead><tr><th>Part</th><th class="num">Qty</th><th class="num">Weight kg</th><th class="num">Poured kg</th><th class="num">Scrap %</th><th class="num">Cost</th><th class="num">Margin %</th><th class="num">Price override</th><th class="num">Price</th><th class="num">Effective margin</th></tr></thead>
<tbody>
<?php $__currentLoopData = $q->lines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr>
    <td><strong><?php echo e($l->line_no); ?>. <?php echo e($l->item->description); ?></strong><div class="muted small"><?php echo e($l->basis); ?></div></td>
    <td class="num"><?php if($draft): ?><input class="q-in" type="number" min="1" name="lines[<?php echo e($l->id); ?>][qty]" value="<?php echo e($l->qty); ?>"><?php else: ?><?php echo e($l->qty); ?><?php endif; ?></td>
    <td class="num"><?php echo e($n($l->weight_kg, 2)); ?></td><td class="num"><?php echo e($n($l->poured_kg, 2)); ?></td>
    <td class="num"><?php if($draft): ?><input class="q-in" type="number" step="0.1" min="0" name="lines[<?php echo e($l->id); ?>][scrap_pct]" value="<?php echo e($l->scrap_pct); ?>"><?php else: ?><?php echo e($n($l->scrap_pct, 1)); ?><?php endif; ?></td>
    <td class="num"><?php echo e($n($l->unit_cost)); ?></td>
    <td class="num"><?php if($draft): ?><input class="q-in" type="number" step="0.1" min="0" name="lines[<?php echo e($l->id); ?>][margin_pct]" value="<?php echo e($l->margin_pct); ?>"><?php else: ?><?php echo e($n($l->margin_pct, 1)); ?><?php endif; ?></td>
    <td class="num"><?php if($draft): ?><input class="q-in" type="number" step="0.01" min="0" name="lines[<?php echo e($l->id); ?>][price_override]" value="<?php echo e($l->price_override); ?>" placeholder="<?php echo e($n($l->breakdown['calculated_price'] ?? 0)); ?>"><?php else: ?><?php echo e($l->price_override ? $n($l->price_override) : '—'); ?><?php endif; ?></td>
    <td class="num"><strong><?php echo e($n($l->unit_price)); ?></strong></td>
    <td class="num"><span class="<?php echo e($l->effective_margin_pct < $min ? 'text-danger' : ''); ?>"><?php echo e($n($l->effective_margin_pct, 1)); ?>%</span></td>
</tr>
<tr><td colspan="10"><details><summary class="small">Where the cost comes from</summary>
<?php $b = $l->breakdown ?? []; $parts = ['metal' => 'Molten metal ('.$n($b['melt_rate'] ?? 0, 0).'/kg)', 'moulding' => 'Moulding', 'cores' => 'Cores', 'fettling' => 'Fettling', 'heat_treatment' => 'Heat treatment', 'testing' => 'Testing and NDT', 'packing' => 'Packing', 'overhead' => 'Overhead', 'machining' => 'Machining', 'other' => 'Other', 'scrap' => 'Scrap allowance', 'tooling' => 'Tooling spread']; ?>
<table class="bd"><tbody><?php $__currentLoopData = $parts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $lab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(($b[$k] ?? 0) != 0): ?><tr><td><?php echo e($lab); ?></td><td class="num"><?php echo e($n($b[$k])); ?></td></tr><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<tr><td><strong>Cost</strong></td><td class="num"><strong><?php echo e($n($l->unit_cost)); ?></strong></td></tr>
<tr><td>Price from margin</td><td class="num"><?php echo e($n($b['calculated_price'] ?? 0)); ?></td></tr></tbody></table>
</details></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody>
<tfoot><tr><th colspan="8" class="num">Total for the quantities above</th><th class="num"><?php echo e($n($total)); ?></th><th></th></tr></tfoot>
</table></div></div>

<div class="panel"><div class="panel-header"><h2>Terms</h2></div><div class="panel-body">
<?php if($draft): ?>
<div class="form-grid">
    <div class="field"><label>Valid until</label><input type="date" name="valid_until" value="<?php echo e(old('valid_until', $q->valid_until?->toDateString())); ?>"><div class="help">Blank: <?php echo e(config('quotation.validity_days')); ?> days from issue.</div></div>
    <div class="field"><label>Delivery, weeks from order</label><input type="number" min="1" name="delivery_weeks" value="<?php echo e(old('delivery_weeks', $q->delivery_weeks)); ?>"></div>
    <div class="field"><label>Payment terms</label><input name="payment_terms" value="<?php echo e(old('payment_terms', $q->payment_terms)); ?>"></div>
    <div class="field"><label>Tooling</label><select name="tooling_mode"><option value="NONE" <?php if($q->tooling_mode === 'NONE'): echo 'selected'; endif; ?>>Not charged (customer supplies, or absorbed)</option><option value="UPFRONT" <?php if($q->tooling_mode === 'UPFRONT'): echo 'selected'; endif; ?>>Charged up front, separate invoice</option><option value="AMORTISED" <?php if($q->tooling_mode === 'AMORTISED'): echo 'selected'; endif; ?>>Spread over the castings</option></select></div>
    <div class="field"><label>Castings to spread tooling over</label><input type="number" min="1" name="tooling_amortise_qty" value="<?php echo e(old('tooling_amortise_qty', $q->tooling_amortise_qty)); ?>"></div>
    <div class="field field-full"><label>Notes for the customer</label><textarea name="notes" rows="3"><?php echo e(old('notes', $q->notes)); ?></textarea></div>
</div>
<p><button class="btn btn-primary" type="submit">Save and recalculate</button></p>
<?php else: ?>
<p>Valid until <?php echo e($q->valid_until?->format('d M Y')); ?> · delivery <?php echo e($q->delivery_weeks ? $q->delivery_weeks.' weeks' : 'to be agreed'); ?> · <?php echo e($q->payment_terms ?: 'payment terms not stated'); ?> · tooling <?php echo e(['NONE' => 'not charged', 'UPFRONT' => 'charged up front ('.$n($q->upfrontToolingPrice()).')', 'AMORTISED' => 'spread over '.$q->tooling_amortise_qty.' castings'][$q->tooling_mode]); ?></p>
<?php if($q->notes): ?><p><?php echo e($q->notes); ?></p><?php endif; ?>
<?php endif; ?>
</div></div>
</form>

<div class="tab-marker" data-tab-start="Decision"></div>
<?php if($draft): ?>
<div class="panel"><div class="panel-header"><h2>Before it can go out</h2></div><div class="panel-body">
<?php if($blockers): ?><ul><?php $__currentLoopData = $blockers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($b); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul><?php else: ?><p>Every part is assessed and priced.</p><?php endif; ?>
<?php if($low < $min): ?><p class="muted">The lowest margin is <?php echo e($n($low, 1)); ?>%, below the <?php echo e($n($min, 1)); ?>% minimum. Issuing will ask for approval if that approval policy is switched on.</p><?php endif; ?>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<form method="POST" action="<?php echo e(route('quotations.refresh', $q)); ?>"><?php echo csrf_field(); ?><button class="btn" type="submit">Re-read the enquiry</button></form>
<form method="POST" action="<?php echo e(route('quotations.issue', $q)); ?>" onsubmit="return confirm('Issue this quotation to the customer?')"><?php echo csrf_field(); ?><button class="btn btn-primary" type="submit" <?php if($blockers): echo 'disabled'; endif; ?>>Issue quotation</button></form>
</div></div></div>
<?php elseif($q->status === 'ISSUED'): ?>
<div class="panel"><div class="panel-header"><h2>Customer's answer</h2></div><div class="panel-body">
<p>Issued <?php echo e($q->issued_at?->format('d M Y')); ?> by <?php echo e($q->issued_by_name); ?>. Accepting creates the product and a draft revision if they do not exist, and a draft sales order priced from this quotation.</p>
<div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
<form method="POST" action="<?php echo e(route('quotations.accept', $q)); ?>" onsubmit="return confirm('Customer accepted? This creates a draft sales order.')"><?php echo csrf_field(); ?><button class="btn btn-primary" type="submit">Customer accepted</button></form>
<form method="POST" action="<?php echo e(route('quotations.reject', $q)); ?>" style="display:flex;gap:6px"><?php echo csrf_field(); ?><input name="decision_note" placeholder="Why (optional)"><button class="btn btn-danger" type="submit">Customer declined</button></form>
</div></div></div>
<?php else: ?>
<div class="panel"><div class="panel-body"><p><?php echo e($q->status); ?><?php echo e($q->decided_at ? ' on '.$q->decided_at->format('d M Y') : ''); ?><?php echo e($q->decision_note ? ' — '.$q->decision_note : ''); ?></p></div></div>
<?php endif; ?>

<div class="tab-marker" data-tab-start="Versions & history"></div>
<div class="panel"><div class="panel-header"><h2>Versions</h2></div><div class="table-wrap"><table><thead><tr><th>Quotation</th><th>Version</th><th>Status</th></tr></thead><tbody>
<?php $__currentLoopData = $versions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><a href="<?php echo e(route('quotations.show', $v->id)); ?>"><?php echo e($v->code); ?></a></td><td><?php echo e($v->version); ?></td><td><?php echo e($v->status); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div></div>
<?php echo $__env->make('audit._history', ['type' => 'Quotation', 'id' => $q->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/quotations/show.blade.php ENDPATH**/ ?>