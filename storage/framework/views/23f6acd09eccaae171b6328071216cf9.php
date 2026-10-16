<?php $__env->startSection('title', 'New sales order'); ?>
<?php $__env->startSection('breadcrumb', 'Sales orders / New'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>New sales order</h1><p>Enter the customer's order. The contract review checks it against specifications, grades, furnaces and patterns.</p></div></div>

<?php if($errors->any()): ?>
    <div class="alert alert-error"><ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div>
<?php endif; ?>

<?php if($customers->isEmpty()): ?>
    <div class="alert alert-error">Create a customer first. <a href="<?php echo e(route('customers.create')); ?>">New customer</a></div>
<?php elseif($revisions->isEmpty()): ?>
    <div class="alert alert-error">No approved product revisions exist yet. Approve a product revision first.</div>
<?php else: ?>
<form method="POST" action="<?php echo e(route('sales-orders.store')); ?>">
<?php echo csrf_field(); ?>
<div class="panel"><div class="panel-header"><h2>Order</h2></div><div class="panel-body"><div class="form-grid" data-progressive="3">
    <div class="field"><label for="customer_id">Customer *</label>
        <select id="customer_id" name="customer_id" required><option value="">Select customer</option>
            <?php $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($c->id); ?>" <?php if(old('customer_id') === $c->id): echo 'selected'; endif; ?>><?php echo e($c->code); ?> — <?php echo e($c->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select></div>
    <div class="field"><label for="customer_reference">Customer PO / reference</label><input id="customer_reference" name="customer_reference" maxlength="100" value="<?php echo e(old('customer_reference')); ?>"></div>
    <div class="field"><label for="order_date">Order date *</label><input id="order_date" name="order_date" type="date" required value="<?php echo e(old('order_date', today()->toDateString())); ?>"></div>
    <div class="field"><label for="requested_delivery_date">Requested delivery</label><input id="requested_delivery_date" name="requested_delivery_date" type="date" value="<?php echo e(old('requested_delivery_date')); ?>"></div>
    <div class="field field-full"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2"><?php echo e(old('notes')); ?></textarea></div>
</div></div></div>

<div class="panel"><div class="panel-header"><h2>Lines</h2></div><div class="panel-body">
    <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>Product revision</th><th>Quantity</th><th>Unit price</th><th>Line delivery</th></tr></thead>
        <tbody>
        <?php for($i = 0; $i < 5; $i++): ?>
            <tr>
                <td><?php echo e($i + 1); ?></td>
                <td><select name="lines[<?php echo e($i); ?>][product_revision_id]" <?php if($i === 0): ?> required <?php endif; ?>>
                    <option value=""><?php echo e($i === 0 ? 'Select product' : '— none —'); ?></option>
                    <?php $__currentLoopData = $revisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($r->id); ?>" <?php if(old("lines.$i.product_revision_id") === $r->id): echo 'selected'; endif; ?>><?php echo e($r->product?->code); ?> rev <?php echo e($r->revision); ?> — <?php echo e($r->product?->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select></td>
                <td><input name="lines[<?php echo e($i); ?>][ordered_qty]" type="number" step="0.001" min="0.001" value="<?php echo e(old("lines.$i.ordered_qty")); ?>"></td>
                <td><input name="lines[<?php echo e($i); ?>][unit_price]" type="number" step="0.01" min="0" value="<?php echo e(old("lines.$i.unit_price")); ?>"></td>
                <td><input name="lines[<?php echo e($i); ?>][requested_delivery_date]" type="date" value="<?php echo e(old("lines.$i.requested_delivery_date")); ?>"></td>
            </tr>
        <?php endfor; ?>
        </tbody>
    </table></div>
    <p class="muted small">Leave unused lines empty.</p>
</div></div>
<button class="btn btn-primary" type="submit">Save order</button>
</form>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/sales-orders/create.blade.php ENDPATH**/ ?>