<?php $__env->startSection('title', 'Customers'); ?>
<?php $__env->startSection('breadcrumb', 'Customers'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div><h1>Customers</h1><p>Customer accounts for sales orders and dispatch.</p></div>
    <div class="actions"><a class="btn btn-primary" href="<?php echo e(route('customers.create')); ?>">+ New customer</a></div>
</div>

<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>

<div class="panel"><div class="panel-body">
    <form method="GET" class="filters">
        <div class="field"><label for="search">Search code or name</label><input id="search" name="search" value="<?php echo e(request('search')); ?>"></div>
        <button class="btn btn-primary" type="submit">Filter</button>
        <a class="btn" href="<?php echo e(route('customers.index')); ?>">Reset</a>
    </form>
</div></div>

<div class="panel">
    <?php if($customers->isEmpty()): ?>
        <div class="empty-state">No customers yet.</div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Code</th><th>Name</th><th>Contact</th><th>Payment terms</th><th>Orders</th><th>Status</th></tr></thead>
            <tbody>
            <?php $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><strong><?php echo e($c->code); ?></strong></td><td><?php echo e($c->name); ?></td>
                    <td><?php echo e($c->contact_name ?: '—'); ?><?php echo e($c->email ? ' · '.$c->email : ''); ?></td>
                    <td><?php echo e($c->payment_terms ?: '—'); ?></td>
                    <td><a href="<?php echo e(route('sales-orders.index', ['search' => ''])); ?>"><?php echo e($c->sales_orders_count); ?></a></td>
                    <td><?php echo e($c->active ? 'Active' : 'Inactive'); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table></div>
        <div class="panel-body"><?php echo e($customers->links()); ?></div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/customers/index.blade.php ENDPATH**/ ?>