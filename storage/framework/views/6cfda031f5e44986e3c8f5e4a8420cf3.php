<?php $L = $layout; ?>
<div class="card hero-card">
    <div class="hero">
        <div>
            <span class="live"><i class="dot"></i>Live</span>
            <h1>Foundry operations overview</h1>
            <p>Melt shop, moulding, quality and the order book on one page. Everything here is read from the live records.</p>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;align-items:flex-end">
            <div class="switch" title="Choose a dashboard layout">
                <?php $__currentLoopData = ['classic' => 'Classic', 'command' => 'Command', 'columns' => 'Columns']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e(route('dashboard', ['layout' => $k])); ?>" class="<?php echo e($L === $k ? 'on' : ''); ?>"><?php echo e($name); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <div class="acts">
                <a class="btnx" href="<?php echo e(route('scan')); ?>">Scan code</a>
                <a class="btnx" href="<?php echo e(route('heats.create')); ?>">Plan a heat</a>
                <a class="btnx primary" href="<?php echo e(route('enquiries.create')); ?>">+ New enquiry</a>
            </div>
        </div>
    </div>
    <?php if(! empty($strip)): ?>
        <div class="strip">
            <span><i class="dot"></i><?php echo e($d['kpis']['heats']['running']); ?> heat<?php echo e($d['kpis']['heats']['running'] === 1 ? '' : 's'); ?> running</span>
            <span class="mono"><?php echo e(now()->format('D d M Y')); ?></span>
            <span><?php echo e($d['queueTotal']); ?> open production order<?php echo e($d['queueTotal'] === 1 ? '' : 's'); ?></span>
        </div>
    <?php endif; ?>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/dashboard/_hero.blade.php ENDPATH**/ ?>