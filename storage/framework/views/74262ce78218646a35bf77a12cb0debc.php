<?php $__env->startSection('title', $tool->code); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1><?php echo e($tool->name); ?></h1><p><?php echo e($label); ?> · <?php echo e($tool->code); ?></p></div><a class="btn" href="<?php echo e(route('labels.show', [$type, $tool->id])); ?>" target="_blank">Print label</a></div>
<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="alert alert-error"><?php echo e($errors->first()); ?></div><?php endif; ?>
<div class="tab-marker" data-tab-start="Tool"></div>
<div class="panel"><div class="panel-header"><h2><?php echo e($label); ?></h2></div><div class="panel-body">
<?php echo $__env->make('attachments._cover', ['type' => $type, 'id' => $tool->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('tools._mark', ['tool' => $tool, 'type' => $type], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="muted small" style="margin-top:8px">Barcode</div><?php echo \App\Support\Code128::svg($tool->code, 44, 2); ?>

</div></div>
<div class="panel"><div class="panel-header"><h2>Maintenance records</h2></div><div class="table-wrap"><table><thead><tr><th>Record</th><th>Activity</th><th>Status</th><th>Reported</th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $records; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><a href="<?php echo e(route('tooling-maintenance.show', $r)); ?>"><?php echo e($r->record_number); ?></a></td><td><?php echo e(str_replace('_',' ',$r->activity_type)); ?></td><td><?php echo e($r->status); ?></td><td><?php echo e($r->reported_at?->format('d M Y')); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="4" class="muted">No maintenance recorded.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="tab-marker" data-tab-start="Files & history"></div>
<?php echo $__env->make('attachments._panel', ['type' => $type, 'id' => $tool->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('audit._history', ['type' => class_basename($tool), 'id' => $tool->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/tools/show.blade.php ENDPATH**/ ?>