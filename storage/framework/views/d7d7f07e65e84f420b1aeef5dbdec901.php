<?php $__env->startSection('title', 'Thermal tests'); ?>
<?php $__env->startSection('breadcrumb', 'Thermal tests'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div><h1>Thermal analysis</h1><p>Cooling-curve tests that verify iron before it is poured.</p></div>
    <div class="actions"><a class="btn btn-primary" href="<?php echo e(route('thermal.create')); ?>">+ Record test</a></div>
</div>
<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="alert alert-error"><?php echo e($errors->first()); ?></div><?php endif; ?>

<div class="panel">
    <div class="panel-header"><h2>TL to CE relation</h2></div>
    <div class="panel-body">
        <p>CE = (<?php echo e(number_format($relation['a'], 1)); ?> − TL) / <?php echo e(number_format($relation['b'], 1)); ?>

            — <?php echo e($relation['calibrated'] ? 'calibrated on '.$relation['n'].' heats' : 'uncalibrated default; calibrate from your own history'); ?>.</p>
        <?php if($calibration): ?><p class="muted small">Last fit <?php echo e($calibration->fitted_at->format('d M Y H:i')); ?>, R² <?php echo e($calibration->r_squared); ?>, residual SD <?php echo e($calibration->residual_sd_ce); ?> CE.</p><?php endif; ?>
        <form method="POST" action="<?php echo e(route('thermal.calibrate')); ?>"><?php echo csrf_field(); ?>
            <button class="btn" type="submit">Calibrate from history</button>
            <span class="muted small">Needs 8 heats with both a cooling curve and a chemistry reading, spanning at least 0.15 CE.</span></form>
    </div>
</div>

<div class="panel">
    <?php if($tests->isEmpty()): ?>
        <div class="empty-state">No tests yet.</div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Taken</th><th>Heat</th><th>Grade</th><th>Sample</th><th>Cup</th><th>C</th><th>Si</th><th>TL</th><th>TS</th><th>TEmin</th><th>Recal.</th><th>Peak</th><th>CE</th><th>Result</th></tr></thead>
            <tbody>
            <?php $__currentLoopData = $tests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><a href="<?php echo e(route('thermal.show', $t)); ?>"><?php echo e($t->taken_at->format('d M H:i')); ?></a></td>
                    <td><?php echo e($t->heat?->heat_number); ?></td><td><?php echo e($t->ironGrade?->code ?? '—'); ?></td><td><?php echo e($t->sample_reference ?: '—'); ?><?php if($t->entry_mode === 'MANUAL'): ?> <span class="muted small">(keyed in)</span><?php endif; ?></td>
                    <td><?php echo e($t->cup_type === 'TELLURIUM' ? 'White' : 'Grey'); ?></td><td><?php echo e($t->carbon_reported_pct ?? '—'); ?></td><td><?php echo e($t->silicon_used ?? '—'); ?></td>
                    <td><?php echo e($t->tl_c ?? '—'); ?></td><td><?php echo e($t->solidus_c ?? '—'); ?></td><td><?php echo e($t->te_min_c ?? '—'); ?></td><td><?php echo e($t->recalescence_c ?? '—'); ?></td><td><?php echo e($t->peak_temp_c ?? '—'); ?></td><td><?php echo e($t->ce_from_tl ?? '—'); ?></td>
                    <td><span class="status <?php echo e($t->result === 'PASS' ? 'status-completed' : ($t->result === 'FAIL' ? 'status-cancelled' : 'status-on-hold')); ?>"><?php echo e(str_replace('_', ' ', $t->result)); ?></span></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table></div>
        <div class="panel-body"><?php echo e($tests->links()); ?></div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/thermal/index.blade.php ENDPATH**/ ?>