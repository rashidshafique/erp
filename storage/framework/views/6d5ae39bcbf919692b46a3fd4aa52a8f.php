<?php $__env->startSection('title', 'Trace '.$order->order_number); ?>
<?php $__env->startSection('breadcrumb', 'Sales orders / '.$order->order_number.' / Trace'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div><h1><?php echo e($order->order_number); ?> — order to dispatch</h1><p><?php echo e($order->customer?->name); ?> · contract review <?php echo e($review ? $review->status : 'not run'); ?></p></div>
    <div class="actions"><a class="btn" href="<?php echo e(route('sales-orders.show', $order)); ?>">Back to order</a></div>
</div>

<?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="panel">
        <div class="panel-header"><h2>Line <?php echo e($row['line']->line_number); ?> — <?php echo e($row['line']->product?->code); ?> × <?php echo e(rtrim(rtrim(number_format((float) $row['line']->ordered_qty, 3), '0'), '.')); ?></h2></div>
        <?php if(empty($row['orders'])): ?>
            <div class="empty-state">No production order yet.</div>
        <?php endif; ?>
        <?php $__currentLoopData = $row['orders']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="panel-body">
                <p><strong><?php echo e($o['po']->production_order_number); ?></strong> — <?php echo e($o['po']->status); ?>, planned <?php echo e(rtrim(rtrim(number_format((float) $o['po']->planned_qty, 3), '0'), '.')); ?>,
                    completed <?php echo e(rtrim(rtrim(number_format((float) $o['po']->completed_qty, 3), '0'), '.')); ?>; <?php echo e($o['moulding_runs']); ?> moulding run(s); dispatched <?php echo e(rtrim(rtrim(number_format($o['dispatched'], 3), '0'), '.')); ?>.</p>
                <?php if($o['lots']->isEmpty()): ?>
                    <p class="muted small">No casting lot has been created from a pour yet.</p>
                <?php else: ?>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Lot</th><th>Heat</th><th>Grade</th><th>Good / poured</th><th>Dispatch evidence</th></tr></thead>
                        <tbody>
                        <?php $__currentLoopData = $o['lots']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $x): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td><strong><?php echo e($x['lot']->lot_number); ?></strong> (<?php echo e($x['lot']->status); ?>)</td>
                                <td><?php if($x['heat']): ?><a href="<?php echo e(route('heats.show', $x['heat'])); ?>"><?php echo e($x['heat']->heat_number); ?></a><?php else: ?> — <?php endif; ?></td>
                                <td><?php echo e($x['heat']?->ironGrade?->code ?? '—'); ?></td>
                                <td><?php echo e($x['lot']->quantity_good ?? '—'); ?> / <?php echo e($x['lot']->quantity_poured ?? '—'); ?></td>
                                <td style="white-space:normal;min-width:300px">
                                    <span class="status <?php echo e($x['readiness']['ok'] ? 'status-completed' : 'status-cancelled'); ?>"><?php echo e($x['readiness']['ok'] ? 'READY' : 'NOT READY'); ?></span>
                                    <?php $__currentLoopData = $x['readiness']['checks']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <div class="small"><?php echo e($c['ok'] === true ? '✓' : ($c['ok'] === false ? '✗' : '•')); ?> <?php echo e($c['label']); ?>: <?php echo e($c['detail']); ?></div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/sales-orders/trace.blade.php ENDPATH**/ ?>