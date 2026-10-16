<?php $__env->startSection('title', 'Scan or find'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Scan or find</h1><p>Scan a barcode or type a code: a product, pattern, tooling code or engraved mark, a lot, heat, pour or order number.</p></div></div>
<div class="panel"><div class="panel-body">
<form method="GET" action="<?php echo e(route('scan')); ?>" style="display:flex;gap:8px;max-width:520px"><input name="code" value="<?php echo e($code ?? ''); ?>" placeholder="Scan or type a code" autofocus autocomplete="off"><button class="btn btn-primary">Find</button></form>
<?php if(isset($missing)): ?><p class="error-text">Nothing has the code “<?php echo e($code); ?>”.</p><?php endif; ?>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/scan.blade.php ENDPATH**/ ?>