<?php $__env->startSection('title', 'Exits'); ?>
<?php $__env->startSection('breadcrumb', 'People exits'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Leaving the company</h1><p>Resignations, terminations and retirements, from notice to clearance.</p></div></div>
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="status">Show</label><select id="status" name="status"><?php $__currentLoopData = ['INITIATED' => 'Open', 'COMPLETED' => 'Completed', 'CANCELLED' => 'Cancelled', 'ALL' => 'All']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if($status === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <button class="btn btn-primary">Filter</button></form></div></div>
<div class="panel"><?php if($exits->isEmpty()): ?><div class="empty-state">No exits.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Employee</th><th>Department</th><th>Type</th><th>Last day</th><th>Status</th></tr></thead><tbody>
<?php $__currentLoopData = $exits; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $x): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><a href="<?php echo e(route('hr.exits.show', $x)); ?>"><strong><?php echo e($x->employee->code); ?></strong></a> <?php echo e($x->employee->full_name); ?></td><td><?php echo e($x->employee->department?->name ?? '—'); ?></td><td><?php echo e(config('hr.exit_types')[$x->exit_type]); ?></td><td><?php echo e($x->last_working_day->format('d M Y')); ?></td><td><?php echo $__env->make('hr._chip', ['s' => $x->status], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div><div class="panel-body"><?php echo e($exits->links()); ?></div><?php endif; ?></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/exits/index.blade.php ENDPATH**/ ?>