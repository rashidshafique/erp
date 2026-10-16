<div class="card">
    <div class="chead"><div><h2>Needs attention</h2><p class="sub">Deviations and sign-offs waiting on someone.</p></div><?php if(count($d['attention'])): ?><span class="pill red"><?php echo e(count($d['attention'])); ?> item<?php echo e(count($d['attention']) === 1 ? '' : 's'); ?></span><?php endif; ?></div>
    <?php $__empty_1 = true; $__currentLoopData = $d['attention']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="alert-item <?php echo e($a['tone'] === 'red' ? 'red' : ''); ?>">
            <div class="meta"><span class="pill <?php echo e($a['tone']); ?>"><?php echo e($a['tag']); ?></span><span class="mono"><?php echo e($a['ref'] ? $a['ref'].' · ' : ''); ?><?php echo e(\Illuminate\Support\Carbon::parse($a['at'])->diffForHumans(null, true, true)); ?></span></div>
            <p><?php echo e($a['text']); ?></p>
            <div class="acts"><?php $__currentLoopData = $a['actions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $url, $primary]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a class="btnx <?php echo e($primary ? 'primary' : ''); ?>" href="<?php echo e($url); ?>"><?php echo e($label); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="empty">Nothing needs attention right now.</div>
    <?php endif; ?>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/dashboard/_attention.blade.php ENDPATH**/ ?>