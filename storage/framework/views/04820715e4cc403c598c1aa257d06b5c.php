<?php $__env->startSection('title', 'People overview'); ?>
<?php $__env->startSection('breadcrumb', 'People'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>People</h1><p>Headcount, what needs attention and recent movements.</p></div>
<div class="actions"><a class="btn" href="<?php echo e(route('directory.index')); ?>">Directory</a><a class="btn btn-primary" href="<?php echo e(route('hr.employees.create')); ?>">+ New employee</a></div></div>
<div class="metric-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:16px">
<?php $__currentLoopData = [['Headcount', $stats['headcount']], ['Onboarding', $stats['onboarding']], ['On probation', $stats['probation']], ['Serving notice', $stats['notice']], ['Joined this month', $stats['joined']], ['Left this month', $stats['left']]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$l, $n]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div class="metric"><div class="metric-label"><?php echo e($l); ?></div><div class="metric-value"><?php echo e(number_format($n)); ?></div></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<div class="tab-marker" data-tab-start="Needs attention"></div>
<?php if($work['today']->isNotEmpty()): ?><p class="muted small" style="margin:0 0 10px">Today: <?php $__currentLoopData = $work['today']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo e(config('hr.attendance.day_status')[$k] ?? $k); ?> <?php echo e($n); ?><?php echo e(! $loop->last ? ' · ' : ''); ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></p><?php endif; ?>
<?php $none = collect($alerts)->every(fn ($a) => $a->isEmpty()) && $policyGaps->isEmpty() && ! $work['exceptions'] && ! $work['overtime'] && ! $work['leave']; ?>
<?php if($work['exceptions'] || $work['overtime'] || $work['leave']): ?>
<div class="panel"><div class="panel-header"><h2>Waiting for HR</h2></div><div class="panel-body">
<?php if($work['leave']): ?><a class="btn" href="<?php echo e(route('hr.leave')); ?>"><?php echo e($work['leave']); ?> leave request(s) to decide</a> <?php endif; ?>
<?php if($work['exceptions']): ?><a class="btn" href="<?php echo e(route('hr.attendance.exceptions')); ?>"><?php echo e($work['exceptions']); ?> attendance exception(s)</a> <?php endif; ?>
<?php if($work['overtime']): ?><a class="btn" href="<?php echo e(route('hr.attendance.overtime')); ?>"><?php echo e($work['overtime']); ?> overtime entr<?php echo e($work['overtime'] === 1 ? 'y' : 'ies'); ?> to approve</a> <?php endif; ?>
</div></div>
<?php endif; ?>
<?php if($none): ?><div class="panel"><div class="empty-state">Nothing needs attention.</div></div><?php endif; ?>
<?php if($alerts['probation']->isNotEmpty()): ?>
<div class="panel"><div class="panel-header"><h2>Probation ending</h2><span class="muted small">Next <?php echo e(config('hr.alert_days')); ?> days</span></div><div class="table-wrap"><table><tbody>
<?php $__currentLoopData = $alerts['probation']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $x): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><a href="<?php echo e(route('hr.employees.show', $x)); ?>"><?php echo e($x->code); ?> · <?php echo e($x->full_name); ?></a></td><td><?php echo e($x->probation_ends_on->format('d M Y')); ?></td><td><?php echo $x->probation_ends_on->isPast() ? '<span class="status status-cancelled">Overdue</span>' : ''; ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div></div><?php endif; ?>
<?php if($alerts['contracts']->isNotEmpty()): ?>
<div class="panel"><div class="panel-header"><h2>Contracts ending</h2></div><div class="table-wrap"><table><tbody>
<?php $__currentLoopData = $alerts['contracts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $x): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><a href="<?php echo e(route('hr.employees.show', $x)); ?>"><?php echo e($x->code); ?> · <?php echo e($x->full_name); ?></a></td><td><?php echo e($x->contract_ends_on->format('d M Y')); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div></div><?php endif; ?>
<?php if($alerts['documents']->isNotEmpty()): ?>
<div class="panel"><div class="panel-header"><h2>Documents expiring</h2></div><div class="table-wrap"><table><tbody>
<?php $__currentLoopData = $alerts['documents']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><a href="<?php echo e(route('hr.employees.show', $d->employee)); ?>"><?php echo e($d->employee->full_name); ?></a></td><td><?php echo e(config('hr.document_kinds')[$d->kind] ?? $d->kind); ?><?php echo e($d->number ? ' '.$d->number : ''); ?></td><td><?php echo e($d->expires_on->format('d M Y')); ?></td><td><?php echo $d->expiryState() === 'EXPIRED' ? '<span class="status status-cancelled">Expired</span>' : '<span class="status status-on-hold">Soon</span>'; ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div></div><?php endif; ?>
<?php if($alerts['tasks']->isNotEmpty()): ?>
<div class="panel"><div class="panel-header"><h2>Overdue checklist tasks</h2></div><div class="table-wrap"><table><tbody>
<?php $__currentLoopData = $alerts['tasks']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><a href="<?php echo e(route('hr.employees.show', $t->employee)); ?>"><?php echo e($t->employee->full_name); ?></a></td><td><?php echo e($t->title); ?></td><td><?php echo e(\App\Models\EmployeeTask::OWNERS[$t->owner] ?? $t->owner); ?></td><td><span class="status status-cancelled"><?php echo e($t->due_on->format('d M')); ?></span></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div></div><?php endif; ?>
<?php if($alerts['exits']->isNotEmpty()): ?>
<div class="panel"><div class="panel-header"><h2>People leaving</h2></div><div class="table-wrap"><table><tbody>
<?php $__currentLoopData = $alerts['exits']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $x): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><a href="<?php echo e(route('hr.exits.show', $x)); ?>"><?php echo e($x->employee->code); ?> · <?php echo e($x->employee->full_name); ?></a></td><td><?php echo e(config('hr.exit_types')[$x->exit_type]); ?></td><td>last day <?php echo e($x->last_working_day->format('d M Y')); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div></div><?php endif; ?>
<?php if($policyGaps->isNotEmpty()): ?>
<div class="panel"><div class="panel-header"><h2>Policies waiting for acknowledgement</h2></div><div class="table-wrap"><table><tbody>
<?php $__currentLoopData = $policyGaps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><a href="<?php echo e(route('hr.policies.show', $g['policy'])); ?>"><?php echo e($g['policy']->code); ?> · <?php echo e($g['policy']->title); ?></a></td><td><?php echo e($g['missing']); ?> still to acknowledge</td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div></div><?php endif; ?>

<div class="tab-marker" data-tab-start="Headcount"></div>
<div class="panel"><div class="panel-header"><h2>By department</h2></div><div class="panel-body">
<?php $max = max(1, $depts->max('n')); ?>
<?php $__empty_1 = true; $__currentLoopData = $depts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<div style="display:flex;align-items:center;gap:12px;margin:6px 0"><div style="width:200px"><?php echo e($d['name']); ?></div><div style="flex:1;max-width:420px;background:#eef2f4;border-radius:5px;height:14px"><div style="width:<?php echo e(round($d['n'] / $max * 100)); ?>%;background:var(--accent);height:14px;border-radius:5px"></div></div><strong><?php echo e($d['n']); ?></strong></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="empty-state">No employees yet.</div><?php endif; ?>
<?php if($unassigned): ?><p class="muted small"><?php echo e($unassigned); ?> without a department.</p><?php endif; ?>
</div></div>
<div class="panel"><div class="panel-header"><h2>By employment type</h2></div><div class="panel-body">
<?php $__currentLoopData = config('hr.employment_types'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(($byType[$k] ?? 0) > 0): ?><div style="display:flex;gap:12px;margin:4px 0"><div style="width:200px"><?php echo e($l); ?></div><strong><?php echo e($byType[$k]); ?></strong></div><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div></div>

<div class="tab-marker" data-tab-start="Recent movements"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Date</th><th>Who</th><th>Event</th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $recent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><?php echo e($r->effective_on->format('d M Y')); ?></td><td><a href="<?php echo e(route('hr.employees.show', $r->employee_id)); ?>"><?php echo e($r->employee?->full_name); ?></a></td><td><?php echo e(\App\Models\EmployeeEvent::KINDS[$r->kind] ?? $r->kind); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="3" class="muted">Nothing yet.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/dashboard.blade.php ENDPATH**/ ?>