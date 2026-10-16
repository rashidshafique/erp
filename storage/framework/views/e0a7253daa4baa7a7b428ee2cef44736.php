<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quotation <?php echo e($q->code); ?></title>
<style>
body { font: 14px/1.45 system-ui, sans-serif; color: #111; max-width: 800px; margin: 24px auto; padding: 0 16px; }
h1 { font-size: 22px; margin: 0 0 4px; } table { border-collapse: collapse; width: 100%; margin: 14px 0; } th, td { border: 1px solid #bbb; padding: 6px 8px; text-align: left; vertical-align: top; } .num { text-align: right; }
.muted { color: #555; } .flag { border: 1px solid #b00; color: #b00; padding: 6px 10px; margin: 10px 0; } @media print { .noprint { display: none; } }
</style></head><body>
<?php
    $n = fn ($v) => number_format((float) $v, 2);
    $brand = \App\Support\UiSettings::brand()['name'];
    $total = $q->lines->sum(fn ($l) => $l->unit_price * $l->qty);
?>
<p class="noprint"><a href="#" onclick="window.print();return false">Print</a></p>
<h1>Quotation <?php echo e($q->code); ?></h1>
<div class="muted"><?php echo e($brand); ?> · Version <?php echo e($q->version); ?> · Issued <?php echo e($q->issued_at?->format('d M Y')); ?></div>
<?php if(in_array($q->status, ['EXPIRED', 'SUPERSEDED', 'REJECTED'], true)): ?><div class="flag">This quotation is no longer open (<?php echo e(strtolower($q->status)); ?>).</div><?php endif; ?>
<p><strong><?php echo e($q->enquiry->customer?->name); ?></strong><?php if($q->enquiry->contact_name): ?><br>Attention: <?php echo e($q->enquiry->contact_name); ?><?php endif; ?> <?php if($q->enquiry->customer_reference): ?><br>Your reference: <?php echo e($q->enquiry->customer_reference); ?><?php endif; ?></p>
<table><thead><tr><th>#</th><th>Description</th><th>Drawing</th><th class="num">Quantity</th><th class="num">Unit price (<?php echo e($q->currency); ?>)</th><th class="num">Amount</th></tr></thead><tbody>
<?php $__currentLoopData = $q->lines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><td><?php echo e($l->line_no); ?></td><td><?php echo e($l->item->description); ?><?php if($l->item->material_text): ?><div class="muted"><?php echo e($l->item->material_text); ?></div><?php endif; ?></td><td><?php echo e($l->item->drawing_ref); ?><?php echo e($l->item->drawing_rev ? ' rev '.$l->item->drawing_rev : ''); ?></td><td class="num"><?php echo e($l->qty); ?></td><td class="num"><?php echo e($n($l->unit_price)); ?></td><td class="num"><?php echo e($n($l->unit_price * $l->qty)); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody><tfoot><tr><th colspan="5" class="num">Total</th><th class="num"><?php echo e($n($total)); ?></th></tr></tfoot></table>
<?php if($q->tooling_mode === 'UPFRONT'): ?><p>Tooling charge, invoiced separately: <strong><?php echo e($q->currency); ?> <?php echo e($n($q->upfrontToolingPrice())); ?></strong>.</p>
<?php elseif($q->tooling_mode === 'AMORTISED'): ?><p>Tooling cost is included in the unit price, spread over <?php echo e($q->tooling_amortise_qty); ?> castings.</p><?php endif; ?>
<p>Valid until <strong><?php echo e($q->valid_until?->format('d M Y')); ?></strong>. Delivery: <?php echo e($q->delivery_weeks ? $q->delivery_weeks.' weeks from order' : 'to be agreed'); ?>. Payment: <?php echo e($q->payment_terms ?: 'to be agreed'); ?>.</p>
<?php if($q->notes): ?><p><?php echo e($q->notes); ?></p><?php endif; ?>
<?php $conds = $q->lines->filter(fn ($l) => $l->item->feasibility_status === 'CONDITIONAL' && $l->item->feasibility_notes); ?>
<?php if($conds->isNotEmpty()): ?><p><strong>Conditions</strong></p><ul><?php $__currentLoopData = $conds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li>Item <?php echo e($l->line_no); ?>: <?php echo e($l->item->feasibility_notes); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul><?php endif; ?>
<p class="muted">Prices are per casting, as cast and fettled unless stated. Production starts after the first samples are approved.</p>
</body></html>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/quotations/print.blade.php ENDPATH**/ ?>