<?php $__env->startSection('title', 'Casting lots'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Casting lots</h1><p>Every casting traced from pour to dispatch.</p></div></div>
<?php if(session("success")): ?><div class="alert alert-success"><?php echo e(session("success")); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="alert alert-error"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
<div class="panel"><div class="panel-body">
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">
<input name="search" value="<?php echo e(request('search')); ?>" placeholder="Lot number">
<select name="status"><option value="">All statuses</option><?php $__currentLoopData = ['IN_PRODUCTION','READY','PACKED','DISPATCHED','ON_HOLD','SCRAPPED','CLOSED']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s); ?>" <?php if(request('status')===$s): echo 'selected'; endif; ?>><?php echo e($s); ?> (<?php echo e($counts[$s] ?? 0); ?>)</option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
<button class="btn">Filter</button></form></div>
<div class="table-wrap"><table><thead><tr><th>Lot</th><th>Product</th><th>Heat</th><th>Order</th><th>Poured</th><th>Good</th><th>Rejected</th><th>Status</th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $lots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr><td><a href="<?php echo e(route('lots.show', $l)); ?>"><?php echo e($l->lot_number); ?></a></td><td><?php echo e($l->product?->code); ?></td><td><?php echo e($l->pourMouldAssembly?->pour?->heat?->heat_number ?? '—'); ?></td><td><?php echo e($l->productionOrder?->production_order_number ?? '—'); ?></td><td><?php echo e($l->quantity_poured); ?></td><td><?php echo e($l->quantity_good); ?></td><td><?php echo e($l->quantity_rejected); ?></td><td><span class="status"><?php echo e($l->status); ?></span></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="8" class="muted">No lots yet. Lots are created when a pour with assigned moulds is completed.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="panel-body"><?php echo e($lots->links()); ?></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/lots/index.blade.php ENDPATH**/ ?>