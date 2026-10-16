<?php $set = $entry->changeSet(); ?>
<?php if($entry->action === 'CREATED'): ?>
    <span class="muted small"><?php echo e(count($set)); ?> field(s) set</span>
    <details><summary class="small">show</summary><?php $__currentLoopData = $set; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="small"><strong><?php echo e($k); ?></strong>: <?php echo e(is_scalar($v['new'] ?? null) || ($v['new'] ?? null) === null ? ($v['new'] ?? '—') : json_encode($v['new'])); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></details>
<?php elseif($entry->action === 'DELETED'): ?>
    <span class="muted small">record removed</span>
<?php else: ?>
    <?php $__currentLoopData = $set; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="small"><strong><?php echo e($k); ?></strong>: <span class="muted"><?php echo e(is_scalar($v['old'] ?? null) || ($v['old'] ?? null) === null ? (($v['old'] ?? null) === null ? '—' : $v['old']) : json_encode($v['old'])); ?></span> → <?php echo e(is_scalar($v['new'] ?? null) || ($v['new'] ?? null) === null ? (($v['new'] ?? null) === null ? '—' : $v['new']) : json_encode($v['new'])); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php endif; ?>
<?php if($entry->reason): ?><div class="small"><em>Reason: <?php echo e($entry->reason); ?></em></div><?php endif; ?>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/audit/_changes.blade.php ENDPATH**/ ?>