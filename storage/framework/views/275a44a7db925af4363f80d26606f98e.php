<?php $__env->startSection('title', 'Quotation rates'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Quotation rates</h1><p>The costs behind every quotation, in <?php echo e($currency); ?>.</p></div></div>
<div class="alert alert-error">The figures shown first time are placeholders to show the screen working. Replace every one with your own costs before issuing a real quotation.</div>
<form method="POST" action="<?php echo e(route('settings.quotation.update')); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
<div class="panel"><div class="panel-body"><div class="form-grid">
<?php $__currentLoopData = $labels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => [$label, $help]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div class="field"><label for="r_<?php echo e($k); ?>"><?php echo e($label); ?></label><input id="r_<?php echo e($k); ?>" type="number" step="0.01" min="0" name="<?php echo e($k); ?>" value="<?php echo e(old($k, $rates[$k])); ?>" required><?php if($help): ?><div class="help"><?php echo e($help); ?></div><?php endif; ?></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div></div></div>
<p><button class="btn btn-primary" type="submit">Save rates</button></p></form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/settings/quotation.blade.php ENDPATH**/ ?>