<div class="card">
    <div class="chead"><div><h2>Live floor status</h2><p class="sub">Moulding runs in progress, furnaces and heats.</p></div><a class="btnx" href="<?php echo e(route('moulding-runs.index')); ?>">Moulding runs</a></div>
    <?php if(count($d['floor'])): ?>
        <div class="floor">
            <?php $__currentLoopData = $d['floor']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a class="fl" href="<?php echo e($f['url']); ?>">
                    <div class="k"><span class="cap"><?php echo e($f['kind']); ?></span><span class="pill <?php echo e($f['tone'] === 'grey' ? '' : $f['tone']); ?>"><?php echo e($f['state']); ?></span></div>
                    <div class="n"><?php echo e($f['name']); ?></div><div class="s"><?php echo e($f['sub']); ?></div>
                    <?php if($f['progress'] !== null): ?><div class="bar" style="margin-top:10px"><i style="width:<?php echo e($f['progress']); ?>%"></i></div><?php endif; ?>
                    <div class="m mono"><?php echo e($f['meta']); ?><?php if($f['progress'] !== null): ?> · <?php echo e($f['progress']); ?>%<?php endif; ?></div>
                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php else: ?>
        <div class="empty">No moulding run or heat is in progress.</div>
    <?php endif; ?>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/dashboard/_floor.blade.php ENDPATH**/ ?>