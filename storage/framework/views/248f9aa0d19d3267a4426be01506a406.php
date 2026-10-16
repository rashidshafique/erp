<?php $__env->startSection('title', $order->order_number); ?>
<?php $__env->startSection('breadcrumb', 'Sales orders / '.$order->order_number); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div><h1><?php echo e($order->order_number); ?></h1><p><?php echo e($order->customer?->code); ?> — <?php echo e($order->customer?->name); ?></p></div>
    <div class="actions">
        <a class="btn" href="<?php echo e(route('sales-orders.index')); ?>">All orders</a>
        <a class="btn" href="<?php echo e(route('sales-orders.trace', $order)); ?>">Trace to dispatch</a>
        <form method="POST" action="<?php echo e(route('sales-orders.review', $order)); ?>" style="display:inline"><?php echo csrf_field(); ?>
            <button class="btn btn-primary" type="submit"><?php echo e($review ? 'Re-run contract review' : 'Run contract review'); ?></button></form>
    </div>
</div>

<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="alert alert-error"><ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div><?php endif; ?>

<div class="metrics">
    <div class="metric"><div class="metric-label">Status</div><div class="metric-value" style="font-size:21px"><?php echo e(str_replace('_', ' ', $order->status)); ?></div></div>
    <div class="metric"><div class="metric-label">Order date</div><div class="metric-value" style="font-size:21px"><?php echo e($order->order_date?->format('d M Y')); ?></div></div>
    <div class="metric"><div class="metric-label">Requested delivery</div><div class="metric-value" style="font-size:21px"><?php echo e($order->requested_delivery_date?->format('d M Y') ?? '—'); ?></div></div>
    <div class="metric"><div class="metric-label">Customer reference</div><div class="metric-value" style="font-size:21px"><?php echo e($order->customer_reference ?: '—'); ?></div></div>
</div>

<div class="tab-marker" data-tab-start="Lines"></div>
<div class="panel">
    <div class="panel-header"><h2>Lines</h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>Product</th><th>Rev</th><th>Ordered</th><th>Scheduled</th><th>Shipped</th><th>Production orders</th><th></th></tr></thead>
        <tbody>
        <?php $__currentLoopData = $order->lines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php $sched = (float) $l->productionOrders->where('status', '!=', 'CANCELLED')->sum('planned_qty'); ?>
            <tr>
                <td><?php echo e($l->line_number); ?></td>
                <td><strong><?php echo e($l->product?->code); ?></strong> <?php echo e($l->product?->name); ?></td>
                <td><?php echo e($l->productRevision?->revision); ?></td>
                <td><?php echo e(rtrim(rtrim(number_format((float) $l->ordered_qty, 3), '0'), '.')); ?></td>
                <td><?php echo e(rtrim(rtrim(number_format($sched, 3), '0'), '.')); ?></td>
                <td><?php echo e(rtrim(rtrim(number_format((float) ($shipped[$l->id] ?? 0), 3), '0'), '.') ?: '0'); ?></td>
                <td><?php echo e($l->productionOrders->pluck('production_order_number')->implode(', ') ?: '—'); ?></td>
                <td>
                    <?php if($sched < (float) $l->ordered_qty - 0.0005): ?>
                        <form method="POST" action="<?php echo e(route('sales-orders.lines.production-order', [$order, $l])); ?>"><?php echo csrf_field(); ?>
                            <button class="btn" type="submit" <?php if(! $order->hasApprovedReview()): echo 'disabled'; endif; ?>>Create production order</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table></div>
    <?php if (! ($order->hasApprovedReview())): ?><div class="panel-body muted small">Production orders can be created once the contract review is approved by both sales and quality.</div><?php endif; ?>
</div>

<div class="tab-marker" data-tab-start="Contract review"></div>
<div class="panel">
    <div class="panel-header">
        <h2>Contract review<?php echo e($review ? ' '.$review->review_number : ''); ?></h2>
        <?php if($review): ?><span class="status <?php echo e($review->status === 'APPROVED' ? 'status-completed' : ($review->verdict === 'BLOCK' ? 'status-cancelled' : 'status-on-hold')); ?>"><?php echo e($review->status); ?> · <?php echo e($review->verdict); ?></span><?php endif; ?>
    </div>
    <?php if(! $review): ?>
        <div class="empty-state">No review yet. Run the contract review to check the order against specifications, customer requirements, grade, furnaces, thermal window, pattern and control plan.</div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Line</th><th>Check</th><th>Result</th><th>Finding</th><th>Acknowledgement</th></tr></thead>
            <tbody>
            <?php $__currentLoopData = $review->items->sortBy(fn ($i) => [$i->line?->line_number ?? 0, ['BLOCK' => 0, 'WARN' => 1, 'PASS' => 2][$i->severity]]); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($i->line?->line_number ?? '—'); ?></td>
                    <td><?php echo e(str_replace('_', ' ', $i->check_code)); ?></td>
                    <td><span class="status <?php echo e($i->severity === 'PASS' ? 'status-completed' : ($i->severity === 'WARN' ? 'status-on-hold' : 'status-cancelled')); ?>"><?php echo e($i->severity); ?></span></td>
                    <td style="white-space:normal;min-width:260px"><?php echo e($i->message); ?></td>
                    <td style="white-space:normal;min-width:220px">
                        <?php if($i->severity === 'WARN'): ?>
                            <?php if($i->acknowledgement): ?> <?php echo e($i->acknowledgement); ?>

                            <?php elseif($review->status === 'IN_REVIEW'): ?>
                                <form method="POST" action="<?php echo e(route('sales-orders.review.acknowledge', $order)); ?>"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="item_id" value="<?php echo e($i->id); ?>">
                                    <input name="reason" placeholder="Why this is acceptable" required minlength="5" maxlength="500">
                                    <button class="btn" type="submit">Acknowledge</button></form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table></div>

        <div class="panel-body">
            <div class="form-grid">
                <?php $__currentLoopData = ['sales' => 'Sales', 'quality' => 'Quality']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="field">
                        <strong><?php echo e($label); ?> sign-off</strong>
                        <?php if($review->{$role.'_signed_at'}): ?>
                            <div><?php echo e($review->{$role.'_signed_by'}); ?> · <?php echo e($review->{$role.'_signed_at'}->format('d M Y H:i')); ?></div>
                        <?php elseif($review->status === 'IN_REVIEW'): ?>
                            <form method="POST" action="<?php echo e(route('sales-orders.review.sign', $order)); ?>"><?php echo csrf_field(); ?>
                                <input type="hidden" name="role" value="<?php echo e($role); ?>">
                                <input name="name" placeholder="Your name" required maxlength="100">
                                <button class="btn btn-primary" type="submit">Sign as <?php echo e(strtolower($label)); ?></button></form>
                        <?php else: ?>
                            <div class="muted small">Not signed.</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<div class="tab-marker" data-tab-start="Files & history"></div>
<?php echo $__env->make('custom-fields._panel', ['entity' => 'sales_order', 'id' => $order->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('attachments._panel', ['type' => 'sales_order', 'id' => $order->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('audit._history', ['type' => 'SalesOrder', 'id' => $order->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/sales-orders/show.blade.php ENDPATH**/ ?>