<?php $__env->startSection('title', 'Sales orders'); ?>
<?php $__env->startSection('breadcrumb', 'Sales orders'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div><h1>Sales orders</h1><p>From customer order through contract review to production and dispatch.</p></div>
    <div class="actions"><a class="btn btn-primary" href="<?php echo e(route('sales-orders.create')); ?>">+ New sales order</a></div>
</div>

<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>

<div class="panel"><div class="panel-body">
    <form method="GET" class="filters">
        <div class="field"><label for="search">Order number</label><input id="search" name="search" value="<?php echo e(request('search')); ?>"></div>
        <div class="field"><label for="status">Status</label>
            <select id="status" name="status"><option value="">All</option>
                <?php $__currentLoopData = ['DRAFT','UNDER_REVIEW','CONFIRMED','IN_PRODUCTION','DISPATCHED','CANCELLED']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($s); ?>" <?php if(request('status') === $s): echo 'selected'; endif; ?>><?php echo e(str_replace('_', ' ', $s)); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select></div>
        <button class="btn btn-primary" type="submit">Filter</button>
        <a class="btn" href="<?php echo e(route('sales-orders.index')); ?>">Reset</a>
    </form>
</div></div>

<div class="panel">
    <?php if($orders->isEmpty()): ?>
        <div class="empty-state">No sales orders yet.</div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Order</th><th>Customer</th><th>Customer ref</th><th>Order date</th><th>Delivery</th><th>Lines</th><th>Status</th></tr></thead>
            <tbody>
            <?php $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><a href="<?php echo e(route('sales-orders.show', $o)); ?>"><strong><?php echo e($o->order_number); ?></strong></a></td>
                    <td><?php echo e($o->customer?->name); ?></td>
                    <td><?php echo e($o->customer_reference ?: '—'); ?></td>
                    <td><?php echo e($o->order_date?->format('d M Y')); ?></td>
                    <td><?php echo e($o->requested_delivery_date?->format('d M Y') ?? '—'); ?></td>
                    <td><?php echo e($o->lines_count); ?></td>
                    <td><span class="status"><?php echo e(str_replace('_', ' ', $o->status)); ?></span></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table></div>
        <div class="panel-body"><?php echo e($orders->links()); ?></div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/sales-orders/index.blade.php ENDPATH**/ ?>