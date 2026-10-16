<?php $__env->startSection('title', 'New policy'); ?>
<?php $__env->startSection('breadcrumb'); ?><a href="<?php echo e(route('hr.policies.index')); ?>">HR policies</a> / New <?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>New policy</h1><p>Saved as a draft until you publish it.</p></div></div>
<form method="POST" action="<?php echo e(route('hr.policies.store')); ?>"><?php echo csrf_field(); ?>
<div class="panel"><div class="panel-body"><div class="form-grid">
    <?php echo $__env->make('hr._in', ['name' => 'title', 'label' => 'Title', 'val' => '', 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'category', 'label' => 'Category', 'items' => config('hr.policy_categories'), 'sel' => 'GENERAL', 'blank' => false, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'effective_on', 'label' => 'Effective from', 'type' => 'date', 'val' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="field"><label><input type="checkbox" name="requires_ack" value="1" checked> Everyone must acknowledge it</label></div>
    <div class="field field-full"><label for="f_body">Policy text</label><textarea id="f_body" name="body" rows="14"><?php echo e(old('body')); ?></textarea></div>
</div></div></div>
<p><button class="btn btn-primary">Save draft</button> <a class="btn" href="<?php echo e(route('hr.policies.index')); ?>">Cancel</a></p></form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/policies/create.blade.php ENDPATH**/ ?>