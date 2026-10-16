<?php
    $rows = \App\Models\CharacteristicLimit::where('subject_type', $type)->where('subject_id', $id)->orderBy('test_group')->orderBy('sort_order')->get();
    $groups = $type === 'SAND_SYSTEM' ? ['SAND' => 'Sand'] : ['MECHANICAL' => 'Mechanical', 'MICROSTRUCTURE' => 'Microstructure'];
?>
<div class="panel">
    <div class="panel-header"><h2><?php echo e($title ?? 'Test requirements'); ?></h2></div>
    <div class="panel-body">
        <?php if($errors->has('limit')): ?><div class="alert alert-error"><?php echo e($errors->first('limit')); ?></div><?php endif; ?>
        <p class="muted small">Minimum and maximum for each test. Tick "required" to stop a lot being released until a passing result exists for its heat. Results keep the limits they were judged against.</p>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>Group</th><th>Characteristic</th><th>Min</th><th>Max</th><th>Unit</th><th>Required for release</th><th></th></tr></thead>
        <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <form method="POST" action="<?php echo e(route('limits.update', $l)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
            <tr><td><?php echo e($l->test_group); ?></td><td><?php echo e($l->name); ?></td>
                <td><input type="number" step="any" name="min_value" value="<?php echo e($l->min_value); ?>" style="width:90px"></td>
                <td><input type="number" step="any" name="max_value" value="<?php echo e($l->max_value); ?>" style="width:90px"></td>
                <td><input name="unit" value="<?php echo e($l->unit); ?>" size="6"></td>
                <td><input type="checkbox" name="is_required_for_release" value="1" <?php if($l->is_required_for_release): echo 'checked'; endif; ?>></td>
                <td><button class="btn">Save</button></form>
                <form method="POST" action="<?php echo e(route('limits.destroy', $l)); ?>" style="display:inline"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-danger">Remove</button></form></td></tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="7" class="muted">No limits set.</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
    <div class="panel-body">
        <form method="POST" action="<?php echo e(route('limits.store')); ?>" class="form-grid"><?php echo csrf_field(); ?>
            <input type="hidden" name="subject_type" value="<?php echo e($type); ?>"><input type="hidden" name="subject_id" value="<?php echo e($id); ?>">
            <label>Group<select name="test_group"><?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>"><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
            <label>Characteristic name<input name="name" required placeholder="e.g. Hardness"></label>
            <label>Key (optional)<input name="characteristic_key" placeholder="hardness_hb"><span class="muted small">Use a key from the entry form to attach limits to it (for example hardness_hb). Leave empty for a new test.</span></label>
            <label>Min<input type="number" step="any" name="min_value"></label><label>Max<input type="number" step="any" name="max_value"></label><label>Unit<input name="unit"></label>
            <label><input type="checkbox" name="is_required_for_release" value="1"> Required for release</label>
            <div><button class="btn btn-primary">Add limit</button></div>
        </form>
    </div>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/limits/_panel.blade.php ENDPATH**/ ?>