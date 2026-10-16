<?php $__env->startSection('title', 'Packing'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Packing</h1><p>Pack only lots that are cleared.</p></div><div class="actions"><a class="btn btn-primary" href="<?php echo e(route('packing.create')); ?>">+ Pack lots</a></div></div>
<?php if(session("success")): ?><div class="alert alert-success"><?php echo e(session("success")); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="alert alert-error"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Record</th><th>Packed</th><th>Lots</th><th>Net kg</th><th>Packed by</th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $records; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><a href="<?php echo e(route('packing.show', $r)); ?>"><?php echo e($r->packing_number); ?></a></td><td><?php echo e($r->packed_at?->format('d M H:i')); ?></td><td><?php echo e($r->items_count); ?></td><td><?php echo e($r->total_net_weight_kg); ?></td><td><?php echo e($r->packed_by); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5" class="muted">Nothing packed yet.</td></tr><?php endif; ?></tbody></table></div><div class="panel-body"><?php echo e($records->links()); ?></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/packing/index.blade.php ENDPATH**/ ?>