<?php $__env->startSection('title', 'Leave'); ?>
<?php $__env->startSection('breadcrumb', 'Leave'); ?>
<?php $__env->startSection('content'); ?>
<?php
    $cls = ['PENDING' => 'status-on-hold', 'APPROVED' => 'status-completed', 'REJECTED' => 'status-cancelled', 'CANCELLED' => 'status-cancelled'];
    $nf = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.') ?: '0';
    $tab = request('tab');
?>
<div class="page-heading"><div><h1>Leave</h1><p>Requests, balances, leave types and the yearly runs.</p></div><div class="actions"><a class="btn" href="<?php echo e(route('leave.my')); ?>">My leave &amp; approvals</a></div></div>
<?php if(session('error')): ?><div class="alert alert-error"><?php echo e(session('error')); ?></div><?php endif; ?>
<style>.row-edit summary { cursor: pointer; color: var(--accent); font-weight: 600; }</style>

<div class="tab-marker" data-tab-start="Requests<?php echo e($pending ? ' ('.$pending.')' : ''); ?>"></div>
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="status">Show</label><select id="status" name="status"><?php $__currentLoopData = ['PENDING' => 'Waiting for a decision', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected', 'CANCELLED' => 'Cancelled', 'ALL' => 'All']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if($status === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <div class="field"><label for="employee">Employee</label><select id="employee" name="employee"><option value="">Everyone</option><?php $__currentLoopData = $people; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($p->id); ?>" <?php if(request('employee') === $p->id): echo 'selected'; endif; ?>><?php echo e($p->code); ?> · <?php echo e($p->full_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <button class="btn btn-primary">Filter</button></form></div></div>
<div class="panel"><?php if($requests->isEmpty()): ?><div class="empty-state">No requests.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Request</th><th>Employee</th><th>Leave</th><th>Dates</th><th>Days</th><th>Status</th><th></th></tr></thead><tbody>
<?php $__currentLoopData = $requests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><td><strong><?php echo e($r->code); ?></strong></td><td><a href="<?php echo e(route('hr.employees.show', $r->employee)); ?>"><?php echo e($r->employee->full_name); ?></a><div class="muted small"><?php echo e($r->employee->department?->name); ?></div></td><td><?php echo e($r->type->name); ?></td>
<td><?php echo e($r->from_date->format('d M')); ?><?php echo e($r->to_date->ne($r->from_date) ? ' – '.$r->to_date->format('d M Y') : ' '.$r->from_date->format('Y')); ?><?php echo e($r->half_day ? ' (half day)' : ''); ?><?php if($r->reason): ?><div class="muted small"><?php echo e($r->reason); ?></div><?php endif; ?></td><td><?php echo e($nf($r->days)); ?></td>
<td><span class="status <?php echo e($cls[$r->status]); ?>"><?php echo e(ucfirst(strtolower($r->status))); ?></span><?php if($r->decided_by_name): ?><div class="muted small"><?php echo e($r->decided_by_name); ?></div><?php endif; ?></td>
<td style="min-width:240px"><?php if($r->status === 'PENDING'): ?>
<form method="POST" action="<?php echo e(route('leave.decide', $r)); ?>" style="display:flex;gap:4px"><?php echo csrf_field(); ?><input name="note" placeholder="Note" style="min-height:28px;padding:2px 6px;max-width:110px"><button class="btn btn-small btn-primary" name="decision" value="APPROVE">Approve</button><button class="btn btn-small" name="decision" value="REJECT">Reject</button></form>
<?php elseif($r->status === 'APPROVED'): ?><form method="POST" action="<?php echo e(route('leave.cancel', $r)); ?>" onsubmit="return confirm('Cancel this approved leave and give the days back?')"><?php echo csrf_field(); ?><button class="btn btn-small">Cancel</button></form><?php endif; ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div><div class="panel-body"><?php echo e($requests->links()); ?></div><?php endif; ?></div>
<div class="panel"><div class="panel-header"><h2>Record leave for someone</h2><span class="muted small">For leave already agreed, or a request on someone's behalf</span></div><div class="panel-body">
<form method="POST" action="<?php echo e(route('leave.store')); ?>"><?php echo csrf_field(); ?><div class="form-grid">
<?php echo $__env->make('hr._sel', ['name' => 'employee_id', 'label' => 'Employee', 'items' => $people->mapWithKeys(fn ($p) => [$p->id => $p->code.' · '.$p->full_name])->all(), 'sel' => '', 'blank' => 'Choose…', 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('hr._sel', ['name' => 'leave_type_id', 'label' => 'Leave type', 'items' => $types->where('active', true)->mapWithKeys(fn ($t) => [$t->id => $t->name])->all(), 'sel' => '', 'blank' => 'Choose…', 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('hr._in', ['name' => 'from_date', 'label' => 'From', 'type' => 'date', 'val' => '', 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->make('hr._in', ['name' => 'to_date', 'label' => 'To', 'type' => 'date', 'val' => '', 'help' => 'Leave blank for one day.'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('hr._in', ['name' => 'reason', 'label' => 'Reason', 'val' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="field"><label><input type="checkbox" name="half_day" value="1"> Half day</label></div>
<div class="field"><label><input type="checkbox" name="approved_now" value="1" checked> Approve straight away</label></div>
</div><p><button class="btn btn-primary">Record</button></p></form></div></div>

<div class="tab-marker" data-tab-start="Balances"></div>
<div class="panel"><?php if($tracked->isEmpty()): ?><div class="empty-state">No leave type keeps a balance yet.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Employee</th><?php $__currentLoopData = $tracked; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><th><?php echo e($t->code); ?></th><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tr></thead><tbody>
<?php $__currentLoopData = $people; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><a href="<?php echo e(route('hr.employees.show', $p)); ?>"><strong><?php echo e($p->code); ?></strong></a> <?php echo e($p->full_name); ?></td>
<?php $__currentLoopData = $tracked; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php $b = optional(($sums[$p->id] ?? collect())->firstWhere('leave_type_id', $t->id))->d ?? 0; ?><td <?php if($b < 0): ?> style="color:var(--danger)" <?php endif; ?>><?php echo e($nf($b)); ?></td><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div><?php endif; ?></div>
<p class="muted small"><?php echo e($tracked->map(fn ($t) => $t->code.' = '.$t->name)->implode(' · ')); ?>. Open an employee's Leave tab to adjust a balance.</p>

<div class="tab-marker" data-tab-start="Leave types"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Leave</th><th>Days a year</th><th>Entitlement</th><th>Carry forward</th><th>Rules</th><th></th></tr></thead><tbody>
<?php $__currentLoopData = $types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><td><strong><?php echo e($t->code); ?></strong></td><td><?php echo e($t->name); ?> <?php if (! ($t->active)): ?><span class="status status-cancelled">Off</span><?php endif; ?></td><td><?php echo e($t->annual_days > 0 ? $nf($t->annual_days) : '—'); ?></td>
<td><?php echo e(['NONE' => 'No balance kept', 'MONTHLY' => 'Earned monthly', 'UPFRONT' => 'Given at the start of the year'][$t->accrual]); ?></td><td><?php echo e($t->tracksBalance() ? 'up to '.$nf($t->carry_forward_max) : '—'); ?></td>
<td class="small"><?php echo e($t->paid ? 'Paid' : 'Unpaid'); ?><?php echo e($t->allow_half_day ? ', half days' : ''); ?><?php echo e($t->counts_days_off ? ', counts days off' : ''); ?><?php echo e($t->after_probation_only ? ', after probation' : ''); ?><?php echo e($t->gender ? ', '.($t->gender === 'F' ? 'women' : 'men').' only' : ''); ?></td>
<td><details class="row-edit"><summary>Edit</summary><form method="POST" action="<?php echo e(route('hr.leave.types.update', $t)); ?>" class="form-grid"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
<?php echo $__env->make('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => $t->code, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->make('hr._in', ['name' => 'name', 'label' => 'Name', 'val' => $t->name, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('hr._in', ['name' => 'annual_days', 'label' => 'Days a year', 'type' => 'number', 'val' => $t->annual_days, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('hr._sel', ['name' => 'accrual', 'label' => 'Entitlement', 'items' => ['NONE' => 'No balance kept', 'MONTHLY' => 'Earned monthly', 'UPFRONT' => 'Given at the start of the year'], 'sel' => $t->accrual, 'blank' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('hr._in', ['name' => 'carry_forward_max', 'label' => 'Carry forward up to', 'type' => 'number', 'val' => $t->carry_forward_max], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('hr._sel', ['name' => 'gender', 'label' => 'Only for', 'items' => ['F' => 'Women', 'M' => 'Men'], 'sel' => $t->gender, 'blank' => 'Everyone'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="field"><label><input type="checkbox" name="paid" value="1" <?php if($t->paid): echo 'checked'; endif; ?>> Paid</label><label><input type="checkbox" name="allow_half_day" value="1" <?php if($t->allow_half_day): echo 'checked'; endif; ?>> Half days allowed</label><label><input type="checkbox" name="counts_days_off" value="1" <?php if($t->counts_days_off): echo 'checked'; endif; ?>> Days off count too</label><label><input type="checkbox" name="after_probation_only" value="1" <?php if($t->after_probation_only): echo 'checked'; endif; ?>> After probation only</label><label><input type="checkbox" name="active" value="1" <?php if($t->active): echo 'checked'; endif; ?>> In use</label></div>
<div class="field"><button class="btn btn-primary">Save</button></div></form></details></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div>
<div class="panel-body"><p class="muted small">The days and rules above are starting values, not legal advice. Set them to what your province's labour law and your contracts say.</p>
<form method="POST" action="<?php echo e(route('hr.leave.types.store')); ?>"><?php echo csrf_field(); ?><div class="form-grid">
<?php echo $__env->make('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => '', 'required' => true, 'ph' => 'e.g. HL'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->make('hr._in', ['name' => 'name', 'label' => 'New leave type', 'val' => '', 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('hr._in', ['name' => 'annual_days', 'label' => 'Days a year', 'type' => 'number', 'val' => 0, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('hr._sel', ['name' => 'accrual', 'label' => 'Entitlement', 'items' => ['NONE' => 'No balance kept', 'MONTHLY' => 'Earned monthly', 'UPFRONT' => 'Given at the start of the year'], 'sel' => 'NONE', 'blank' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="field"><label><input type="checkbox" name="paid" value="1" checked> Paid</label><label><input type="checkbox" name="allow_half_day" value="1" checked> Half days allowed</label></div>
</div><p><button class="btn btn-primary">Add leave type</button></p></form></div></div>

<div class="tab-marker" data-tab-start="Yearly runs"></div>
<div class="panel"><div class="panel-header"><h2>Bring entitlements up to date</h2></div><div class="panel-body">
<p class="muted">Gives everyone this year's up-front leave and every completed month of monthly leave. Safe to run again: nothing is added twice. The same run is available from the command line as <code>php artisan hr:leave-accrue</code>.</p>
<form method="POST" action="<?php echo e(route('hr.leave.accrue')); ?>"><?php echo csrf_field(); ?><button class="btn btn-primary">Run now</button></form></div></div>
<div class="panel"><div class="panel-header"><h2>Close a year</h2></div><div class="panel-body">
<p class="muted">Carries each balance forward up to the leave type's limit and writes off the rest. Run it once the year has ended, then run the entitlements for the new year.</p>
<form method="POST" action="<?php echo e(route('hr.leave.close-year')); ?>" class="form-grid" onsubmit="return confirm('Write off leave above the carry-forward limit for this year?')"><?php echo csrf_field(); ?>
<?php echo $__env->make('hr._in', ['name' => 'year', 'label' => 'Year', 'type' => 'number', 'val' => $year - 1, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><div class="field"><label>&nbsp;</label><button class="btn">Close the year</button></div></form></div></div>
<?php if($tab): ?><script>document.addEventListener('DOMContentLoaded', function () { var m = {requests:'Requests',balances:'Balances',types:'Leave types',runs:'Yearly runs'}[<?php echo json_encode($tab, 15, 512) ?>]; var b = [].slice.call(document.querySelectorAll('.tabbar button')).filter(function (x) { return x.textContent.trim().indexOf(m) === 0; })[0]; if (b) b.click(); });</script><?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/leave.blade.php ENDPATH**/ ?>