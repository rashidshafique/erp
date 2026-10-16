<?php $__env->startSection('title', 'Overtime'); ?>
<?php $__env->startSection('breadcrumb', 'Overtime'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Overtime</h1><p>Time past the end of the shift, and work on days off. Only approved minutes go to payroll.</p></div></div>
<?php echo $__env->make('hr.attendance._tabs', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="from">From</label><input id="from" type="date" name="from" value="<?php echo e($from); ?>"></div>
    <div class="field"><label for="to">To</label><input id="to" type="date" name="to" value="<?php echo e($to); ?>"></div>
    <div class="field"><label for="status">Show</label><select id="status" name="status"><?php $__currentLoopData = ['PENDING' => 'Waiting', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected', 'ALL' => 'All']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if($status === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <button class="btn btn-primary">Filter</button></form></div></div>
<form method="POST" action="<?php echo e(route('hr.attendance.overtime.decide')); ?>"><?php echo csrf_field(); ?>
<div class="panel"><?php if($rows->isEmpty()): ?><div class="empty-state">No overtime in this range.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th style="width:34px"></th><th>Day</th><th>Employee</th><th>Worked past / on day off</th><th>Overtime</th><th>Approve (minutes)</th><th>Status</th></tr></thead><tbody>
<?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><td><input type="checkbox" name="ids[]" value="<?php echo e($r->id); ?>"></td><td><?php echo e($r->work_date->format('D d M')); ?></td>
<td><a href="<?php echo e(route('hr.employees.show', $r->employee)); ?>"><strong><?php echo e($r->employee->code); ?></strong></a> <?php echo e($r->employee->full_name); ?></td>
<td class="small"><?php echo e($r->check_in?->format('H:i')); ?> – <?php echo e($r->check_out?->format('H:i')); ?> <?php if($r->hasFlag('WORKED_OFF')): ?><span class="status status-in-progress">Day off</span><?php endif; ?></td>
<td><?php echo e(\App\Models\AttendanceRecord::hm($r->overtime_minutes)); ?></td>
<td><input type="number" name="minutes[<?php echo e($r->id); ?>]" value="<?php echo e($r->overtime_status === 'APPROVED' ? $r->overtime_approved_minutes : $r->overtime_minutes); ?>" min="0" max="<?php echo e($r->overtime_minutes); ?>" style="width:90px;min-height:30px;padding:3px 6px"></td>
<td><span class="status <?php echo e(['PENDING' => 'status-on-hold', 'APPROVED' => 'status-completed', 'REJECTED' => 'status-cancelled'][$r->overtime_status] ?? ''); ?>"><?php echo e(ucfirst(strtolower($r->overtime_status))); ?></span></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div>
<div class="panel-body"><button class="btn btn-primary" name="decision" value="APPROVE">Approve ticked</button> <button class="btn" name="decision" value="REJECT">Reject ticked</button></div>
<div class="panel-body"><?php echo e($rows->links()); ?></div><?php endif; ?></div></form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/attendance/overtime.blade.php ENDPATH**/ ?>