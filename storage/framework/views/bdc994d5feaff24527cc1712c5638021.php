<?php $__env->startSection('title', 'New enquiry'); ?>
<?php $__env->startSection('breadcrumb'); ?><a href="<?php echo e(route('enquiries.index')); ?>">Enquiries</a> / New <?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>New enquiry</h1><p>Who is asking, and what kind of request is it. You add the parts on the next screen.</p></div></div>
<form method="POST" action="<?php echo e(route('enquiries.store')); ?>"><?php echo csrf_field(); ?>
<div class="panel"><div class="panel-body"><div class="form-grid" data-progressive="3">
    <div class="field"><label for="customer_id">Customer</label><select id="customer_id" name="customer_id" required><option value="">— choose —</option><?php $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($c->id); ?>" <?php if(old('customer_id') === $c->id): echo 'selected'; endif; ?>><?php echo e($c->code); ?> — <?php echo e($c->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <div class="field"><label for="title">Title</label><input id="title" name="title" value="<?php echo e(old('title')); ?>" required placeholder="e.g. Pump housing, 200 a year"></div>
    <div class="field field-full"><label for="source_type">What are they giving us?</label><select id="source_type" name="source_type"><?php $__currentLoopData = \App\Models\Enquiry::SOURCES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(old('source_type', 'DRAWING') === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
        <div class="help">Repeat: a part already in the system. Drawing or CAD: new work from a model. Customer tool: they send a pattern or core box. Transfer: moving from another foundry. Change: a revision of a part we make. Concept: no drawing yet.</div></div>
    <div class="field"><label for="received_on">Received</label><input id="received_on" type="date" name="received_on" value="<?php echo e(old('received_on', now()->toDateString())); ?>"></div>
    <div class="field"><label for="due_on">Quote needed by</label><input id="due_on" type="date" name="due_on" value="<?php echo e(old('due_on')); ?>"></div>
    <div class="field"><label for="customer_reference">Their reference</label><input id="customer_reference" name="customer_reference" value="<?php echo e(old('customer_reference')); ?>"></div>
    <div class="field"><label for="contact_name">Contact</label><input id="contact_name" name="contact_name" value="<?php echo e(old('contact_name')); ?>"></div>
    <div class="field field-full"><label for="summary">What they said</label><textarea id="summary" name="summary" rows="3"><?php echo e(old('summary')); ?></textarea></div>
</div></div></div>
<p><button class="btn btn-primary" type="submit">Create enquiry</button> <a class="btn" href="<?php echo e(route('enquiries.index')); ?>">Cancel</a></p>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/enquiries/create.blade.php ENDPATH**/ ?>