<?php $__env->startSection('title', 'Not allowed'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Not allowed</h1><p><?php echo e($why ?? 'You do not have access to this.'); ?></p></div></div>
<div class="panel"><div class="panel-body">Ask an administrator to change your role if you need this. <a href="<?php echo e(route('dashboard')); ?>">Back to dashboard</a></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/errors/403.blade.php ENDPATH**/ ?>