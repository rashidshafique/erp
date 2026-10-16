
<div class="field" <?php if(! empty($keep)): ?> data-keep <?php endif; ?>><label for="f_<?php echo e($name); ?>"><?php echo e($label); ?><?php echo e(! empty($required) ? ' *' : ''); ?></label>
<input id="f_<?php echo e($name); ?>" name="<?php echo e($name); ?>" type="<?php echo e($type ?? 'text'); ?>" value="<?php echo e(old($name, $val ?? '')); ?>" <?php if(! empty($required)): ?> required <?php endif; ?> <?php if(! empty($ph)): ?> placeholder="<?php echo e($ph); ?>" <?php endif; ?> <?php if(($type ?? '') === 'number'): ?> step="any" <?php endif; ?>>
<?php if(! empty($help)): ?><div class="help"><?php echo e($help); ?></div><?php endif; ?> <?php $__errorArgs = [$name];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="error-text"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/_in.blade.php ENDPATH**/ ?>