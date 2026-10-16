<?php $__env->startSection('title', 'Nonconformance reports'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Nonconformance reports</h1><p>Open NCRs block dispatch of the lot.</p></div></div>
<?php if(session("success")): ?><div class="alert alert-success"><?php echo e(session("success")); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="alert alert-error"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>NCR</th><th>Lot</th><th>Title</th><th>Severity</th><th>Qty</th><th>Status</th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $ncrs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><a href="<?php echo e(route('ncrs.show', $n)); ?>"><?php echo e($n->ncr_number); ?></a></td><td><?php echo e($n->lot?->lot_number); ?></td><td><?php echo e($n->title); ?></td><td><?php echo e($n->severity); ?></td><td><?php echo e($n->quantity_affected); ?></td><td><?php echo e($n->status); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="6" class="muted">No NCRs.</td></tr><?php endif; ?></tbody></table></div><div class="panel-body"><?php echo e($ncrs->links()); ?></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/ncrs/index.blade.php ENDPATH**/ ?>