<?php $__env->startSection('title', $lot->lot_number); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1><?php echo e($lot->lot_number); ?></h1><p><?php echo e($lot->product?->code); ?> — <?php echo e($lot->product?->name); ?> · <span class="status"><?php echo e($lot->status); ?></span></p></div>
<div class="actions"><a class="btn" href="<?php echo e(route('lots.certificate', $lot)); ?>">Certificate</a>
<a class="btn btn-primary" href="<?php echo e(route('lots.inspections.create', $lot)); ?>">+ Inspection</a></div></div>
<?php if(session("success")): ?><div class="alert alert-success"><?php echo e(session("success")); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="alert alert-error"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
<div class="tab-marker" data-tab-start="Summary"></div>
<div class="panel"><div class="panel-header"><h2>Summary</h2></div><div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px">
<div><div class="muted small">Heat</div><?php if($heat): ?><a href="<?php echo e(route('heats.show', $heat)); ?>"><?php echo e($heat->heat_number); ?></a> (<?php echo e($heat->ironGrade?->code ?? 'no grade'); ?>)<?php else: ?> — <?php endif; ?></div>
<div><div class="muted small">Production order</div><?php echo e($lot->productionOrder?->production_order_number ?? '—'); ?></div>
<div><div class="muted small">Poured</div><strong><?php echo e($lot->quantity_poured); ?></strong></div>
<div><div class="muted small">Rejected</div><strong><?php echo e($lot->quantity_rejected); ?></strong></div>
<div><div class="muted small">Good</div><strong><?php echo e($lot->quantity_good); ?></strong></div>
<div><div class="muted small">Still in process</div><strong><?php echo e($pieces); ?></strong></div>
<div><div class="muted small">Packed</div><strong><?php echo e($packed); ?></strong></div>
<div><div class="muted small">Gross / net kg</div><?php echo e($lot->gross_weight_kg); ?> / <?php echo e($lot->net_casting_weight_kg); ?></div></div></div>

<div class="panel"><div class="panel-header"><h2>Dispatch readiness</h2><span class="status <?php echo e($readiness['ok'] ? 'status-completed' : 'status-cancelled'); ?>"><?php echo e($readiness['ok'] ? 'CLEAR' : 'NOT CLEAR'); ?></span></div>
<div class="panel-body"><ul><?php $__currentLoopData = $readiness['checks']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($c['ok'] === true ? '✔' : ($c['ok'] === false ? '✘' : '•')); ?> <strong><?php echo e($c['label']); ?></strong> — <?php echo e($c['detail']); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div></div>

<div class="tab-marker" data-tab-start="Stages"></div>
<div class="panel"><div class="panel-header"><h2>Shop-floor stages</h2></div>
<?php if($cooling): ?><div class="panel-body small">Cooling before shakeout: minimum <?php echo e($cooling['minimum']); ?> min from pour completion — <?php if($cooling['wait'] > 0): ?><strong>wait until <?php echo e($cooling['ready_at']->format('d M H:i')); ?></strong> (<?php echo e($cooling['wait']); ?> min left)<?php else: ?> <strong>cooling time met</strong><?php endif; ?></div><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>Stage</th><th>In</th><th>Accepted</th><th>Rejected</th><th>Reason</th><th>Operator</th><th>When</th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $lot->stageEvents->sortBy('created_at'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><?php echo e($e->stage?->name); ?></td><td><?php echo e($e->quantity_in); ?></td><td><?php echo e($e->quantity_accepted); ?></td><td><?php echo e($e->quantity_rejected); ?></td><td><?php echo e($e->rejection_reason); ?></td><td><?php echo e($e->operator_name); ?></td><td><?php echo e($e->created_at?->format('d M H:i')); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="7" class="muted">No stage recorded yet.</td></tr><?php endif; ?></tbody></table></div>
<?php if(in_array($lot->status, ['IN_PRODUCTION','ON_HOLD'])): ?>
<div class="panel-body"><form method="POST" action="<?php echo e(route('lots.stages.store', $lot)); ?>" class="form-grid"><?php echo csrf_field(); ?>
<label>Stage<select name="stage_definition_id" required><?php $__currentLoopData = $stages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s->id); ?>"><?php echo e($s->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
<label>Pieces in<input type="number" name="quantity_in" min="1" value="<?php echo e($pieces); ?>" required></label>
<label>Accepted<input type="number" name="quantity_accepted" min="0" value="<?php echo e($pieces); ?>" required></label>
<label>Rejected<input type="number" name="quantity_rejected" min="0" value="0" required></label>
<label>Reason if rejected<input name="rejection_reason" maxlength="100" placeholder="e.g. sand inclusion"></label>
<label>Early shakeout reason<input name="cooling_override_reason" maxlength="300" placeholder="only if cooling time not met"></label>
<label>Operator<input name="operator_name"></label><label>Shift<input name="shift_code"></label><label>Notes<input name="notes"></label>
<div><button class="btn btn-primary">Record stage</button></div></form></div><?php endif; ?></div>

<div class="tab-marker" data-tab-start="Quality & hold"></div>
<div class="panel"><div class="panel-header"><h2>Inspections and NCRs</h2></div><div class="table-wrap"><table><thead><tr><th>Record</th><th>Type</th><th>Status</th><th>Detail</th></tr></thead><tbody>
<?php $__currentLoopData = $lot->inspections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><a href="<?php echo e(route('inspections.show', $i)); ?>"><?php echo e($i->inspection_number); ?></a></td><td><?php echo e($i->inspection_type); ?></td><td><?php echo e($i->status); ?></td><td><?php echo e($i->quantity_accepted); ?> ok / <?php echo e($i->quantity_rejected); ?> rej / <?php echo e($i->quantity_rework); ?> rework</td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php $__currentLoopData = $lot->ncrs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><a href="<?php echo e(route('ncrs.show', $n)); ?>"><?php echo e($n->ncr_number); ?></a></td><td>NCR</td><td><?php echo e($n->status); ?></td><td><?php echo e($n->title); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php if($lot->inspections->isEmpty() && $lot->ncrs->isEmpty()): ?><tr><td colspan="4" class="muted">None yet.</td></tr><?php endif; ?></tbody></table></div></div>

<?php if(!in_array($lot->status, ['DISPATCHED','CLOSED','SCRAPPED'])): ?>
<div class="panel"><div class="panel-header"><h2>Hold</h2></div><div class="panel-body">
<?php if($lot->status === 'ON_HOLD'): ?><form method="POST" action="<?php echo e(route('lots.release', $lot)); ?>"><?php echo csrf_field(); ?><button class="btn">Release from hold</button></form>
<?php else: ?><form method="POST" action="<?php echo e(route('lots.hold', $lot)); ?>" style="display:flex;gap:10px"><?php echo csrf_field(); ?><input name="reason" placeholder="Reason for hold" required style="flex:1"><button class="btn btn-danger">Put on hold</button></form><?php endif; ?></div></div><?php endif; ?>
<div class="tab-marker" data-tab-start="Files & history"></div>
<?php echo $__env->make('custom-fields._panel', ['entity' => 'casting_lot', 'id' => $lot->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('attachments._panel', ['type' => 'casting_lot', 'id' => $lot->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('audit._history', ['type' => 'CastingLot', 'id' => $lot->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/lots/show.blade.php ENDPATH**/ ?>