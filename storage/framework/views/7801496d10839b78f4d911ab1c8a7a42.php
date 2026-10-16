<?php
    $gate = app(\App\Services\QualityGates::class)->pourGate($pour->heat);
    $grade = $pour->heat?->ironGrade;
?>
<div class="panel">
    <div class="panel-header">
        <h2>Pouring gate</h2>
        <span class="status <?php echo e($gate['ok'] ? 'status-completed' : 'status-cancelled'); ?>"><?php echo e($gate['ok'] ? 'CLEAR' : 'BLOCKED'); ?></span>
    </div>
    <div class="panel-body">
        <?php if($errors->has('gate') || $errors->has('complete')): ?>
            <div class="alert alert-error"><?php echo e($errors->first('gate') ?: $errors->first('complete')); ?></div>
        <?php endif; ?>

        <p class="muted small">
            Heat <?php echo e($pour->heat?->heat_number); ?> — grade <?php echo e($grade?->code ?? 'none set'); ?>.
            A heat with a grade needs a chemistry reading in the grade window and a recent passing thermal test.
        </p>
        <ul>
            <?php $__currentLoopData = $gate['checks']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li>
                    <strong><?php echo e($c['label']); ?>:</strong>
                    <?php echo e($c['ok'] === true ? 'OK' : ($c['ok'] === false ? 'NOT OK' : 'n/a')); ?> — <?php echo e($c['detail']); ?>

                </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>

        <?php if($pour->heat): ?>
            <a class="btn" href="<?php echo e(route('thermal.create', ['heat_id' => $pour->heat_id])); ?>">Record thermal test</a>
        <?php endif; ?>

        <?php if($pour->status === 'PLANNED'): ?>
            <form method="POST" action="<?php echo e(route('pours.start', $pour)); ?>" style="display:inline">
                <?php echo csrf_field(); ?>
                <button class="btn btn-primary" type="submit" <?php if(! $gate['ok']): echo 'disabled'; endif; ?>>Start pour</button>
            </form>
        <?php elseif($pour->status === 'IN_PROGRESS'): ?>
            <form method="POST" action="<?php echo e(route('pours.complete', $pour)); ?>" style="margin-top:14px">
                <?php echo csrf_field(); ?>
                <div class="form-grid">
                    <div class="field">
                        <label for="pour_temperature_c">Pour temperature (°C) *</label>
                        <input id="pour_temperature_c" name="pour_temperature_c" type="number" step="1" min="1000" max="1700" required value="<?php echo e(old('pour_temperature_c')); ?>">
                        <?php if($grade && ($grade->pour_temp_min_c || $grade->pour_temp_max_c)): ?>
                            <span class="muted small">Window <?php echo e($grade->pour_temp_min_c ?? '—'); ?> to <?php echo e($grade->pour_temp_max_c ?? '—'); ?> °C</span>
                        <?php endif; ?>
                        <?php $__errorArgs = ['pour_temperature_c'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="error-text"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="field">
                        <label for="total_poured_weight_kg">Total poured weight (kg) *</label>
                        <input id="total_poured_weight_kg" name="total_poured_weight_kg" type="number" step="0.1" min="0.1" required value="<?php echo e(old('total_poured_weight_kg')); ?>">
                        <?php $__errorArgs = ['total_poured_weight_kg'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="error-text"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="field field-full">
                        <label for="out_of_window_reason">Reason, only if the temperature is outside the window</label>
                        <input id="out_of_window_reason" name="out_of_window_reason" maxlength="500" value="<?php echo e(old('out_of_window_reason')); ?>">
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">Complete pour</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/pours/_gate.blade.php ENDPATH**/ ?>