<?php $__env->startSection('title', 'New customer'); ?>
<?php $__env->startSection('breadcrumb', 'Customers / New'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>New customer</h1><p>Create the account before entering the first order.</p></div></div>

<?php if($errors->any()): ?>
    <div class="alert alert-error"><ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div>
<?php endif; ?>

<form method="POST" action="<?php echo e(route('customers.store')); ?>">
<?php echo csrf_field(); ?>
<div class="panel"><div class="panel-body"><div class="form-grid" data-progressive="3">
    <div class="field"><label for="code">Code *</label><input id="code" name="code" required maxlength="40" value="<?php echo e(old('code')); ?>"></div>
    <div class="field"><label for="name">Name *</label><input id="name" name="name" required maxlength="200" value="<?php echo e(old('name')); ?>"></div>
    <div class="field"><label for="short_name">Short name</label><input id="short_name" name="short_name" maxlength="60" value="<?php echo e(old('short_name')); ?>"></div>
    <div class="field"><label for="contact_name">Contact</label><input id="contact_name" name="contact_name" maxlength="120" value="<?php echo e(old('contact_name')); ?>"></div>
    <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" maxlength="40" value="<?php echo e(old('phone')); ?>"></div>
    <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="160" value="<?php echo e(old('email')); ?>"></div>
    <div class="field"><label for="tax_number">Tax number</label><input id="tax_number" name="tax_number" maxlength="60" value="<?php echo e(old('tax_number')); ?>"></div>
    <div class="field"><label for="payment_terms">Payment terms</label><input id="payment_terms" name="payment_terms" maxlength="100" value="<?php echo e(old('payment_terms')); ?>"></div>
    <div class="field field-full"><label for="billing_address">Billing address</label><textarea id="billing_address" name="billing_address" rows="2"><?php echo e(old('billing_address')); ?></textarea></div>
    <div class="field field-full"><label for="shipping_address">Shipping address</label><textarea id="shipping_address" name="shipping_address" rows="2"><?php echo e(old('shipping_address')); ?></textarea></div>
</div></div></div>
<button class="btn btn-primary" type="submit">Save customer</button>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/customers/create.blade.php ENDPATH**/ ?>