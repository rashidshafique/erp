
<div class="field" <?php if(! empty($keep)): ?> data-keep <?php endif; ?>><label for="f_<?php echo e($name); ?>"><?php echo e($label); ?><?php echo e(! empty($required) ? ' *' : ''); ?></label>
<select id="f_<?php echo e($name); ?>" name="<?php echo e($name); ?>" <?php if(! empty($required)): ?> required <?php endif; ?>>
    <?php if(($blank ?? '') !== false): ?><option value=""><?php echo e($blank ?? '—'); ?></option><?php endif; ?>
    <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if((string) old($name, $sel ?? '') === (string) $k): echo 'selected'; endif; ?>><?php echo e($t); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</select><?php $__errorArgs = [$name];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="error-text"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/_sel.blade.php ENDPATH**/ ?>