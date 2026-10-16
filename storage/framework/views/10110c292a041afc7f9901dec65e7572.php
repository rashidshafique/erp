<?php $hist = \App\Models\AuditLog::where('entity_type', $type)->where('entity_id', (string) $id)->orderByDesc('id')->limit(15)->get(); ?>
<div class="panel">
    <div class="panel-header"><h2>History</h2><a class="small" href="<?php echo e(route('audit.index', ['entity_type' => $type, 'entity_id' => $id])); ?>">Full history</a></div>
    <div class="table-wrap"><table><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Changes</th></tr></thead><tbody>
        <?php $__empty_1 = true; $__currentLoopData = $hist; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><?php echo e($e->occurred_at->setTimezone(config('app.timezone'))->format('d M H:i')); ?></td><td><?php echo e($e->user_name ?? '—'); ?></td><td><?php echo e($e->action); ?></td><td><?php echo $__env->make('audit._changes', ['entry' => $e], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></td></tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="4" class="muted">No changes recorded.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/audit/_history.blade.php ENDPATH**/ ?>