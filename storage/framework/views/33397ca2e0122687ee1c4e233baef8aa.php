<?php
    $judge = app(\App\Services\Quality\ChemistryJudge::class);
    $readings = $heat->chemistryReadings()->get();
    $grade = $heat->ironGrade;
    $limitText = function ($key) use ($grade) {
        if (! $grade) return '';
        $min = $grade->{$key.'_min'}; $max = $grade->{$key.'_max'};
        if ($min === null && $max === null) return '';
        return ($min !== null ? rtrim(rtrim(number_format((float) $min, 3), '0'), '.') : '') . '–' . ($max !== null ? rtrim(rtrim(number_format((float) $max, 3), '0'), '.') : '');
    };
?>
<div class="panel" id="chemistry">
    <div class="panel-header"><h2>Chemical analysis</h2></div>
    <div class="panel-body">
        <p class="muted small"><?php echo e($grade ? 'Judged against '.$grade->code.'.' : 'This heat has no iron grade, so results are saved but not judged.'); ?>

            Carbon equivalent is worked out as C + (Si + P) / 3.</p>

        <?php if($readings->isNotEmpty()): ?>
        <div class="table-wrap"><table>
            <thead><tr><th>#</th><th>Stage</th><th>Tested</th>
                <?php $__currentLoopData = \App\Services\Quality\ChemistryJudge::COLUMNS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $col): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><th><?php echo e(\App\Models\IronGrade::ELEMENTS[$key]); ?><div class="muted small"><?php echo e($limitText($key)); ?></div></th><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <th>CE</th><th>Result</th><th></th></tr></thead>
            <tbody>
            <?php $__currentLoopData = $readings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php $v = $judge->judge($grade, $r->only(array_values(\App\Services\Quality\ChemistryJudge::COLUMNS)), $r->carbon_equivalent !== null ? (float) $r->carbon_equivalent : null); ?>
                <tr>
                    <td><?php echo e($r->reading_number); ?></td>
                    <td><?php echo e(str_replace('_', ' ', $r->sample_stage)); ?><div class="muted small"><?php echo e($r->sample_reference); ?></div></td>
                    <td><?php echo e($r->tested_at?->format('d M H:i')); ?><div class="muted small"><?php echo e($r->tested_by); ?></div></td>
                    <?php $__currentLoopData = \App\Services\Quality\ChemistryJudge::COLUMNS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $col): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><td><?php echo e($r->$col !== null ? rtrim(rtrim(number_format((float) $r->$col, 3), '0'), '.') : '—'); ?></td><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <td><?php echo e($r->carbon_equivalent !== null ? number_format((float) $r->carbon_equivalent, 2) : '—'); ?></td>
                    <td>
                        <?php if($v['ok'] === null): ?><span class="status">NOT JUDGED</span>
                        <?php elseif($v['ok']): ?><span class="status status-completed">IN LIMITS</span>
                        <?php else: ?><span class="status status-cancelled" title="<?php echo e(implode(' ', $v['findings'])); ?>">OUT OF LIMITS</span><?php endif; ?>
                        <?php if($r->is_verified): ?><div class="muted small">verified</div><?php endif; ?>
                    </td>
                    <td><?php if (! ($r->is_verified)): ?><form method="POST" action="<?php echo e(route('heats.chemistry.verify', [$heat, $r])); ?>"><?php echo csrf_field(); ?><button class="btn" type="submit">Verify</button></form><?php endif; ?></td>
                </tr>
                <?php if($v['ok'] === false): ?><tr><td></td><td colspan="<?php echo e(5 + count(\App\Services\Quality\ChemistryJudge::COLUMNS)); ?>" class="muted small" style="color:var(--danger)"><?php echo e(implode(' ', $v['findings'])); ?></td></tr><?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table></div>
        <?php else: ?>
            <p class="muted">No analysis recorded yet.</p>
        <?php endif; ?>

        <h3 style="margin-top:22px;font-size:15px">Record an analysis</h3>
        <?php $__errorArgs = ['chemistry'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="alert alert-error"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        <form method="POST" action="<?php echo e(route('heats.chemistry.store', $heat)); ?>"><?php echo csrf_field(); ?>
            <div class="form-grid">
                <div class="field"><label for="sample_stage">Sample taken *</label>
                    <select id="sample_stage" name="sample_stage"><?php $__currentLoopData = ['BASE' => 'Base iron', 'AFTER_TREATMENT' => 'After treatment', 'FINAL' => 'Final / ladle', 'PRE_POUR' => 'Pre-pour']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(old('sample_stage', 'AFTER_TREATMENT') === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
                <div class="field"><label for="test_method">Method *</label>
                    <select id="test_method" name="test_method"><?php $__currentLoopData = ['SPECTROMETER' => 'Spectrometer', 'CS_ANALYSER' => 'C/S analyser', 'WET_CHEMISTRY' => 'Wet chemistry', 'OTHER' => 'Other']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(old('test_method', 'SPECTROMETER') === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
                <div class="field"><label for="sample_reference">Sample reference</label><input id="sample_reference" name="sample_reference" maxlength="100" value="<?php echo e(old('sample_reference')); ?>"></div>
                <div class="field"><label for="tested_by">Tested by</label><input id="tested_by" name="tested_by" maxlength="100" value="<?php echo e(old('tested_by')); ?>"></div>
                <div class="field"><label for="instrument_reference">Instrument</label><input id="instrument_reference" name="instrument_reference" maxlength="100" value="<?php echo e(old('instrument_reference')); ?>"></div>
                <div class="field"><label for="tested_at">Tested at</label><input id="tested_at" name="tested_at" type="datetime-local" value="<?php echo e(old('tested_at')); ?>"></div>
                <?php $__currentLoopData = \App\Services\Quality\ChemistryJudge::COLUMNS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $col): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="field"><label for="<?php echo e($col); ?>"><?php echo e(\App\Models\IronGrade::ELEMENTS[$key]); ?> % <span class="muted small"><?php echo e($limitText($key)); ?></span></label>
                        <input id="<?php echo e($col); ?>" name="<?php echo e($col); ?>" type="number" step="0.0001" min="0" max="100" value="<?php echo e(old($col)); ?>"></div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <div class="field field-full"><label for="chem_notes">Notes</label><input id="chem_notes" name="notes" maxlength="2000" value="<?php echo e(old('notes')); ?>"></div>
            </div>
            <button class="btn btn-primary" type="submit">Save analysis</button>
        </form>
    </div>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/heats/_chemistry.blade.php ENDPATH**/ ?>