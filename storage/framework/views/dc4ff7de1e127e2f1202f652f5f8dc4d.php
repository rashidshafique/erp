<?php $__env->startSection('title', $inspection->inspection_number); ?>
<?php $__env->startSection('content'); ?>
<?php $open = $inspection->status === 'IN_PROGRESS'; ?>
<div class="page-heading"><div><h1><?php echo e($inspection->inspection_number); ?></h1><p><?php echo e($inspection->inspection_type); ?> · lot <a href="<?php echo e(route('lots.show', $inspection->lot)); ?>"><?php echo e($inspection->lot->lot_number); ?></a> · <?php echo e($inspection->quantity_inspected); ?> piece(s) · <span class="status"><?php echo e($inspection->status); ?></span></p></div></div>
<?php if(session("success")): ?><div class="alert alert-success"><?php echo e(session("success")); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="alert alert-error"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
<div class="tab-marker" data-tab-start="Results"></div>
<div class="panel"><div class="panel-header"><h2>Characteristics</h2></div>
<form method="POST" action="<?php echo e(route('inspections.results.save', $inspection)); ?>"><?php echo csrf_field(); ?>
<div class="table-wrap"><table><thead><tr><th>Characteristic</th><th>Limits</th><th>Measured</th><th>Pass?</th><th>Defect if failed</th><th>Notes</th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $inspection->results; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><?php echo e($r->test_name); ?><div class="muted small"><?php echo e($r->test_method); ?></div></td><td><?php echo e($r->minimum_value ?? ''); ?> – <?php echo e($r->maximum_value ?? ''); ?> <?php echo e($r->unit); ?></td>
<td><?php if($r->result_type === 'NUMERIC'): ?><input type="number" step="any" name="results[<?php echo e($r->id); ?>][measured_value]" value="<?php echo e($r->measured_value); ?>" <?php if(!$open): echo 'disabled'; endif; ?>><?php else: ?><span class="muted small">—</span><?php endif; ?></td>
<td><?php if($r->result_type === 'NUMERIC'): ?><strong><?php echo e($r->passed === null ? '' : ($r->passed ? 'PASS' : 'FAIL')); ?></strong><?php else: ?><select name="results[<?php echo e($r->id); ?>][passed]" <?php if(!$open): echo 'disabled'; endif; ?>><option value="">—</option><option value="1" <?php if($r->passed === true): echo 'selected'; endif; ?>>Pass</option><option value="0" <?php if($r->passed === false): echo 'selected'; endif; ?>>Fail</option></select><?php endif; ?></td>
<td><select name="results[<?php echo e($r->id); ?>][quality_defect_id]" <?php if(!$open): echo 'disabled'; endif; ?>><option value="">—</option><?php $__currentLoopData = $defects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($d->id); ?>" <?php if($r->quality_defect_id === $d->id): echo 'selected'; endif; ?>><?php echo e($d->category); ?> · <?php echo e($d->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></td>
<td><input name="results[<?php echo e($r->id); ?>][notes]" value="<?php echo e($r->notes); ?>" <?php if(!$open): echo 'disabled'; endif; ?>></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="6" class="muted">No characteristics yet. Add them below.</td></tr><?php endif; ?></tbody></table></div>
<?php if($open && $inspection->results->isNotEmpty()): ?><div class="panel-body"><button class="btn btn-primary">Save results</button></div><?php endif; ?></form></div>

<div class="tab-marker" data-tab-start="Complete"></div>
<?php if($open): ?>
<div class="panel"><div class="panel-header"><h2>Add a characteristic</h2></div><div class="panel-body"><form method="POST" action="<?php echo e(route('inspections.results.add', $inspection)); ?>" class="form-grid"><?php echo csrf_field(); ?>
<label>Name<input name="test_name" required></label><label>Type<select name="result_type"><option value="NUMERIC">Numeric</option><option value="PASS_FAIL">Pass / fail</option></select></label>
<label>Min<input type="number" step="any" name="minimum_value"></label><label>Max<input type="number" step="any" name="maximum_value"></label><label>Unit<input name="unit"></label><label>Method<input name="test_method"></label>
<div><button class="btn">Add</button></div></form></div></div>

<div class="panel"><div class="panel-header"><h2>Complete inspection</h2></div><div class="panel-body"><p class="muted small">Save results first. The four quantities must add up to <?php echo e($inspection->quantity_inspected); ?>. Any rejected or rework piece, or failed result, raises an NCR automatically.</p>
<form method="POST" action="<?php echo e(route('inspections.complete', $inspection)); ?>" class="form-grid"><?php echo csrf_field(); ?>
<label>Accepted<input type="number" name="quantity_accepted" min="0" value="<?php echo e(old('quantity_accepted', $inspection->quantity_inspected)); ?>" required></label>
<label>Rejected<input type="number" name="quantity_rejected" min="0" value="<?php echo e(old('quantity_rejected', 0)); ?>" required></label>
<label>Rework<input type="number" name="quantity_rework" min="0" value="<?php echo e(old('quantity_rework', 0)); ?>" required></label>
<label>Pending<input type="number" name="quantity_pending" min="0" value="<?php echo e(old('quantity_pending', 0)); ?>" required></label>
<label>Disposition<select name="overall_disposition"><?php $__currentLoopData = ['ACCEPT','PARTIAL','REWORK','REJECT']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $x): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option><?php echo e($x); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
<label>NCR title (optional)<input name="ncr_title"></label><label>Remarks<input name="remarks"></label>
<div><button class="btn btn-primary">Complete</button></div></form></div></div>
<?php else: ?>
<div class="panel"><div class="panel-body">Disposition <strong><?php echo e($inspection->overall_disposition); ?></strong> — <?php echo e($inspection->quantity_accepted); ?> accepted, <?php echo e($inspection->quantity_rejected); ?> rejected, <?php echo e($inspection->quantity_rework); ?> rework, <?php echo e($inspection->quantity_pending); ?> pending.
<?php $__currentLoopData = $inspection->ncrs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?> <a href="<?php echo e(route('ncrs.show', $n)); ?>"><?php echo e($n->ncr_number); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></div>
<?php endif; ?>
<div class="tab-marker" data-tab-start="Files & history"></div>
<?php echo $__env->make('custom-fields._panel', ['entity' => 'inspection', 'id' => $inspection->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('attachments._panel', ['type' => 'inspection', 'id' => $inspection->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('audit._history', ['type' => 'QualityInspection', 'id' => $inspection->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/inspections/show.blade.php ENDPATH**/ ?>