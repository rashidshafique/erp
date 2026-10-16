
<div class="field" data-keep>
    <label for="sand_system">Made for sand system</label>
    <select id="sand_system" name="sand_system" style="max-width:260px">
        <option value="">Not recorded (suits any)</option>
        <?php $__currentLoopData = $sands; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sd): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($sd); ?>" <?php if(old('sand_system', $pattern->sand_system) === $sd): echo 'selected'; endif; ?>><?php echo e($sd); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
</div>
<div class="field">
    <label>Also works on</label>
    <div style="display:flex;gap:14px;flex-wrap:wrap">
        <?php $__currentLoopData = $sands; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sd): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <label style="font-weight:400;display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="compatible_sands[]" value="<?php echo e($sd); ?>" <?php if(in_array($sd, old('compatible_sands', $pattern->compatible_sands ?? []), true)): echo 'checked'; endif; ?>><?php echo e($sd); ?></label>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</div>
<div class="field field-full">
    <label for="sand_note">Sand note <span class="muted small">(taper allowance, why it does not suit another sand)</span></label>
    <input id="sand_note" name="sand_note" maxlength="1000" value="<?php echo e(old('sand_note', $pattern->sand_note)); ?>" style="max-width:520px">
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/patterns/_sand.blade.php ENDPATH**/ ?>