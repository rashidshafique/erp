<?php
    $svc = app(\App\Services\Quality\Measurements::class);
    $sets = \App\Models\MeasurementSet::with('measurements')->where('heat_id', $heat->id)->whereIn('kind', ['MECHANICAL', 'MICROSTRUCTURE'])->orderByDesc('taken_at')->get();
?>
<div class="panel">
    <div class="panel-header"><h2>Mechanical and microstructure tests</h2></div>
    <div class="panel-body">
        <?php if($errors->has('test_MECHANICAL') || $errors->has('test_MICROSTRUCTURE')): ?>
            <div class="alert alert-error"><?php echo e($errors->first('test_MECHANICAL') ?: $errors->first('test_MICROSTRUCTURE')); ?></div>
        <?php endif; ?>
        <?php if($heat->ironGrade): ?>
            <p class="muted small">Limits come from grade <?php echo e($heat->ironGrade->code); ?> (set under Iron grades → Test requirements).</p>
        <?php else: ?>
            <p class="muted small">This heat has no iron grade, so results are recorded but not judged.</p>
        <?php endif; ?>
    </div>
    <?php if($sets->isNotEmpty()): ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Date</th><th>Kind</th><th>Sample</th><th>Results</th><th>Result</th></tr></thead>
            <tbody>
            <?php $__currentLoopData = $sets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr><td><?php echo e($s->taken_at->format('d M H:i')); ?></td><td><?php echo e($s->kind); ?></td>
                    <td><?php echo e($s->sample_reference); ?><div class="muted small"><?php echo e(\App\Services\Quality\Measurements::SAMPLE_TYPES[$s->sample_type] ?? ''); ?></div></td>
                    <td><?php $__currentLoopData = $s->measurements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo e($m->name); ?>: <strong><?php echo e($m->value !== null ? rtrim(rtrim(number_format($m->value, 4, '.', ''), '0'), '.') : $m->text_value); ?></strong> <?php echo e($m->unit); ?><?php if($m->passed === false): ?> <span style="color:var(--danger)">✘</span><?php elseif($m->passed): ?> ✔<?php endif; ?><br><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></td>
                    <td><span class="status <?php echo e($s->result === 'PASS' ? 'status-completed' : ($s->result === 'FAIL' ? 'status-cancelled' : '')); ?>"><?php echo e($s->result); ?></span></td></tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table></div>
    <?php endif; ?>
    <?php $__currentLoopData = ['MECHANICAL' => 'Record mechanical test', 'MICROSTRUCTURE' => 'Record microstructure']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kind => $title): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="panel-body" style="border-top:1px solid var(--border,#ddd)">
            <h3 style="font-size:15px"><?php echo e($title); ?></h3>
            <form method="POST" action="<?php echo e(route('heats.tests.store', [$heat, $kind])); ?>" class="form-grid"><?php echo csrf_field(); ?>
                <label>Sample reference<input name="sample_reference"></label>
                <label>Sample type<select name="sample_type"><option value="">—</option><?php $__currentLoopData = \App\Services\Quality\Measurements::SAMPLE_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>"><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                <label>Tested by<input name="tested_by"></label><label>Machine / equipment<input name="equipment_reference"></label>
                <?php $__currentLoopData = $svc->formRows($kind, 'IRON_GRADE', $heat->iron_grade_id); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <label><?php echo e($r['name']); ?><?php if($r['unit']): ?> (<?php echo e($r['unit']); ?>)<?php endif; ?>
                        <input name="values[<?php echo e($r['key']); ?>]" <?php if($r['numeric']): ?> type="number" step="any" <?php endif; ?>>
                        <?php if($r['min'] !== null || $r['max'] !== null): ?><span class="muted small">limit <?php echo e($r['min'] ?? ''); ?>–<?php echo e($r['max'] ?? ''); ?></span><?php endif; ?>
                    </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <div><button class="btn btn-primary">Save</button></div>
            </form>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/heats/_tests.blade.php ENDPATH**/ ?>