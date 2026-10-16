<?php $k = $d['kpis']; $fmt = fn ($n, $dp = 0) => number_format((float) $n, $dp); ?>
<div class="kpis">
    <a class="kpi" href="<?php echo e(route('enquiries.index')); ?>">
        <div class="top"><span class="cap">Open enquiries</span><span class="pill blue">Active</span></div>
        <div class="val mono"><?php echo e($fmt($k['enquiries']['open'])); ?></div>
        <div class="note"><?php echo e($k['enquiries']['quotes']); ?> quotation<?php echo e($k['enquiries']['quotes'] === 1 ? '' : 's'); ?> issued</div>
        <div class="foot"><span>+<?php echo e($k['enquiries']['today']); ?> today</span><?php echo $__env->make('dashboard._spark', ['vals' => $k['enquiries']['spark']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
    </a>
    <a class="kpi" href="<?php echo e(route('production-orders.index')); ?>">
        <div class="top"><span class="cap">Orders due (7 days)</span><?php if($k['due']['overdue']): ?><span class="pill red"><?php echo e($k['due']['overdue']); ?> late</span><?php else: ?><span class="pill green">On track</span><?php endif; ?></div>
        <div class="val mono"><?php echo e($fmt($k['due']['count'])); ?> <small>orders</small></div>
        <div class="note"><?php echo e($fmt($k['due']['qty'])); ?> pieces planned</div>
    </a>
    <a class="kpi" href="<?php echo e(route('heats.index')); ?>">
        <div class="top"><span class="cap">Heats today</span><span class="pill blue"><?php echo e($k['heats']['running']); ?> running</span></div>
        <div class="val mono"><?php echo e($k['heats']['done']); ?> <small>/ <?php echo e($k['heats']['total']); ?></small></div>
        <div class="note"><?php echo e($fmt($k['heats']['tonnes'], 1)); ?> t tapped</div>
        <div class="bar" style="margin-top:8px"><i style="width:<?php echo e($k['heats']['total'] > 0 ? min(100, round(100 * $k['heats']['done'] / $k['heats']['total'])) : 0); ?>%"></i></div>
    </a>
    <a class="kpi" href="<?php echo e(route('reports.yield')); ?>">
        <div class="top"><span class="cap">Shop scrap (7 days)</span><?php if($k['scrap']['rate'] !== null): ?><span class="pill <?php echo e($k['scrap']['rate'] <= 3 ? 'green' : 'amber'); ?>"><?php echo e($k['scrap']['rate'] <= 3 ? '≤ 3% ok' : 'Above 3%'); ?></span><?php endif; ?></div>
        <div class="val mono" style="color:<?php echo e(($k['scrap']['rate'] ?? 0) > 3 ? '#b91c1c' : 'var(--accent)'); ?>"><?php echo e($k['scrap']['rate'] !== null ? $fmt($k['scrap']['rate'], 2).'%' : '—'); ?></div>
        <div class="foot"><span><?php if($k['scrap']['delta'] !== null): ?><?php echo e($k['scrap']['delta'] <= 0 ? '↓' : '↑'); ?> <?php echo e($fmt(abs($k['scrap']['delta']), 2)); ?>% vs last week <?php else: ?> No pours recorded <?php endif; ?></span><?php echo $__env->make('dashboard._spark', ['vals' => $k['scrap']['spark'], 'color' => '#ef4444'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
    </a>
    <a class="kpi" href="<?php echo e(route('ncrs.index')); ?>">
        <div class="top"><span class="cap">Open NCRs</span><?php if($k['ncrs']['major']): ?><span class="pill red"><?php echo e($k['ncrs']['major']); ?> major</span><?php endif; ?></div>
        <div class="val mono"><?php echo e($k['ncrs']['open']); ?> <small>reports</small></div>
        <div class="note"><?php echo e($k['ncrs']['major']); ?> major · <?php echo e($k['ncrs']['minor']); ?> minor</div>
    </a>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/dashboard/_kpis.blade.php ENDPATH**/ ?>