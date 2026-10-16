<?php
    $cf = app(\App\Services\CustomFields\CustomFields::class);
    $cfDefs = $cf->definitions($entity);
    $cfVals = $cfDefs->isEmpty() ? [] : $cf->values($entity, $id);
?>
<div class="panel">
    <div class="panel-header"><h2>Additional information</h2>
        <a class="small" href="<?php echo e(route('custom-fields.index')); ?>">Manage fields</a></div>
    <div class="panel-body">
    <?php if($cfDefs->isEmpty()): ?>
        <p class="muted small">No additional fields are defined for <?php echo e(strtolower(\App\Services\CustomFields\CustomFields::ENTITIES[$entity][0])); ?> records. Add them under Administration → Custom fields.</p>
    <?php else: ?>
        <form method="POST" action="<?php echo e(route('custom-fields.values', [$entity, $id])); ?>" class="form-grid"><?php echo csrf_field(); ?>
            <?php $__currentLoopData = $cfDefs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php $v = old('cf.'.$d->field_key, $cfVals[$d->field_key] ?? null); ?>
                <label><?php echo e($d->label); ?><?php if($d->unit): ?> (<?php echo e($d->unit); ?>)<?php endif; ?> <?php if($d->is_required): ?>*<?php endif; ?>
                    <?php if($d->data_type === 'SELECT'): ?>
                        <select name="cf[<?php echo e($d->field_key); ?>]"><option value="">—</option><?php $__currentLoopData = ($d->options ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option <?php if($v === $o): echo 'selected'; endif; ?>><?php echo e($o); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
                    <?php elseif($d->data_type === 'BOOLEAN'): ?>
                        <select name="cf[<?php echo e($d->field_key); ?>]"><option value="">—</option><option value="1" <?php if($v === true || $v === '1'): echo 'selected'; endif; ?>>Yes</option><option value="0" <?php if($v === false || $v === '0'): echo 'selected'; endif; ?>>No</option></select>
                    <?php elseif($d->data_type === 'DATE'): ?>
                        <input type="date" name="cf[<?php echo e($d->field_key); ?>]" value="<?php echo e($v); ?>">
                    <?php elseif($d->data_type === 'NUMBER'): ?>
                        <input type="number" step="any" name="cf[<?php echo e($d->field_key); ?>]" value="<?php echo e($v); ?>" <?php if($d->min_value !== null): ?> min="<?php echo e($d->min_value); ?>" <?php endif; ?> <?php if($d->max_value !== null): ?> max="<?php echo e($d->max_value); ?>" <?php endif; ?>>
                    <?php else: ?>
                        <input name="cf[<?php echo e($d->field_key); ?>]" value="<?php echo e($v); ?>" maxlength="500">
                    <?php endif; ?>
                    <?php if($d->help_text): ?><span class="muted small"><?php echo e($d->help_text); ?></span><?php endif; ?>
                    <?php $__errorArgs = ['cf.'.$d->field_key];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span style="color:var(--danger)"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </label>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <div><button class="btn btn-primary">Save</button></div>
        </form>
    <?php endif; ?>
    </div>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/custom-fields/_panel.blade.php ENDPATH**/ ?>