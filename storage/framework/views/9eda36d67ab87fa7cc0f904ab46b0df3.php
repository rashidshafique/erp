<?php $__env->startSection('title', 'Custom fields'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Custom fields</h1><p>Extra descriptive information for your records: drawing references, box sizes, special notes. Measured quality data (chemistry, tests) is not stored here.</p></div></div>
<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="alert alert-error"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
<div class="panel"><div class="panel-header"><h2>Add a field</h2></div><div class="panel-body"><form method="POST" action="<?php echo e(route('custom-fields.store')); ?>" class="form-grid"><?php echo csrf_field(); ?>
<label>Applies to<select name="entity_type"><?php $__currentLoopData = $entities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(old('entity_type')===$k): echo 'selected'; endif; ?>><?php echo e($e[0]); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
<label>Label<input name="label" value="<?php echo e(old('label')); ?>" required></label>
<label>Type<select name="data_type"><?php $__currentLoopData = $types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(old('data_type')===$k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
<label>Choices (for choice lists, one per line)<textarea name="options_text" rows="2"><?php echo e(old('options_text')); ?></textarea></label>
<label>Unit<input name="unit" value="<?php echo e(old('unit')); ?>" maxlength="20"></label><label>Min (numbers)<input type="number" step="any" name="min_value"></label><label>Max (numbers)<input type="number" step="any" name="max_value"></label>
<label>Help text<input name="help_text" maxlength="255"></label><label>Order<input type="number" name="sort_order" value="100"></label>
<label><input type="checkbox" name="is_required" value="1"> Required</label>
<div><button class="btn btn-primary">Add field</button></div></form></div></div>
<?php $__currentLoopData = $entities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php if(isset($defs[$key])): ?>
<div class="panel"><div class="panel-header"><h2><?php echo e($e[0]); ?></h2></div><div class="table-wrap"><table><thead><tr><th>Label</th><th>Key</th><th>Type</th><th>Choices</th><th>Unit</th><th>Req.</th><th>Order</th><th>Active</th><th></th></tr></thead><tbody>
<?php $__currentLoopData = $defs[$key]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<form method="POST" action="<?php echo e(route('custom-fields.update', $d)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
<tr><td><input name="label" value="<?php echo e($d->label); ?>"></td><td class="muted small"><?php echo e($d->field_key); ?></td><td><?php echo e($types[$d->data_type]); ?></td>
<td><textarea name="options_text" rows="1"><?php echo e(implode("\n", $d->options ?? [])); ?></textarea></td><td><input name="unit" value="<?php echo e($d->unit); ?>" size="6"></td>
<td><input type="checkbox" name="is_required" value="1" <?php if($d->is_required): echo 'checked'; endif; ?>></td><td><input type="number" name="sort_order" value="<?php echo e($d->sort_order); ?>" style="width:70px"></td>
<td><input type="checkbox" name="is_active" value="1" <?php if($d->is_active): echo 'checked'; endif; ?>></td><td><button class="btn">Save</button></td></tr></form>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div></div>
<?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/settings/custom-fields.blade.php ENDPATH**/ ?>