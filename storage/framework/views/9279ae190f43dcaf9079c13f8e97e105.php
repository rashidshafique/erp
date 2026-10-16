<?php $__env->startSection('title', 'Pack lots'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Pack lots</h1><p>Only READY lots with good, unpacked pieces appear here.</p></div></div>
<?php if(session("success")): ?><div class="alert alert-success"><?php echo e(session("success")); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="alert alert-error"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
<form method="POST" action="<?php echo e(route('packing.store')); ?>"><?php echo csrf_field(); ?>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Lot</th><th>Product</th><th>Good</th><th>Left to pack</th><th>Pack now</th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $lots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><?php echo e($l->lot_number); ?></td><td><?php echo e($l->product?->code); ?></td><td><?php echo e($l->quantity_good); ?></td><td><?php echo e($l->packable); ?></td><td><input type="number" name="qty[<?php echo e($l->id); ?>]" min="0" max="<?php echo e($l->packable); ?>" value="<?php echo e(old('qty.'.$l->id, 0)); ?>"></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5" class="muted">No lot is ready for packing.</td></tr><?php endif; ?></tbody></table></div>
<div class="panel-body form-grid"><label>Packed by<input name="packed_by"></label><label>Checked by<input name="checked_by"></label><label>Package type<input name="package_type" placeholder="pallet, crate"></label><label>Store at (rack / bin)<input name="storage_location" maxlength="60"></label><label>Packages<input type="number" name="package_count" min="1" value="1"></label><label>Notes<input name="packaging_notes"></label>
<div><button class="btn btn-primary">Create packing record</button></div></div></div></form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/packing/create.blade.php ENDPATH**/ ?>