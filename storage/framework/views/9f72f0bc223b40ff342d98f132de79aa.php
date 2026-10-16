<?php $__env->startSection('title', $e->code.' '.$e->full_name); ?>
<?php $__env->startSection('breadcrumb'); ?><a href="<?php echo e(route('hr.employees.index')); ?>">Employees</a> / <?php echo e($e->code); ?> <?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<?php
    $photo = \App\Models\Attachment::coverFor('employee', $e->id);
    $opts = fn ($c, $fn = null) => $c->mapWithKeys(fn ($r) => [$r->id => $fn ? $fn($r) : $r->name])->all();
    $onb = $e->tasks->where('kind', 'ONBOARDING');
    $ext = $e->tasks->where('kind', 'EXIT');
    $docFiles = \App\Models\Attachment::where('attachable_type', 'employee_document')->whereIn('attachable_id', $e->documents->pluck('id'))->whereNull('removed_at')->get()->groupBy('attachable_id');
    $expiry = ['EXPIRED' => ['status-cancelled', 'Expired'], 'SOON' => ['status-on-hold', 'Expires soon'], 'OK' => ['status-completed', 'Valid']];
?>
<div class="page-heading">
    <div style="display:flex;gap:16px;align-items:center">
        <?php if($photo): ?><img src="<?php echo e(route('attachments.download', $photo)); ?>" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:50%;border:1px solid var(--border);padding:0;min-height:0"><?php endif; ?>
        <div><h1><?php echo e($e->full_name); ?></h1><p><?php echo e($e->code); ?> · <?php echo e($e->designation?->name ?? 'No designation'); ?> · <?php echo e($e->department?->name ?? 'No department'); ?> · <?php echo $__env->make('hr._chip', ['s' => $e->status], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></p></div>
    </div>
    <div class="actions">
        <?php if($e->status === 'ONBOARDING'): ?><form method="POST" action="<?php echo e(route('hr.employees.start-work', $e)); ?>"><?php echo csrf_field(); ?><button class="btn btn-primary" type="submit">Finish onboarding</button></form><?php endif; ?>
        <a class="btn" href="<?php echo e(route('hr.employees.index')); ?>">All employees</a>
    </div>
</div>
<?php if(session('error')): ?><div class="alert alert-error"><?php echo e(session('error')); ?></div><?php endif; ?>
<?php if($openExit): ?><div class="alert alert-error">Leaving on <?php echo e($openExit->last_working_day->format('d M Y')); ?> (<?php echo e(config('hr.exit_types')[$openExit->exit_type]); ?>). <a href="<?php echo e(route('hr.exits.show', $openExit)); ?>">Open the exit</a></div><?php endif; ?>

<div class="tab-marker" data-tab-start="Summary"></div>
<div class="panel"><div class="panel-header"><h2>At a glance</h2></div><div class="panel-body">
<div class="form-grid">
    <div class="field"><label>Position</label><div><?php echo e($e->designation?->name ?? '—'); ?><?php echo e($e->grade ? ' · '.$e->grade->name : ''); ?></div></div>
    <div class="field"><label>Department</label><div><?php echo e($e->department?->path() ?? '—'); ?></div></div>
    <div class="field"><label>Location</label><div><?php echo e($e->location?->name ?? '—'); ?></div></div>
    <div class="field"><label>Reports to</label><div><?php if($e->manager): ?><a href="<?php echo e(route('hr.employees.show', $e->manager)); ?>"><?php echo e($e->manager->full_name); ?></a><?php else: ?> — <?php endif; ?></div></div>
    <div class="field"><label>Cost centre</label><div><?php echo e($e->costCentre ? $e->costCentre->code.' · '.$e->costCentre->name : '—'); ?></div></div>
    <div class="field"><label>Employment</label><div><?php echo e(config('hr.employment_types')[$e->employment_type] ?? $e->employment_type); ?></div></div>
    <div class="field"><label>Joined</label><div><?php echo e($e->joined_on->format('d M Y')); ?> · <?php echo e($e->service()); ?></div></div>
    <div class="field"><label><?php echo e($e->status === 'PROBATION' ? 'Probation ends' : 'Confirmed'); ?></label><div><?php echo e($e->status === 'PROBATION' ? $e->probation_ends_on?->format('d M Y') : ($e->confirmed_on?->format('d M Y') ?? '—')); ?></div></div>
    <div class="field"><label>Mobile</label><div><?php echo e($e->phone ?: '—'); ?></div></div>
    <div class="field"><label>Work email</label><div><?php echo e($e->work_email ?: '—'); ?></div></div>
    <?php if($e->reports->isNotEmpty()): ?><div class="field field-full"><label>Direct reports (<?php echo e($e->reports->count()); ?>)</label><div><?php $__currentLoopData = $e->reports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e(route('hr.employees.show', $r)); ?>"><?php echo e($r->full_name); ?></a><?php echo e($r->designation ? ' ('.$r->designation->name.')' : ''); ?><?php echo e(! $loop->last ? ', ' : ''); ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></div><?php endif; ?>
</div></div></div>
<?php if($onb->isNotEmpty() && $e->status === 'ONBOARDING'): ?>
<div class="panel"><div class="panel-header"><h2>Joining checklist</h2><span class="muted small"><?php echo e($onb->whereNotNull('done_at')->count()); ?> of <?php echo e($onb->count()); ?> done</span></div></div>
<?php endif; ?>

<div class="tab-marker" data-tab-start="Personal & pay"></div>
<form method="POST" action="<?php echo e(route('hr.employees.update', $e)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
    <?php echo $__env->make('hr.employees._fields', ['e' => $e], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="panel"><div class="panel-header"><h2>Login</h2></div><div class="panel-body"><div class="form-grid">
        <?php echo $__env->make('hr._sel', ['name' => 'user_id', 'label' => 'Linked user account', 'items' => $opts($users, fn ($u) => $u->name.' · '.$u->email), 'sel' => $e->user_id, 'blank' => 'No login'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="help field-full">A linked login is switched off automatically when the person leaves.</div>
    </div></div></div>
    <p><button class="btn btn-primary" type="submit">Save changes</button></p>
</form>

<div class="tab-marker" data-tab-start="Position & history"></div>
<?php if($e->isCurrent() && ! $openExit): ?>
<div class="panel"><div class="panel-header"><h2>Transfer or promote</h2><span class="muted small">Dated and recorded; may need approval</span></div><div class="panel-body">
<form method="POST" action="<?php echo e(route('hr.employees.transfer', $e)); ?>"><?php echo csrf_field(); ?>
<div class="form-grid">
    <?php echo $__env->make('hr._sel', ['name' => 'kind', 'label' => 'This is a', 'items' => ['TRANSFER' => 'Transfer', 'PROMOTION' => 'Promotion'], 'sel' => 'TRANSFER', 'blank' => false, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'effective_on', 'label' => 'Effective from', 'type' => 'date', 'val' => now()->toDateString(), 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'department_id', 'label' => 'Department', 'items' => $opts($departments, fn ($d) => $d->path()), 'sel' => $e->department_id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'designation_id', 'label' => 'Designation', 'items' => $opts($designations), 'sel' => $e->designation_id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'grade_id', 'label' => 'Grade', 'items' => $opts($grades), 'sel' => $e->grade_id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'location_id', 'label' => 'Location', 'items' => $opts($locations), 'sel' => $e->location_id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'cost_centre_id', 'label' => 'Cost centre', 'items' => $opts($costCentres, fn ($c) => $c->code.' · '.$c->name), 'sel' => $e->cost_centre_id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'manager_id', 'label' => 'Reports to', 'items' => $opts($managers->where('id', '!=', $e->id), fn ($m) => $m->code.' · '.$m->full_name), 'sel' => $e->manager_id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'note', 'label' => 'Note', 'val' => '', 'ph' => 'Reason or letter reference'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div><p><button class="btn btn-primary" type="submit">Record</button></p></form>
</div></div>
<?php endif; ?>
<?php if($e->status === 'PROBATION'): ?>
<div class="panel"><div class="panel-header"><h2>Confirm after probation</h2><span class="muted small">Probation ends <?php echo e($e->probation_ends_on?->format('d M Y') ?? '—'); ?></span></div><div class="panel-body">
<form method="POST" action="<?php echo e(route('hr.employees.confirm', $e)); ?>"><?php echo csrf_field(); ?><div class="form-grid">
    <?php echo $__env->make('hr._in', ['name' => 'confirmed_on', 'label' => 'Confirmed from', 'type' => 'date', 'val' => now()->toDateString(), 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'note', 'label' => 'Note', 'val' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div><p><button class="btn btn-primary" type="submit">Confirm employee</button></p></form></div></div>
<?php endif; ?>
<?php if($e->isCurrent() && ! $openExit): ?>
<div class="panel"><div class="panel-header"><h2>Leaving the company</h2></div><div class="panel-body">
<form method="POST" action="<?php echo e(route('hr.employees.exit', $e)); ?>"><?php echo csrf_field(); ?><div class="form-grid">
    <?php echo $__env->make('hr._sel', ['name' => 'exit_type', 'label' => 'Type', 'items' => config('hr.exit_types'), 'sel' => 'RESIGNATION', 'blank' => false, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'notice_given_on', 'label' => 'Notice given on', 'type' => 'date', 'val' => now()->toDateString()], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'last_working_day', 'label' => 'Last working day', 'type' => 'date', 'val' => now()->addDays(30)->toDateString(), 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'reason', 'label' => 'Reason', 'val' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div><p><button class="btn btn-danger" type="submit" onclick="return confirm('Start the exit process for this person?')">Start exit</button></p></form></div></div>
<?php endif; ?>
<div class="panel"><div class="panel-header"><h2>History</h2></div>
<div class="table-wrap"><table><thead><tr><th>Date</th><th>Event</th><th>What changed</th><th>By</th></tr></thead><tbody>
<?php $__currentLoopData = $e->events; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ev): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><td><?php echo e($ev->effective_on->format('d M Y')); ?></td><td><?php echo e(\App\Models\EmployeeEvent::KINDS[$ev->kind] ?? $ev->kind); ?></td>
<td><?php $__currentLoopData = ($ev->changes ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(isset($c['label'])): ?><?php echo e($c['label']); ?>: <?php echo e($c['from'] ?? '—'); ?> → <strong><?php echo e($c['to'] ?? '—'); ?></strong><?php else: ?><?php echo e($c['from'] ?? ''); ?> → <strong><?php echo e($c['to'] ?? ''); ?></strong><?php endif; ?><br><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php echo e($ev->note); ?></td><td class="muted"><?php echo e($ev->created_by_name); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div></div>

<div class="tab-marker" data-tab-start="Checklists"></div>
<?php $__currentLoopData = ['ONBOARDING' => ['Joining checklist', $onb], 'EXIT' => ['Leaving checklist', $ext]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kind => [$title, $list]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php if($list->isNotEmpty() || $kind === 'ONBOARDING'): ?>
<div class="panel"><div class="panel-header"><h2><?php echo e($title); ?></h2><span class="muted small"><?php echo e($list->whereNotNull('done_at')->count()); ?> of <?php echo e($list->count()); ?> done</span></div>
<?php if($list->isNotEmpty()): ?><div class="table-wrap"><table><thead><tr><th style="width:44px"></th><th>Task</th><th>Owner</th><th>Due</th><th>Done</th><th></th></tr></thead><tbody>
<?php $__currentLoopData = $list; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><td><form method="POST" action="<?php echo e(route('hr.tasks.toggle', $t)); ?>"><?php echo csrf_field(); ?><button class="btn btn-small" type="submit" title="<?php echo e($t->done_at ? 'Mark not done' : 'Mark done'); ?>"><?php echo $t->done_at ? '&#10003;' : '&#9675;'; ?></button></form></td>
<td <?php if($t->done_at): ?> style="text-decoration:line-through;color:var(--muted)" <?php endif; ?>><?php echo e($t->title); ?></td><td><?php echo e(\App\Models\EmployeeTask::OWNERS[$t->owner] ?? $t->owner); ?></td>
<td><?php if($t->isOverdue()): ?><span class="status status-cancelled"><?php echo e($t->due_on->format('d M')); ?></span><?php else: ?><?php echo e($t->due_on?->format('d M Y') ?? '—'); ?><?php endif; ?></td>
<td class="muted small"><?php echo e($t->done_at ? $t->done_at->format('d M Y').' · '.$t->done_by_name : ''); ?></td>
<td><form method="POST" action="<?php echo e(route('hr.tasks.destroy', $t)); ?>" onsubmit="return confirm('Remove this task?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-small">Remove</button></form></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div><?php endif; ?>
<div class="panel-body"><form method="POST" action="<?php echo e(route('hr.employees.tasks.store', $e)); ?>" class="form-grid"><?php echo csrf_field(); ?><input type="hidden" name="kind" value="<?php echo e($kind); ?>">
    <?php echo $__env->make('hr._in', ['name' => 'title', 'label' => 'Add a task', 'val' => '', 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'owner', 'label' => 'Owner', 'items' => \App\Models\EmployeeTask::OWNERS, 'sel' => 'HR', 'blank' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'due_on', 'label' => 'Due', 'type' => 'date', 'val' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="field"><label>&nbsp;</label><button class="btn" type="submit">Add</button></div></form></div></div>
<?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<div class="tab-marker" data-tab-start="Attendance"></div>
<?php $stc = ['PRESENT' => 'status-completed', 'HALF_DAY' => 'status-on-hold', 'ABSENT' => 'status-cancelled', 'LEAVE' => 'status-in-progress', 'OFF' => '', 'HOLIDAY' => '']; ?>
<div class="panel"><div class="panel-header"><h2><?php echo e($month->format('F Y')); ?></h2>
<div><a class="btn btn-small" href="<?php echo e(route('hr.employees.show', [$e, 'month' => $month->copy()->subMonth()->format('Y-m-01'), 'tab' => 'attendance'])); ?>">&larr;</a> <a class="btn btn-small" href="<?php echo e(route('hr.employees.show', [$e, 'month' => $month->copy()->addMonth()->format('Y-m-01'), 'tab' => 'attendance'])); ?>">&rarr;</a></div></div>
<div class="panel-body small">Present <?php echo e($attSummary['PRESENT']); ?> · Half days <?php echo e($attSummary['HALF_DAY']); ?> · Absent <?php echo e($attSummary['ABSENT']); ?> · Leave <?php echo e($attSummary['LEAVE']); ?> · Days off <?php echo e($attSummary['OFF'] + $attSummary['HOLIDAY']); ?> · Late <?php echo e($attSummary['late']); ?> times · Approved overtime <?php echo e(\App\Models\AttendanceRecord::hm($attSummary['overtime'])); ?></div>
<div class="table-wrap"><table><thead><tr><th>Day</th><th>Status</th><th>In</th><th>Out</th><th>Worked</th><th></th></tr></thead><tbody>
<?php for($d = $month->copy(); $d->month === $month->month; $d->addDay()): ?>
<?php $r = $attRows->get($d->toDateString()); ?>
<tr><td><?php echo e($d->format('D d')); ?></td><td><?php if($r): ?><span class="status <?php echo e($stc[$r->status] ?? ''); ?>"><?php echo e(config('hr.attendance.day_status')[$r->status]); ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
<td><?php echo e($r?->check_in?->format('H:i') ?? ''); ?></td><td><?php echo e($r?->check_out?->format('H:i') ?? ''); ?></td><td><?php echo e($r ? \App\Models\AttendanceRecord::hm($r->worked_minutes) : ''); ?></td><td><?php if($r): ?><?php echo $__env->make('hr._dayflags', ['flags' => $r->flags], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endif; ?></td></tr>
<?php endfor; ?></tbody></table></div></div>

<div class="tab-marker" data-tab-start="Leave"></div>
<?php $nf = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.') ?: '0'; ?>
<div class="metric-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:16px">
<?php $__currentLoopData = $leaveBalances; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="metric"><div class="metric-label"><?php echo e($b['type']->name); ?></div><div class="metric-value"><?php echo e($nf($b['balance'])); ?></div></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
<div class="panel"><div class="panel-header"><h2>Requests</h2></div>
<?php if($leaveRequests->isEmpty()): ?><div class="empty-state">No leave yet.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Request</th><th>Leave</th><th>Dates</th><th>Days</th><th>Status</th></tr></thead><tbody>
<?php $__currentLoopData = $leaveRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($r->code); ?></td><td><?php echo e($r->type->name); ?></td><td><?php echo e($r->from_date->format('d M Y')); ?><?php echo e($r->to_date->ne($r->from_date) ? ' – '.$r->to_date->format('d M Y') : ''); ?></td><td><?php echo e($nf($r->days)); ?></td><td><?php echo e(ucfirst(strtolower($r->status))); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div><?php endif; ?>
<div class="panel-body"><form method="POST" action="<?php echo e(route('leave.store')); ?>"><?php echo csrf_field(); ?><input type="hidden" name="employee_id" value="<?php echo e($e->id); ?>"><div class="form-grid">
<?php echo $__env->make('hr._sel', ['name' => 'leave_type_id', 'label' => 'Record leave', 'items' => $leaveTypes->mapWithKeys(fn ($t) => [$t->id => $t->name])->all(), 'sel' => '', 'blank' => 'Choose…', 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('hr._in', ['name' => 'from_date', 'label' => 'From', 'type' => 'date', 'val' => '', 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->make('hr._in', ['name' => 'to_date', 'label' => 'To', 'type' => 'date', 'val' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="field"><label><input type="checkbox" name="half_day" value="1"> Half day</label><label><input type="checkbox" name="approved_now" value="1" checked> Approved</label></div>
</div><p><button class="btn btn-primary">Record leave</button></p></form></div></div>
<div class="panel"><div class="panel-header"><h2>Balance history</h2></div><div class="table-wrap"><table><thead><tr><th>Date</th><th>Leave</th><th>What</th><th>Days</th><th>Note</th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $ledger; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><?php echo e($l->entry_date->format('d M Y')); ?></td><td><?php echo e($l->type->code); ?></td><td><?php echo e(ucfirst(strtolower($l->kind))); ?></td><td <?php if($l->days < 0): ?> style="color:var(--danger)" <?php endif; ?>><?php echo e($l->days > 0 ? '+' : ''); ?><?php echo e($nf($l->days)); ?></td><td class="muted small"><?php echo e($l->note); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5" class="muted">Nothing yet.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="panel-body"><form method="POST" action="<?php echo e(route('hr.leave.adjust', $e)); ?>"><div class="form-grid"><?php echo csrf_field(); ?>
<?php echo $__env->make('hr._sel', ['name' => 'leave_type_id', 'label' => 'Adjust a balance', 'items' => $leaveBalances->mapWithKeys(fn ($b) => [$b['type']->id => $b['type']->name])->all(), 'sel' => '', 'blank' => 'Choose…', 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('hr._in', ['name' => 'days', 'label' => 'Days (+ or −)', 'type' => 'number', 'val' => '', 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->make('hr._in', ['name' => 'note', 'label' => 'Why', 'val' => '', 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div><p><button class="btn">Adjust</button></p></form></div></div>

<?php if(! empty($pay)): ?><?php echo $__env->make('hr.payroll._employee', ['e' => $e, 'pay' => $pay], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endif; ?>
<div class="tab-marker" data-tab-start="Documents"></div>
<div class="panel"><div class="panel-header"><h2>Documents on record</h2></div>
<?php if($e->documents->isNotEmpty()): ?><div class="table-wrap"><table><thead><tr><th>Document</th><th>Number</th><th>Issued</th><th>Expires</th><th>File</th><th></th></tr></thead><tbody>
<?php $__currentLoopData = $e->documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php $st = $d->expiryState(); ?>
<tr><td><?php echo e(config('hr.document_kinds')[$d->kind] ?? $d->kind); ?><?php echo e($d->title ? ' · '.$d->title : ''); ?></td><td><?php echo e($d->number ?: '—'); ?></td><td><?php echo e($d->issued_on?->format('d M Y') ?? '—'); ?></td>
<td><?php echo e($d->expires_on?->format('d M Y') ?? '—'); ?> <?php if($st && $st !== 'OK'): ?><span class="status <?php echo e($expiry[$st][0]); ?>"><?php echo e($expiry[$st][1]); ?></span><?php endif; ?></td>
<td><?php $__currentLoopData = $docFiles->get($d->id, collect()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e(route('attachments.download', $f)); ?>" <?php if($f->isInlineViewable()): ?> target="_blank" <?php endif; ?>><?php echo e($f->original_name); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></td>
<td><form method="POST" action="<?php echo e(route('hr.documents.destroy', $d)); ?>" onsubmit="return confirm('Remove this document record?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-small">Remove</button></form></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div><?php endif; ?>
<div class="panel-body"><form method="POST" action="<?php echo e(route('hr.employees.documents.store', $e)); ?>" enctype="multipart/form-data"><div class="form-grid">
    <?php echo $__env->make('hr._sel', ['name' => 'kind', 'label' => 'Type', 'items' => config('hr.document_kinds'), 'sel' => 'CNIC', 'blank' => false, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'number', 'label' => 'Number', 'val' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'issued_on', 'label' => 'Issued', 'type' => 'date', 'val' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'expires_on', 'label' => 'Expires', 'type' => 'date', 'val' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'title', 'label' => 'Title', 'val' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="field"><label for="f_file">File</label><input id="f_file" type="file" name="file"></div>
</div><p><button class="btn btn-primary" type="submit">Add document</button></p></form></div></div>

<div class="tab-marker" data-tab-start="Policies"></div>
<div class="panel"><div class="panel-header"><h2>Policy acknowledgements</h2></div>
<?php if($policies->isEmpty()): ?><div class="empty-state">No published policies need acknowledging.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Policy</th><th>Version</th><th>Status</th><th></th></tr></thead><tbody>
<?php $__currentLoopData = $policies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php $a = $p->acks->firstWhere('version', $p->version); ?>
<tr><td><a href="<?php echo e(route('hr.policies.show', $p)); ?>"><?php echo e($p->code); ?> · <?php echo e($p->title); ?></a></td><td>v<?php echo e($p->version); ?></td>
<td><?php if($a): ?><span class="status status-completed">Acknowledged <?php echo e($a->acknowledged_at->format('d M Y')); ?></span><?php else: ?><span class="status status-on-hold">Not yet</span><?php endif; ?></td>
<td><?php if (! ($a)): ?><form method="POST" action="<?php echo e(route('hr.policies.acknowledge', $p)); ?>"><?php echo csrf_field(); ?><input type="hidden" name="employee_ids[]" value="<?php echo e($e->id); ?>"><button class="btn btn-small">Record acknowledgement</button></form><?php endif; ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div><?php endif; ?></div>

<div class="tab-marker" data-tab-start="Files"></div>
<?php echo $__env->make('attachments._panel', ['type' => 'employee', 'id' => $e->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/employees/show.blade.php ENDPATH**/ ?>