<?php ($m = $rec['melt']); ?>
<div class="panel">
    <div class="panel-header"><h2><?php echo e($rec['family_label']); ?>: recommended melt</h2><span class="muted small"><?php echo e($m['section_tier']); ?></span></div>
    <div class="panel-body">
        <p style="margin-top:0"><strong>Why this material.</strong> <?php echo e($rec['family_reason']); ?></p>
        <div class="detail-grid">
            <div><div class="detail-label">Carbon equivalent</div><div class="detail-value"><?php echo e($n($m['ce'][0], 1)); ?> to <?php echo e($n($m['ce'][1], 1)); ?></div></div>
            <div><div class="detail-label">Pouring temperature</div><div class="detail-value small"><?php echo e($m['pour_text']); ?></div></div>
            <div><div class="detail-label">Inoculation</div><div class="detail-value small"><?php echo e($m['inoculation']); ?></div></div>
            <?php if($m['nodule_target']): ?>
                <div><div class="detail-label">Nodule count target</div><div class="detail-value">≥ <?php echo e($m['nodule_target']); ?> per mm²</div></div>
            <?php endif; ?>
        </div>
        <?php $__currentLoopData = $m['notes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $note): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p class="muted small" style="margin-bottom:0"><?php echo e($note); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</div>

<?php if(! empty($m['conflicts'])): ?>
    <div class="alert alert-error"><strong>Conflict with the selected grade:</strong>
        <ul style="margin:6px 0 0 18px"><?php $__currentLoopData = $m['conflicts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($c); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header"><h2>Chemistry window (mass %)</h2></div>
    <div class="table-wrap"><table class="no-row-click ca-wrap">
        <thead><tr><th>Element</th><th>From</th><th>To</th><th>Aim</th><?php if($a->grade): ?><th>Grade <?php echo e($a->grade->code); ?></th><?php endif; ?><th>Note</th></tr></thead>
        <tbody><?php $__currentLoopData = $m['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><strong><?php echo e($r['element']); ?></strong></td>
                <td><?php echo e($r['min']); ?></td><td><?php echo e($r['max']); ?></td><td><strong><?php echo e($r['target']); ?></strong></td>
                <?php if($a->grade): ?><td class="small"><?php echo e(isset($r['grade_min']) || isset($r['grade_max']) ? (($r['grade_min'] ?? '—').' to '.($r['grade_max'] ?? '—')) : '—'); ?></td><?php endif; ?>
                <td class="small"><?php echo e($r['note']); ?></td>
            </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody>
    </table></div>
</div>

<?php if($m['mg']): ?>
<div class="panel">
    <div class="panel-header"><h2>Magnesium treatment</h2></div>
    <div class="panel-body">
        <p style="margin:0"><?php echo e($m['mg']['text']); ?></p>
        <p class="muted small" style="margin-bottom:0">Base sulphur above 0.025 % wastes magnesium and risks low nodularity. Too much magnesium forms carbides; too little gives flake or compacted graphite.</p>
    </div>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header"><h2>Grades in your list that fit</h2></div>
    <div class="panel-body">
        <?php if(! empty($m['grade_candidates'])): ?>
            <div class="table-wrap"><table class="no-row-click ca-wrap">
                <thead><tr><th>Grade</th><th>Name</th><th>Min tensile</th><th>Min yield</th><th>Min elongation</th></tr></thead>
                <tbody><?php $__currentLoopData = $m['grade_candidates']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr><td><strong><?php echo e($g['code']); ?></strong></td><td><?php echo e($g['name']); ?></td><td><?php echo e($g['min_tensile_mpa'] ?? '—'); ?> MPa</td><td><?php echo e($g['min_yield_mpa'] ?? '—'); ?> MPa</td><td><?php echo e($g['min_elongation_pct'] ?? '—'); ?> %</td></tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody>
            </table></div>
            <p class="muted small" style="margin-bottom:0">Lowest strength that still meets the requirement first. Section size lowers the strength actually reached in a casting compared with a separately cast test bar, so keep a margin.</p>
        <?php else: ?>
            <p class="muted" style="margin:0">No active <?php echo e($rec['family_label']); ?> grade in your list meets the entered requirement. Add grades under Quality → Iron grades, or relax the requirement.</p>
        <?php endif; ?>
    </div>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/casting-analyses/_chemistry.blade.php ENDPATH**/ ?>