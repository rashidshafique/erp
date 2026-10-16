<?php $__env->startSection('title', 'Codes and marking'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Codes and marking</h1><p>How product, pattern and tooling codes are generated, and how tooling is marked for engraving.</p></div></div>
<form method="POST" action="<?php echo e(route('settings.codes.update')); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
<div class="panel"><div class="panel-header"><h2>Item codes</h2></div><div class="panel-body">
<div class="form-grid">
<div class="field"><label>Style</label><select name="code_style"><?php $__currentLoopData = $styles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(\App\Services\Codes\CodeGenerator::codeStyle() === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
<div class="help">Letters and numbers: PRD-00012. Numbers only: the type digit then the number (100012). Letters only: PRDAAAAB.</div></div>
<div class="field"><label>Digits in the running number</label><input type="number" name="code_digits" min="3" max="8" value="<?php echo e(\App\Services\Codes\CodeGenerator::codeDigits()); ?>"></div>
</div>
<table style="margin-top:10px"><thead><tr><th>Type</th><th>Prefix (letters)</th><th>Next code</th></tr></thead><tbody>
<?php $__currentLoopData = $kinds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $meta): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($meta[3]); ?></td><td><input name="prefix[<?php echo e($k); ?>]" maxlength="5" value="<?php echo e(\App\Services\Codes\CodeGenerator::prefix($k)); ?>" style="width:90px;text-transform:uppercase"></td><td><code><?php echo e($previews[$k]); ?></code></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table>
<p class="help">A code you type yourself is always accepted if it is not already used. Blank means generate. Prefixes are used by the letters styles; numbers only uses a type digit instead.</p>
</div></div>
<div class="panel"><div class="panel-header"><h2>Engraving marks for tooling</h2></div><div class="panel-body">
<div class="form-grid">
<div class="field"><label>Characters</label><select name="mark_style"><?php $__currentLoopData = $styles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(\App\Services\Codes\CodeGenerator::markStyle() === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
<div class="help">Choose numbers only if you engrave with a number die set.</div></div>
<div class="field"><label>Length</label><input type="number" name="mark_length" min="3" max="10" value="<?php echo e(\App\Services\Codes\CodeGenerator::markLength()); ?>"><div class="help">Grows by one character automatically if every combination is ever used.</div></div>
<div class="field field-full"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="mark_avoid_lookalikes" value="1" <?php if(\App\Services\Codes\CodeGenerator::setting('mark_avoid_lookalikes', '1') === '1'): echo 'checked'; endif; ?>> Leave out look-alike characters (0 and O, 1 and I, Q)</label><div class="help">Applies to letters and to letters-and-numbers. Numbers only always uses 0–9.</div></div>
</div>
<p>Next mark: <code style="font-size:16px"><?php echo e($nextMark); ?></code> <span class="muted small">Marks are issued in order and never reused, and no two tools can share one.</span></p>
</div></div>
<p><button class="btn btn-primary">Save</button></p></form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/settings/codes.blade.php ENDPATH**/ ?>