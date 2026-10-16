<?php $__env->startSection('title', $ncr->ncr_number); ?>
<?php $__env->startSection('content'); ?>
<?php $closed = $ncr->status === 'CLOSED'; ?>
<div class="page-heading"><div><h1><?php echo e($ncr->ncr_number); ?></h1><p><?php echo e($ncr->title); ?> · <?php echo e($ncr->severity); ?> · <span class="status"><?php echo e($ncr->status); ?></span></p></div></div>
<?php if(session("success")): ?><div class="alert alert-success"><?php echo e(session("success")); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="alert alert-error"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
<div class="tab-marker" data-tab-start="Investigation"></div>
<div class="panel"><div class="panel-body"><p><?php echo e($ncr->description); ?></p>
<p class="muted small">Lot <a href="<?php echo e(route('lots.show', $ncr->lot)); ?>"><?php echo e($ncr->lot?->lot_number); ?></a> · <?php echo e($ncr->quantity_affected); ?> piece(s) affected <?php if($ncr->defect): ?> · defect: <?php echo e($ncr->defect->name); ?> <?php endif; ?></p></div></div>
<div class="panel"><div class="panel-header"><h2>Investigation and disposition</h2></div><div class="panel-body">
<form method="POST" action="<?php echo e(route('ncrs.update', $ncr)); ?>" class="form-grid"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
<label>Containment<textarea name="containment_action" <?php if($closed): echo 'disabled'; endif; ?>><?php echo e(old('containment_action', $ncr->containment_action)); ?></textarea></label>
<label>Root cause<textarea name="root_cause" <?php if($closed): echo 'disabled'; endif; ?>><?php echo e(old('root_cause', $ncr->root_cause)); ?></textarea></label>
<label>Corrective action<textarea name="corrective_action" <?php if($closed): echo 'disabled'; endif; ?>><?php echo e(old('corrective_action', $ncr->corrective_action)); ?></textarea></label>
<label>Preventive action<textarea name="preventive_action" <?php if($closed): echo 'disabled'; endif; ?>><?php echo e(old('preventive_action', $ncr->preventive_action)); ?></textarea></label>
<label>Disposition<select name="disposition" <?php if($closed): echo 'disabled'; endif; ?>><option value="">—</option><?php $__currentLoopData = $dispositions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option <?php if($ncr->disposition === $d): echo 'selected'; endif; ?>><?php echo e($d); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
<label>Assigned to<input name="assigned_to" value="<?php echo e(old('assigned_to', $ncr->assigned_to)); ?>" <?php if($closed): echo 'disabled'; endif; ?>></label>
<?php if (! ($closed)): ?><div><button class="btn btn-primary">Save</button></div><?php endif; ?></form></div></div>
<?php if (! ($closed)): ?><div class="panel"><div class="panel-header"><h2>Close</h2></div><div class="panel-body"><form method="POST" action="<?php echo e(route('ncrs.close', $ncr)); ?>" class="form-grid"><?php echo csrf_field(); ?>
<label>Closed by<input name="closed_by" required></label><label>Verification notes<input name="verification_notes"></label><div><button class="btn btn-danger">Close NCR</button></div></form></div></div>
<?php else: ?><div class="panel"><div class="panel-body">Closed <?php echo e($ncr->closed_at?->format('d M Y H:i')); ?> by <?php echo e($ncr->closed_by); ?>. <?php echo e($ncr->verification_notes); ?></div></div><?php endif; ?>
<div class="tab-marker" data-tab-start="Files & history"></div>
<?php echo $__env->make('custom-fields._panel', ['entity' => 'ncr', 'id' => $ncr->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('attachments._panel', ['type' => 'ncr', 'id' => $ncr->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('audit._history', ['type' => 'NonconformanceReport', 'id' => $ncr->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/ncrs/show.blade.php ENDPATH**/ ?>