<?php $__env->startSection('title', 'Attendance exceptions'); ?>
<?php $__env->startSection('breadcrumb', 'Attendance exceptions'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Attendance exceptions</h1><p>Absences and missing punches that need someone to settle them.</p></div></div>
<?php echo $__env->make('hr.attendance._tabs', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="from">From</label><input id="from" type="date" name="from" value="<?php echo e($from); ?>"></div>
    <div class="field"><label for="to">To</label><input id="to" type="date" name="to" value="<?php echo e($to); ?>"></div>
    <div class="field"><label for="status">Show</label><select id="status" name="status"><?php $__currentLoopData = ['OPEN' => 'Open', 'RESOLVED' => 'Settled', 'ALL' => 'Both']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if($status === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <button class="btn btn-primary">Filter</button></form></div></div>
<div class="panel"><?php if($rows->isEmpty()): ?><div class="empty-state">Nothing to settle.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Day</th><th>Employee</th><th>What happened</th><th>Settle it</th></tr></thead><tbody>
<?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><td><?php echo e($r->work_date->format('D d M')); ?></td>
<td><a href="<?php echo e(route('hr.employees.show', $r->employee)); ?>"><strong><?php echo e($r->employee->code); ?></strong></a> <?php echo e($r->employee->full_name); ?><div class="muted small"><?php echo e($r->employee->department?->name); ?></div></td>
<td><?php echo $__env->make('hr._dayflags', ['flags' => $r->flags], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><div class="small muted"><?php echo e($r->check_in ? 'In '.$r->check_in->format('H:i') : 'No check-in'); ?> · <?php echo e($r->check_out ? 'Out '.$r->check_out->format('H:i') : 'No check-out'); ?></div></td>
<td><?php if($r->exception_status === 'OPEN'): ?>
<form method="POST" action="<?php echo e(route('hr.attendance.resolve', $r)); ?>" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center"><?php echo csrf_field(); ?>
<select name="how" required style="min-height:30px;padding:3px 6px;max-width:220px"><option value="">Choose…</option><option value="PRESENT">Excused, count as present</option><option value="ABSENT">Confirm absent</option><?php $__currentLoopData = $types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="LEAVE:<?php echo e($t->id); ?>">Charge to <?php echo e($t->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
<input name="note" placeholder="Note" style="min-height:30px;padding:3px 6px;max-width:150px"><button class="btn btn-small btn-primary">Settle</button>
<a class="btn btn-small" href="<?php echo e(route('hr.attendance', ['date' => $r->work_date->toDateString()])); ?>">Fix times</a></form>
<?php else: ?><span class="status status-completed">Settled</span> <span class="small muted"><?php echo e($r->resolution); ?></span><?php endif; ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div><div class="panel-body"><?php echo e($rows->links()); ?></div><?php endif; ?></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/attendance/exceptions.blade.php ENDPATH**/ ?>