<?php $__env->startSection('title', 'Sand lab'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Sand lab</h1><p>Test the sand each shift. A failed or missing test is shown on the moulding run and when a run is started.</p></div></div>
<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>
<?php if($errors->has('sand')): ?><div class="alert alert-error"><?php echo e($errors->first('sand')); ?></div><?php endif; ?>
<?php if($system): ?>
<div class="panel"><div class="panel-body"><form method="GET" style="display:flex;gap:10px;align-items:center">Sand system
<select name="system" onchange="this.form.submit()"><?php $__currentLoopData = $systems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s->id); ?>" <?php if($s->id === $system->id): echo 'selected'; endif; ?>><?php echo e($s->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
<span class="muted small"><?php echo e($system->description); ?></span></form></div></div>

<div class="panel"><div class="panel-header"><h2>Record a test</h2></div><div class="panel-body"><form method="POST" action="<?php echo e(route('sand-tests.store')); ?>" class="form-grid"><?php echo csrf_field(); ?>
<input type="hidden" name="sand_system_id" value="<?php echo e($system->id); ?>">
<label>Sample point<select name="sample_point"><option value="">—</option><?php $__currentLoopData = $points; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>"><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
<label>Station<select name="moulding_station_id"><option value="">—</option><?php $__currentLoopData = $stations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($st->id); ?>"><?php echo e($st->code); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
<label>Shift<select name="shift"><option value="">—</option><?php $__currentLoopData = ['Morning','Evening','Night','General']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $x): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option><?php echo e($x); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
<label>Tested by<input name="tested_by"></label>
<?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><label><?php echo e($r['name']); ?><?php if($r['unit']): ?> (<?php echo e($r['unit']); ?>)<?php endif; ?><input type="number" step="any" name="values[<?php echo e($r['key']); ?>]"><?php if($r['min'] !== null || $r['max'] !== null): ?><span class="muted small">limit <?php echo e($r['min'] ?? ''); ?>–<?php echo e($r['max'] ?? ''); ?></span><?php endif; ?></label><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<div><button class="btn btn-primary">Save test</button></div></form></div></div>

<div class="panel"><div class="panel-header"><h2>Recent tests</h2></div><div class="table-wrap"><table><thead><tr><th>When</th><th>Point</th><th>Shift</th><th>Results</th><th>Result</th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $sets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr><td><?php echo e($s->taken_at->format('d M H:i')); ?></td><td><?php echo e($s->sample_point); ?></td><td><?php echo e($s->shift); ?></td>
<td><?php $__currentLoopData = $s->measurements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo e($m->name); ?>: <strong><?php echo e(rtrim(rtrim(number_format((float) $m->value, 4, '.', ''), '0'), '.')); ?></strong> <?php echo e($m->unit); ?><?php if($m->passed === false): ?> <span style="color:var(--danger)">✘</span><?php endif; ?><br><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></td>
<td><span class="status <?php echo e($s->result === 'PASS' ? 'status-completed' : ($s->result === 'FAIL' ? 'status-cancelled' : '')); ?>"><?php echo e($s->result); ?></span></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5" class="muted">No tests yet.</td></tr><?php endif; ?></tbody></table></div></div>

<?php echo $__env->make('limits._panel', ['type' => 'SAND_SYSTEM', 'id' => $system->id, 'title' => 'Limits for '.$system->name.' sand'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/sand-tests/index.blade.php ENDPATH**/ ?>