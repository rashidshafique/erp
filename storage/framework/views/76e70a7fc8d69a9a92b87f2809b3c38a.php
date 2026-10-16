<?php $trs = $heat->treatments()->get(); $types = \App\Models\HeatTreatment::TYPES; ?>
<div class="panel">
    <div class="panel-header"><h2>Metal treatment and inoculation</h2></div>
    <div class="panel-body">
        <?php if($errors->has('treatment')): ?><div class="alert alert-error"><?php echo e($errors->first('treatment')); ?></div><?php endif; ?>
        <?php if($heat->ironGrade): ?>
            <p class="muted small">Grade <?php echo e($heat->ironGrade->code); ?>:
                <?php echo e($heat->ironGrade->needs_mg_treatment ? 'needs magnesium treatment' : 'no magnesium treatment'); ?>,
                <?php echo e($heat->ironGrade->needs_inoculation ? 'needs inoculation' : 'no inoculation required'); ?>.
                Fade limit <?php echo e(\App\Http\Controllers\ProcessControlController::maxFadeMinutes()); ?> min.</p>
        <?php endif; ?>
    </div>
    <?php if($trs->isNotEmpty()): ?>
        <div class="table-wrap"><table><thead><tr><th>Time</th><th>Treatment</th><th>Material</th><th>Amount</th><th>Method</th><th>Temp</th><th>Recovery</th><th>By</th><th></th></tr></thead><tbody>
            <?php $__currentLoopData = $trs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr><td><?php echo e($t->treated_at->format('d M H:i')); ?></td><td><?php echo e($types[$t->treatment_type]); ?></td><td><?php echo e($t->material); ?></td>
                    <td><?php echo e($t->amount_kg); ?> kg <?php if($t->addition_pct): ?><div class="muted small"><?php echo e($t->addition_pct); ?> %</div><?php endif; ?></td>
                    <td><?php echo e(\App\Models\HeatTreatment::METHODS[$t->method] ?? ''); ?></td><td><?php echo e($t->temperature_c); ?></td><td><?php echo e($t->recovery_pct); ?></td><td><?php echo e($t->treated_by); ?></td>
                    <td><form method="POST" action="<?php echo e(route('heats.treatments.destroy', [$heat, $t])); ?>"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-danger">Remove</button></form></td></tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody></table></div>
    <?php endif; ?>
    <div class="panel-body" style="border-top:1px solid var(--border,#ddd)">
        <form method="POST" action="<?php echo e(route('heats.treatments.store', $heat)); ?>" class="form-grid"><?php echo csrf_field(); ?>
            <label>Treatment<select name="treatment_type"><?php $__currentLoopData = $types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>"><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
            <label>Time<input type="datetime-local" name="treated_at"><span class="muted small">Leave empty for now</span></label>
            <label>Material<input name="material" placeholder="FeSiMg 6%, FeSi75..."></label>
            <label>Amount (kg)<input type="number" step="any" name="amount_kg"></label>
            <label>Metal treated (kg)<input type="number" step="any" name="metal_treated_kg"><span class="muted small">Gives the addition %</span></label>
            <label>Method<select name="method"><option value="">—</option><?php $__currentLoopData = \App\Models\HeatTreatment::METHODS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>"><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
            <label>Metal temperature °C<input type="number" step="any" name="temperature_c"></label>
            <label>Ladle<input name="ladle_reference"></label>
            <label>Mg recovery %<input type="number" step="any" name="recovery_pct"></label>
            <label>Treated by<input name="treated_by"></label>
            <div><button class="btn btn-primary">Record treatment</button></div>
        </form>
    </div>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/heats/_treatment.blade.php ENDPATH**/ ?>