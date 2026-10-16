<?php $__env->startSection('title', 'Production queue'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Production queue</h1><p>Where each open lot stands after the pour. Oldest first within each step.</p></div><div class="actions"><a class="btn" href="<?php echo e(route('finished-goods.index')); ?>">Finished goods (<?php echo e($awaitingDispatch); ?> packed lines)</a></div></div>
<?php $__empty_1 = true; $__currentLoopData = $buckets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<div class="panel"><div class="panel-header"><h2><?php echo e($b['label']); ?></h2><span class="muted"><?php echo e(count($b['lots'])); ?> lot(s)</span></div>
<div class="table-wrap"><table><thead><tr><th>Lot</th><th>Product</th><th>Order</th><th>Pieces</th><th>Poured</th><th>Age</th><th>Cooling</th><th>Status</th></tr></thead><tbody>
<?php $__currentLoopData = $b['lots']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php $l = $r['lot']; ?>
<tr><td><a href="<?php echo e(route('lots.show', $l)); ?>"><?php echo e($l->lot_number); ?></a></td><td><?php echo e($l->product?->code); ?></td><td><?php echo e($l->productionOrder?->production_order_number); ?><?php if($r['urgent']): ?><div class="muted small">required <?php echo e($r['urgent']->format('d M')); ?></div><?php endif; ?></td>
<td><?php echo e($r['pieces']); ?></td><td><?php echo e($r['poured']?->format('d M H:i') ?? '—'); ?></td><td><?php echo e($r['age_h'] !== null ? $r['age_h'].' h' : '—'); ?></td>
<td><?php if($r['cool']): ?><?php if($r['cool']['wait'] > 0): ?><span class="status status-in-progress">wait <?php echo e($r['cool']['wait']); ?> min</span><?php else: ?> <span class="muted small">met</span><?php endif; ?> <?php endif; ?></td>
<td><?php echo e($l->status); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="panel"><div class="panel-body muted">No open lots.</div></div><?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/lots/queue.blade.php ENDPATH**/ ?>