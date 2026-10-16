<?php $__env->startSection('title', 'My leave'); ?>
<?php $__env->startSection('breadcrumb', 'My leave'); ?>
<?php $__env->startSection('content'); ?>
<?php
    $cls = ['PENDING' => 'status-on-hold', 'APPROVED' => 'status-completed', 'REJECTED' => 'status-cancelled', 'CANCELLED' => 'status-cancelled'];
    $nf = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.') ?: '0';
?>
<div class="page-heading"><div><h1>My leave</h1><p><?php echo e($me ? $me->full_name.' · '.$me->code : 'Your login is not linked to an employee record.'); ?></p></div><?php if($isHr): ?><div class="actions"><a class="btn" href="<?php echo e(route('hr.leave')); ?>">All leave (HR)</a></div><?php endif; ?></div>
<?php if(session('error')): ?><div class="alert alert-error"><?php echo e(session('error')); ?></div><?php endif; ?>

<?php if($waiting->isNotEmpty()): ?>
<div class="panel"><div class="panel-header"><h2>Waiting for your decision</h2><span class="muted small"><?php echo e($waiting->count()); ?></span></div><div class="table-wrap"><table><thead><tr><th>Who</th><th>Leave</th><th>Dates</th><th>Days</th><th></th></tr></thead><tbody>
<?php $__currentLoopData = $waiting; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><td><strong><?php echo e($r->employee->full_name); ?></strong><div class="muted small"><?php echo e($r->code); ?></div></td><td><?php echo e($r->type->name); ?></td>
<td><?php echo e($r->from_date->format('d M')); ?><?php echo e($r->to_date->ne($r->from_date) ? ' – '.$r->to_date->format('d M Y') : ' '.$r->from_date->format('Y')); ?><?php echo e($r->half_day ? ' (half day)' : ''); ?><?php if($r->reason): ?><div class="muted small"><?php echo e($r->reason); ?></div><?php endif; ?></td><td><?php echo e($nf($r->days)); ?></td>
<td><form method="POST" action="<?php echo e(route('leave.decide', $r)); ?>" style="display:flex;gap:4px"><?php echo csrf_field(); ?><input name="note" placeholder="Note" style="min-height:28px;padding:2px 6px;max-width:120px"><button class="btn btn-small btn-primary" name="decision" value="APPROVE">Approve</button><button class="btn btn-small" name="decision" value="REJECT">Reject</button></form></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div></div>
<?php endif; ?>

<?php if($me || $isHr): ?>
<div class="tab-marker" data-tab-start="Ask for leave"></div>
<?php if($me): ?>
<div class="metric-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:16px">
<?php $__currentLoopData = $balances; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="metric"><div class="metric-label"><?php echo e($b['type']->name); ?></div><div class="metric-value"><?php echo e($nf($b['balance'])); ?></div></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
<?php endif; ?>
<div class="panel"><div class="panel-header"><h2>New request</h2></div><div class="panel-body">
<form method="POST" action="<?php echo e(route('leave.store')); ?>"><?php echo csrf_field(); ?><div class="form-grid">
<?php if($isHr): ?><?php echo $__env->make('hr._sel', ['name' => 'employee_id', 'label' => 'For', 'items' => $people->mapWithKeys(fn ($p) => [$p->id => $p->code.' · '.$p->full_name])->all(), 'sel' => $me?->id, 'blank' => $me ? false : 'Choose…', 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endif; ?>
<?php echo $__env->make('hr._sel', ['name' => 'leave_type_id', 'label' => 'Leave type', 'items' => $types->mapWithKeys(fn ($t) => [$t->id => $t->name])->all(), 'sel' => '', 'blank' => 'Choose…', 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('hr._in', ['name' => 'from_date', 'label' => 'From', 'type' => 'date', 'val' => '', 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('hr._in', ['name' => 'to_date', 'label' => 'To', 'type' => 'date', 'val' => '', 'help' => 'Leave blank for one day.'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('hr._in', ['name' => 'reason', 'label' => 'Reason', 'val' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="field"><label><input type="checkbox" name="half_day" value="1"> Half day</label></div>
</div><p><button class="btn btn-primary">Send for approval</button></p></form></div></div>

<div class="tab-marker" data-tab-start="My requests"></div>
<div class="panel"><?php if($mine->isEmpty()): ?><div class="empty-state">No requests yet.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Request</th><th>Leave</th><th>Dates</th><th>Days</th><th>Status</th><th></th></tr></thead><tbody>
<?php $__currentLoopData = $mine; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><td><strong><?php echo e($r->code); ?></strong></td><td><?php echo e($r->type->name); ?></td><td><?php echo e($r->from_date->format('d M')); ?><?php echo e($r->to_date->ne($r->from_date) ? ' – '.$r->to_date->format('d M Y') : ' '.$r->from_date->format('Y')); ?></td><td><?php echo e($nf($r->days)); ?></td>
<td><span class="status <?php echo e($cls[$r->status]); ?>"><?php echo e(ucfirst(strtolower($r->status))); ?></span><?php if($r->decision_note): ?><div class="muted small"><?php echo e($r->decision_note); ?></div><?php endif; ?></td>
<td><?php if($r->status === 'PENDING' || ($r->status === 'APPROVED' && $r->from_date->isFuture())): ?><form method="POST" action="<?php echo e(route('leave.cancel', $r)); ?>" onsubmit="return confirm('Cancel this request?')"><?php echo csrf_field(); ?><button class="btn btn-small">Cancel</button></form><?php endif; ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div><?php endif; ?></div>
<?php endif; ?>

<?php if($team->isNotEmpty()): ?>
<div class="tab-marker" data-tab-start="My team"></div>
<div class="panel"><div class="table-wrap"><table><tbody><?php $__currentLoopData = $team; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><strong><?php echo e($t->full_name); ?></strong></td><td class="muted"><?php echo e($t->designation?->name); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div></div>
<?php endif; ?>
<?php if (! ($me || $isHr)): ?><div class="panel"><div class="empty-state">Ask HR to link your login to your employee record so you can request leave.</div></div><?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/leave/my.blade.php ENDPATH**/ ?>