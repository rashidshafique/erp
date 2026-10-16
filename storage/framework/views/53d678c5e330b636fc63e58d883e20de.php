<?php
    $map = ['ONBOARDING' => 'status-on-hold', 'PROBATION' => 'status-in-progress', 'ACTIVE' => 'status-completed', 'NOTICE' => 'status-on-hold', 'EXITED' => 'status-cancelled',
        'DRAFT' => 'status-on-hold', 'PUBLISHED' => 'status-completed', 'RETIRED' => 'status-cancelled', 'INITIATED' => 'status-on-hold', 'COMPLETED' => 'status-completed', 'CANCELLED' => 'status-cancelled'];
    $names = config('hr.statuses') + ['INITIATED' => 'Open', 'COMPLETED' => 'Completed', 'CANCELLED' => 'Cancelled', 'DRAFT' => 'Draft', 'PUBLISHED' => 'Published', 'RETIRED' => 'Retired'];
?>
<span class="status <?php echo e($map[$s] ?? ''); ?>"><?php echo e($names[$s] ?? $s); ?></span>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/_chip.blade.php ENDPATH**/ ?>