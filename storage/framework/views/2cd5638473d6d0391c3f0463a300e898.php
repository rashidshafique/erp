<?php $__env->startSection('title', 'Import punches'); ?>
<?php $__env->startSection('breadcrumb', 'Import punches'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Import punches</h1><p>Load a log from a fingerprint, card or face terminal.</p></div></div>
<?php echo $__env->make('hr.attendance._tabs', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if(session('error')): ?><div class="alert alert-error"><?php echo e(session('error')); ?></div><?php endif; ?>
<?php if(isset($result)): ?>
<div class="panel"><div class="panel-header"><h2>Result</h2></div><div class="panel-body">
<p><strong><?php echo e($result['saved']); ?></strong> day(s) saved from <?php echo e($result['punches']); ?> punches.</p>
<?php if($result['unknown']): ?><p class="alert alert-error">Not an employee code: <?php echo e(implode(', ', $result['unknown'])); ?></p><?php endif; ?>
<?php $__currentLoopData = array_merge($result['problems'], $result['bad']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p class="small muted"><?php echo e($m); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<p><a class="btn" href="<?php echo e(route('hr.attendance')); ?>">Open the daily sheet</a> <a class="btn" href="<?php echo e(route('hr.attendance.exceptions')); ?>">Exceptions</a></p></div></div>
<?php endif; ?>
<div class="panel"><div class="panel-body">
<p class="muted">One punch per line: <code>EMP-00012, 2026-10-13 08:04</code> (or code, date, time in three columns). A heading line is fine. The first punch of a day is the check-in and the last is the check-out. A punch just after a night shift ends goes to the shift that started the evening before. Importing again replaces the times for those days.</p>
<form method="POST" action="<?php echo e(route('hr.attendance.import.run')); ?>" enctype="multipart/form-data"><?php echo csrf_field(); ?><div class="form-grid">
    <div class="field"><label for="file">CSV or text file</label><input id="file" type="file" name="file" accept=".csv,.txt"></div>
    <div class="field field-full"><label for="pasted">…or paste the log</label><textarea id="pasted" name="pasted" rows="8" placeholder="EMP-00001, 2026-10-13 07:58&#10;EMP-00001, 2026-10-13 17:31"></textarea></div>
</div><p><button class="btn btn-primary">Import</button></p></form></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/attendance/import.blade.php ENDPATH**/ ?>