<?php $__env->startSection('title', 'Exit '.$e->code); ?>
<?php $__env->startSection('breadcrumb'); ?><a href="<?php echo e(route('hr.exits.index')); ?>">Exits</a> / <?php echo e($e->code); ?> <?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<?php $open = $x->status === 'INITIATED'; $tasks = $e->tasks->where('kind', 'EXIT'); ?>
<div class="page-heading"><div><h1><?php echo e($e->full_name); ?> is leaving</h1><p><?php echo e($e->code); ?> · <?php echo e(config('hr.exit_types')[$x->exit_type]); ?> · last day <?php echo e($x->last_working_day->format('d M Y')); ?> · <?php echo $__env->make('hr._chip', ['s' => $x->status], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></p></div>
<div class="actions"><a class="btn" href="<?php echo e(route('hr.employees.show', $e)); ?>">Employee record</a></div></div>
<?php if(session('error')): ?><div class="alert alert-error"><?php echo e(session('error')); ?></div><?php endif; ?>

<div class="tab-marker" data-tab-start="Clearance"></div>
<div class="panel"><div class="panel-header"><h2>Clearance checklist</h2><span class="muted small"><?php echo e($tasks->whereNotNull('done_at')->count()); ?> of <?php echo e($tasks->count()); ?> done</span></div>
<div class="table-wrap"><table><thead><tr><th style="width:44px"></th><th>Task</th><th>Owner</th><th>Done</th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr><td><?php if($open): ?><form method="POST" action="<?php echo e(route('hr.tasks.toggle', $t)); ?>"><?php echo csrf_field(); ?><button class="btn btn-small"><?php echo $t->done_at ? '&#10003;' : '&#9675;'; ?></button></form><?php else: ?><?php echo $t->done_at ? '&#10003;' : ''; ?><?php endif; ?></td>
<td <?php if($t->done_at): ?> style="text-decoration:line-through;color:var(--muted)" <?php endif; ?>><?php echo e($t->title); ?></td><td><?php echo e(\App\Models\EmployeeTask::OWNERS[$t->owner] ?? $t->owner); ?></td><td class="muted small"><?php echo e($t->done_at ? $t->done_at->format('d M Y').' · '.$t->done_by_name : ''); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="4" class="muted">No checklist items.</td></tr><?php endif; ?></tbody></table></div></div>
<?php if($open): ?>
<div class="panel"><div class="panel-body">
<?php if($blockers): ?><p class="muted">Cannot complete yet: <?php echo e(implode('; ', $blockers)); ?>.</p><?php endif; ?>
<form method="POST" action="<?php echo e(route('hr.exits.complete', $x)); ?>" style="display:inline" onsubmit="return confirm('Complete the exit? The person is marked as left and any linked login is switched off.')"><?php echo csrf_field(); ?><button class="btn btn-primary" <?php if($blockers): ?> disabled <?php endif; ?>>Complete exit</button></form>
<form method="POST" action="<?php echo e(route('hr.exits.cancel', $x)); ?>" style="display:inline" onsubmit="return confirm('Cancel this exit and keep the person?')"><?php echo csrf_field(); ?><button class="btn">Cancel exit</button></form>
</div></div><?php endif; ?>

<?php if(\App\Support\HrAccess::isPayroll()): ?>
<?php $st = $x->settlement; ?>
<div class="tab-marker" data-tab-start="Final settlement"></div>
<div class="panel"><div class="panel-header"><h2>Final settlement</h2></div><div class="panel-body">
<?php if($st): ?><p>Status: <strong><?php echo e($st->status); ?></strong> · net <?php echo e(number_format($st->net)); ?> PKR. <a class="btn btn-small" href="<?php echo e(route('hr.payroll.settlements.show', $st)); ?>">Open settlement</a></p>
<?php else: ?><p class="muted">Salary not yet paid, leave, gratuity and any loan balance are worked out here. It can be started before or after the exit is completed.</p><form method="POST" action="<?php echo e(route('hr.payroll.settlements.open', $x)); ?>"><?php echo csrf_field(); ?><button class="btn btn-primary">Calculate settlement</button></form><?php endif; ?>
</div></div>
<?php endif; ?>

<div class="tab-marker" data-tab-start="Details"></div>
<div class="panel"><div class="panel-body">
<form method="POST" action="<?php echo e(route('hr.exits.update', $x)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?><fieldset <?php if (! ($open)): ?> disabled <?php endif; ?> style="border:0;padding:0;margin:0"><div class="form-grid">
    <?php echo $__env->make('hr._in', ['name' => 'notice_given_on', 'label' => 'Notice given on', 'type' => 'date', 'val' => $x->notice_given_on?->toDateString()], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'last_working_day', 'label' => 'Last working day', 'type' => 'date', 'val' => $x->last_working_day->toDateString(), 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'rehire_eligible', 'label' => 'Eligible for rehire', 'items' => ['1' => 'Yes', '0' => 'No'], 'sel' => $x->rehire_eligible === null ? '' : (int) $x->rehire_eligible, 'blank' => 'Not decided'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="field field-full"><label for="f_reason">Reason</label><input id="f_reason" name="reason" value="<?php echo e(old('reason', $x->reason)); ?>"></div>
    <div class="field field-full"><label for="f_ei">Exit interview notes</label><textarea id="f_ei" name="exit_interview" rows="5"><?php echo e(old('exit_interview', $x->exit_interview)); ?></textarea></div>
</div><?php if($open): ?><p><button class="btn btn-primary">Save</button></p><?php endif; ?></fieldset></form>
<p class="muted small">Final settlement: <?php echo e($x->settlement_status === 'PENDING' ? 'not prepared yet (comes with payroll)' : strtolower($x->settlement_status)); ?>.</p>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/exits/show.blade.php ENDPATH**/ ?>