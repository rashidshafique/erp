<?php $__env->startSection('title', 'Record thermal test'); ?>
<?php $__env->startSection('breadcrumb', 'Thermal tests / New'); ?>

<?php
    $f = fn ($cup, $k) => old($cup.'.'.$k);
?>

<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Record thermal test</h1><p>Key in the readings from the thermal analysis unit for the white and/or grey cup. Add the full cooling curve when you have it.</p></div></div>
<?php if($errors->any()): ?><div class="alert alert-error"><ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div><?php endif; ?>

<form method="POST" action="<?php echo e(route('thermal.store')); ?>" enctype="multipart/form-data"><?php echo csrf_field(); ?>
<div class="panel"><div class="panel-header"><h2>Sample</h2></div><div class="panel-body"><div class="form-grid" data-progressive="3">
    <div class="field"><label for="heat_id">Heat *</label>
        <select id="heat_id" name="heat_id" required><option value="">Select heat</option>
            <?php $__currentLoopData = $heats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($h->id); ?>" <?php if(old('heat_id', $selected) === $h->id): echo 'selected'; endif; ?>><?php echo e($h->heat_number); ?> — <?php echo e($h->furnace?->code); ?> — <?php echo e($h->ironGrade?->code ?? 'no grade'); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select></div>
    <div class="field"><label for="sample_reference">Sample reference</label><input id="sample_reference" name="sample_reference" maxlength="60" value="<?php echo e(old('sample_reference')); ?>"></div>
    <div class="field"><label for="stage">Stage *</label>
        <select id="stage" name="stage"><?php $__currentLoopData = ['BASE' => 'Base iron (before treatment)', 'POST_TREATMENT' => 'After treatment / inoculation', 'PRE_POUR' => 'Pre-pour check']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(old('stage', 'POST_TREATMENT') === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <div class="field"><label for="taken_by">Taken by</label><input id="taken_by" name="taken_by" maxlength="100" value="<?php echo e(old('taken_by')); ?>"></div>
    <div class="field"><label for="inoculant_added_pct">Inoculant added (%)</label><input id="inoculant_added_pct" name="inoculant_added_pct" type="number" step="0.01" min="0" max="2" value="<?php echo e(old('inoculant_added_pct')); ?>"></div>
    <div class="field"><label for="notes">Notes</label><input id="notes" name="notes" maxlength="2000" value="<?php echo e(old('notes')); ?>"></div>
</div></div></div>

<div class="cup-grid">
<div class="panel"><div class="panel-header"><h2>White cup <span class="muted small">(tellurium)</span></h2></div><div class="panel-body">
    <p class="muted small">The iron solidifies white, giving carbon, silicon and the liquidus and solidus. Leave the whole card empty if no white cup was run.</p>
    <div class="form-grid" data-progressive="3">
        <div class="field"><label for="w_c">Carbon % (C)</label><input id="w_c" name="white[carbon]" type="number" step="0.01" min="0" max="5" value="<?php echo e($f('white', 'carbon')); ?>"></div>
        <div class="field"><label for="w_si">Silicon % (Si)</label><input id="w_si" name="white[silicon]" type="number" step="0.01" min="0" max="6" value="<?php echo e($f('white', 'silicon')); ?>"><span class="muted small">Left empty, the latest chemistry reading is used.</span></div>
        <div class="field"><label for="w_tl">TL, liquidus (°C)</label><input id="w_tl" name="white[tl_c]" type="number" step="0.1" value="<?php echo e($f('white', 'tl_c')); ?>"></div>
        <div class="field"><label for="w_ts">TS, solidus (°C)</label><input id="w_ts" name="white[solidus_c]" type="number" step="0.1" value="<?php echo e($f('white', 'solidus_c')); ?>"></div>
        <div class="field"><label for="w_peak">Peak temperature (°C)</label><input id="w_peak" name="white[peak_temp_c]" type="number" step="0.1" value="<?php echo e($f('white', 'peak_temp_c')); ?>"><span class="muted small">Highest temperature in the cup.</span></div>
    </div>
    <details class="curve-box" <?php if($f('white', 'curve_text')): ?> open <?php endif; ?>><summary>Full cooling curve (optional)</summary>
        <div class="field"><label for="w_file">CSV file (seconds, temperature °C)</label><input id="w_file" name="white[curve_file]" type="file" accept=".csv,.txt"></div>
        <div class="field"><label for="w_text">…or paste the curve</label><textarea id="w_text" name="white[curve_text]" rows="5" placeholder="0,1320.0&#10;0.5,1319.1&#10;…"><?php echo e($f('white', 'curve_text')); ?></textarea></div>
        <span class="muted small">At least 40 readings. Anything you key in above is used in place of the value detected from the curve.</span>
    </details>
</div></div>

<div class="panel"><div class="panel-header"><h2>Grey cup <span class="muted small">(stable, no tellurium)</span></h2></div><div class="panel-body">
    <p class="muted small">The iron solidifies grey, giving the eutectic arrest used to judge inoculation. Leave the whole card empty if no grey cup was run.</p>
    <div class="form-grid" data-progressive="3">
        <div class="field"><label for="g_tl">TL, liquidus (°C)</label><input id="g_tl" name="grey[tl_c]" type="number" step="0.1" value="<?php echo e($f('grey', 'tl_c')); ?>"></div>
        <div class="field"><label for="g_ts">TS, solidus (°C)</label><input id="g_ts" name="grey[solidus_c]" type="number" step="0.1" value="<?php echo e($f('grey', 'solidus_c')); ?>"></div>
        <div class="field"><label for="g_temin">TEmin (°C)</label><input id="g_temin" name="grey[te_min_c]" type="number" step="0.1" value="<?php echo e($f('grey', 'te_min_c')); ?>"></div>
        <div class="field"><label for="g_temax">TEmax (°C)</label><input id="g_temax" name="grey[te_max_c]" type="number" step="0.1" value="<?php echo e($f('grey', 'te_max_c')); ?>"></div>
        <div class="field"><label for="g_recal">Recalescence (°C)</label><input id="g_recal" name="grey[recalescence_c]" type="number" step="0.1" value="<?php echo e($f('grey', 'recalescence_c')); ?>"><span class="muted small">Left empty, it is worked out as TEmax − TEmin.</span></div>
        <div class="field"><label for="g_peak">Peak temperature (°C)</label><input id="g_peak" name="grey[peak_temp_c]" type="number" step="0.1" value="<?php echo e($f('grey', 'peak_temp_c')); ?>"></div>
        <div class="field"><label for="g_c">Carbon % (C)</label><input id="g_c" name="grey[carbon]" type="number" step="0.01" min="0" max="5" value="<?php echo e($f('grey', 'carbon')); ?>"></div>
        <div class="field"><label for="g_si">Silicon % (Si)</label><input id="g_si" name="grey[silicon]" type="number" step="0.01" min="0" max="6" value="<?php echo e($f('grey', 'silicon')); ?>"><span class="muted small">Left empty, the white cup's Si is used.</span></div>
    </div>
    <details class="curve-box" <?php if($f('grey', 'curve_text')): ?> open <?php endif; ?>><summary>Full cooling curve (optional)</summary>
        <div class="field"><label for="g_file">CSV file (seconds, temperature °C)</label><input id="g_file" name="grey[curve_file]" type="file" accept=".csv,.txt"></div>
        <div class="field"><label for="g_text">…or paste the curve</label><textarea id="g_text" name="grey[curve_text]" rows="5" placeholder="0,1320.0&#10;0.5,1319.1&#10;…"><?php echo e($f('grey', 'curve_text')); ?></textarea></div>
        <span class="muted small">At least 40 readings. <a href="<?php echo e(route('thermal.sample')); ?>">Download a sample curve</a> to try it. Anything you key in above is used in place of the value detected from the curve.</span>
    </details>
</div></div>
</div>

<button class="btn btn-primary" type="submit">Analyse and save</button>
</form>

<style>
    .cup-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:16px; align-items:start; margin-bottom:16px; }
    .cup-grid .panel { margin-bottom:0; }
    .curve-box { margin-top:12px; border-top:1px solid #e6eaf0; padding-top:10px; }
    .curve-box summary { cursor:pointer; font-weight:600; font-size:13px; }
    @media (max-width: 900px) { .cup-grid { grid-template-columns:1fr; } }
</style>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/thermal/create.blade.php ENDPATH**/ ?>