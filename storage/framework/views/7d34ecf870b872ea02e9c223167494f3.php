<?php $__env->startSection('title', $grade->code); ?>
<?php $__env->startSection('breadcrumb', 'Iron grades / '.$grade->code); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div><h1><?php echo e($grade->code); ?></h1><p><?php echo e($grade->name); ?> · <?php echo e($grade->family); ?><?php echo e($grade->standard_code ? ' · '.$grade->standard_code : ''); ?></p></div>
    <div class="actions"><a class="btn" href="<?php echo e(route('iron-grades.index')); ?>">All grades</a></div>
</div>
<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="alert alert-error"><ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div><?php endif; ?>

<div class="tab-marker" data-tab-start="Chemistry"></div>
<div class="panel">
    <div class="panel-header"><h2>Chemistry and process limits</h2></div>
    <div class="panel-body">
        <form method="POST" action="<?php echo e(route('iron-grades.update', $grade)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
            <input type="hidden" name="name" value="<?php echo e($grade->name); ?>">
            <div class="table-wrap"><table>
                <thead><tr><th>Element</th><th>Min %</th><th>Max %</th></tr></thead>
                <tbody>
                <?php $__currentLoopData = \App\Models\IronGrade::ELEMENTS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr><td><?php echo e($label); ?></td>
                        <td><input name="<?php echo e($k); ?>_min" type="number" step="0.001" value="<?php echo e(old($k.'_min', $grade->{$k.'_min'})); ?>"></td>
                        <td><input name="<?php echo e($k); ?>_max" type="number" step="0.001" value="<?php echo e(old($k.'_max', $grade->{$k.'_max'})); ?>"></td></tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <tr><td>CE (C + Si/3)</td>
                        <td><input name="ce_min" type="number" step="0.01" value="<?php echo e(old('ce_min', $grade->ce_min)); ?>"></td>
                        <td><input name="ce_max" type="number" step="0.01" value="<?php echo e(old('ce_max', $grade->ce_max)); ?>"></td></tr>
                    <tr><td>Tapping temperature °C</td>
                        <td><input name="tap_temp_min_c" type="number" value="<?php echo e(old('tap_temp_min_c', $grade->tap_temp_min_c)); ?>"></td>
                        <td><input name="tap_temp_max_c" type="number" value="<?php echo e(old('tap_temp_max_c', $grade->tap_temp_max_c)); ?>"></td></tr>
                    <tr><td>Pouring temperature °C</td>
                        <td><input name="pour_temp_min_c" type="number" value="<?php echo e(old('pour_temp_min_c', $grade->pour_temp_min_c)); ?>"></td>
                        <td><input name="pour_temp_max_c" type="number" value="<?php echo e(old('pour_temp_max_c', $grade->pour_temp_max_c)); ?>"></td></tr>
                </tbody>
            </table></div>
            <div class="field field-full"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="2"><?php echo e(old('notes', $grade->notes)); ?></textarea></div>
            <button class="btn btn-primary" type="submit">Save limits</button>
        </form>
    </div>
</div>

<div class="tab-marker" data-tab-start="Thermal & furnaces"></div>
<div class="panel">
    <div class="panel-header"><h2>Thermal analysis acceptance window (stable cup)</h2></div>
    <div class="panel-body">
        <p class="muted small">Set these from your own correlation of cooling curves with metallography and mechanical results. A field left empty is not judged. With every field empty, tests are recorded but not judged and the pouring gate does not ask for a thermal test.</p>
        <form method="POST" action="<?php echo e(route('iron-grades.window', $grade)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
            <div class="form-grid">
                <?php $__currentLoopData = ['tl_min_c' => 'TL min °C', 'tl_max_c' => 'TL max °C', 'ce_min' => 'CE from TL min', 'ce_max' => 'CE from TL max', 'te_min_floor_c' => 'TEmin at least °C', 'recal_min_c' => 'Recalescence min °C', 'recal_max_c' => 'Recalescence max °C', 'solidus_min_c' => 'Solidus at least °C']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="field"><label for="w_<?php echo e($k); ?>"><?php echo e($l); ?></label><input id="w_<?php echo e($k); ?>" name="<?php echo e($k); ?>" type="number" step="0.1" value="<?php echo e(old($k, $window->{$k})); ?>"></div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <div class="field field-full"><label for="w_notes">Notes</label><input id="w_notes" name="notes" maxlength="1000" value="<?php echo e(old('notes', $window->notes)); ?>"></div>
            </div>
            <button class="btn btn-primary" type="submit">Save window</button>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Furnace capability</h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Furnace</th><th>Type</th><th>Can melt this grade</th><th>Basis</th><th>Override</th></tr></thead>
        <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $furnaces; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
                <td><strong><?php echo e($f->code); ?></strong> <?php echo e($f->name); ?></td><td><?php echo e($f->furnace_type); ?></td>
                <td><?php echo e($can[$f->id] ? 'Yes' : 'No'); ?></td>
                <td><?php echo e($overrides->has($f->id) ? 'Set for this furnace' : 'Default for '.$f->furnace_type); ?></td>
                <td><form method="POST" action="<?php echo e(route('iron-grades.capability', $grade)); ?>"><?php echo csrf_field(); ?>
                    <input type="hidden" name="furnace_id" value="<?php echo e($f->id); ?>">
                    <select name="mode"><option value="DEFAULT">Use default</option><option value="ALLOW" <?php if($overrides->get($f->id) === true): echo 'selected'; endif; ?>>Allow</option><option value="DENY" <?php if($overrides->has($f->id) && $overrides->get($f->id) === false): echo 'selected'; endif; ?>>Deny</option></select>
                    <button class="btn" type="submit">Save</button></form></td>
            </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="5" class="empty-state">No active furnaces.</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
    <div class="panel-body muted small">Default: induction furnaces can melt every family; cupolas only grey iron. Change the defaults in config/foundry.php.</div>
</div>

<div class="tab-marker" data-tab-start="Designations & limits"></div>
<div class="panel">
    <div class="panel-header"><h2>Equivalent designations</h2></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Standard</th><th>Designation</th><th>Match</th></tr></thead>
        <tbody><?php $__currentLoopData = $grade->aliases->sortBy('standard'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($a->standard); ?></td><td><?php echo e($a->designation); ?></td><td><?php echo e($a->is_exact ? 'Same grade' : 'Nearest equivalent — confirm with the customer'); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody>
    </table></div>
</div>
<?php echo $__env->make('limits._panel', ['type' => 'IRON_GRADE', 'id' => $grade->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="tab-marker" data-tab-start="Files & history"></div>
<?php echo $__env->make('attachments._panel', ['type' => 'iron_grade', 'id' => $grade->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('audit._history', ['type' => 'IronGrade', 'id' => $grade->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/iron-grades/show.blade.php ENDPATH**/ ?>