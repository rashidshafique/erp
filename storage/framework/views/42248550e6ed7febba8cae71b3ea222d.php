<?php $sandStatus = app(\App\Services\Quality\Measurements::class)->sandStatus($run->sand_system, $run->started_at ?? $run->created_at ?? now()); ?>
<div class="panel"><div class="panel-body">
    <strong>Sand condition:</strong>
    <span class="status <?php echo e($sandStatus['state'] === 'OK' ? 'status-completed' : 'status-cancelled'); ?>"><?php echo e($sandStatus['state'] === 'OK' ? 'TESTED' : ($sandStatus['state'] === 'FAIL' ? 'FAILED' : 'NO TEST')); ?></span>
    <?php echo e($sandStatus['text']); ?> <a href="<?php echo e(route('sand-tests.index')); ?>">Sand lab</a>
</div></div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/moulding-runs/_sand.blade.php ENDPATH**/ ?>