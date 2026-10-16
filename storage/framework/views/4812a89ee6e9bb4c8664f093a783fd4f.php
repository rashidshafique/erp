<div class="card" style="padding-bottom:6px">
    <div class="chead"><div><h2>Priority production queue</h2><p class="sub">Open orders, earliest required date first.</p></div><a class="btnx" href="<?php echo e(route('production-orders.index')); ?>">All orders</a></div>
    <?php if($d['queue']->isNotEmpty()): ?>
        <div style="overflow-x:auto"><table class="q">
            <thead><tr><th>Order</th><th>Casting</th><th style="text-align:right">Qty</th><th>Due</th><th>Status</th></tr></thead>
            <tbody>
            <?php $__currentLoopData = $d['queue']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php $late = $o->required_date && $o->required_date->lt(today()); ?>
                <tr>
                    <td class="mono"><a href="<?php echo e(route('production-orders.index', ['q' => $o->production_order_number])); ?>"><?php echo e($o->production_order_number); ?></a></td>
                    <td><div class="cell"><?php if($o->cover): ?><img class="thumb" src="<?php echo e(route('attachments.download', $o->cover)); ?>" alt=""><?php else: ?><span class="thumb"></span><?php endif; ?><div><strong><?php echo e($o->product?->name ?? '—'); ?></strong><div class="sub" style="font-size:12px;color:var(--dx-mute)"><?php echo e($o->salesOrderLine?->salesOrder?->customer?->name ?? $o->product?->code); ?></div></div></div></td>
                    <td class="mono" style="text-align:right"><?php echo e(number_format((float) $o->planned_qty)); ?><div style="font-size:11px;color:var(--dx-mute)"><?php echo e(number_format((float) $o->completed_qty)); ?> done</div></td>
                    <td class="mono" style="white-space:nowrap;<?php echo e($late ? 'color:#b91c1c;font-weight:650' : ''); ?>"><?php echo e($o->required_date?->format('d M') ?? '—'); ?></td>
                    <td><span class="pill <?php echo e($o->status === 'IN_PROGRESS' ? 'blue' : ($late ? 'red' : '')); ?>"><?php echo e(str_replace('_', ' ', $o->status)); ?></span></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table></div>
        <div style="padding:10px 2px 6px;font-size:12.5px;color:var(--dx-mute)">Showing <?php echo e($d['queue']->count()); ?> of <?php echo e($d['queueTotal']); ?> open orders.</div>
    <?php else: ?>
        <div class="empty">No open production orders.</div>
    <?php endif; ?>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/dashboard/_queue.blade.php ENDPATH**/ ?>