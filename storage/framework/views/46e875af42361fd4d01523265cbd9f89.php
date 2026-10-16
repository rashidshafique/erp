<?php $__env->startSection('title', 'New employee'); ?>
<?php $__env->startSection('breadcrumb'); ?><a href="<?php echo e(route('hr.employees.index')); ?>">Employees</a> / New <?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>New employee</h1><p>Add the person and where they sit. The joining checklist is created for you.</p></div>
<div class="actions"><a class="btn" href="<?php echo e(route('hr.employees.index')); ?>">Cancel</a></div></div>
<form method="POST" action="<?php echo e(route('hr.employees.store')); ?>"><?php echo csrf_field(); ?>
    <?php echo $__env->make('hr.employees._fields', ['e' => null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <p><button class="btn btn-primary" type="submit">Add employee</button> <a class="btn" href="<?php echo e(route('hr.employees.index')); ?>">Cancel</a></p>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/employees/create.blade.php ENDPATH**/ ?>