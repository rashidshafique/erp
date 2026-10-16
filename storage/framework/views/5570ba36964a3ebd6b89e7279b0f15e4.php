<style>.ca-wrap td, .ca-wrap th { white-space: normal; vertical-align: top; }</style>
<?php ($t = $rec['tooling']); ?>
<?php ($gt = $rec['gating']); ?>
<div class="panel">
    <div class="panel-header"><h2>Moulding and pattern</h2></div>
    <div class="panel-body">
        <div class="detail-grid">
            <div><div class="detail-label">Process</div><div class="detail-value"><?php echo e($t['process']); ?></div><div class="muted small"><?php echo e($t['process_why']); ?></div></div>
            <div><div class="detail-label">Parting line</div><div class="detail-value"><?php echo e($t['parting']); ?></div></div>
            <div><div class="detail-label">Pattern equipment</div><div class="detail-value"><?php echo e($t['pattern']); ?></div></div>
            <div><div class="detail-label">Pattern shrink allowance</div><div class="detail-value"><?php echo e($n($t['shrink_allowance_pct'], 1)); ?> %</div></div>
            <div><div class="detail-label">Machining allowance</div><div class="detail-value"><?php echo e($n($t['machining_allowance_mm'], 1)); ?> mm per machined face</div><div class="muted small">General sand-casting guide by size; use your drawing's tolerance class.</div></div>
            <div><div class="detail-label">Draft</div><div class="detail-value"><?php echo e($t['draft']); ?></div></div>
            <div><div class="detail-label">Flask (inside, mm)</div><div class="detail-value"><?php echo e(implode(' × ', $t['flask_mm'])); ?></div><div class="muted small"><?php echo e($t['flask_note']); ?></div></div>
            <div><div class="detail-label">Cores</div><div class="detail-value"><?php echo e($t['core_count'] ? $t['core_count'].' needed' : 'None needed'); ?></div><div class="muted small"><?php echo e($t['core_binder']); ?></div></div>
        </div>
    </div>
</div>

<?php if(! empty($rec['cores'])): ?>
<div class="panel">
    <div class="panel-header"><h2>Where cores are needed</h2></div>
    <div class="table-wrap"><table class="no-row-click ca-wrap">
        <thead><tr><th>Zone</th><th>Position (mm)</th><th>Surface</th><th>Advice</th></tr></thead>
        <tbody><?php $__currentLoopData = $rec['cores']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr><td><strong><?php echo e($c['label']); ?></strong></td><td class="small"><?php echo e(implode(', ', array_map(fn ($v) => $n($v, 0), $c['centre_mm']))); ?></td><td><?php echo e($c['area_cm2'] !== null ? $n($c['area_cm2'], 0).' cm²' : '—'); ?></td><td class="small"><?php echo e($c['advice']); ?></td></tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody>
    </table></div>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header"><h2>Feeders</h2><span class="muted small">modulus method: feeder modulus ≥ <?php echo e($n($rec['feeders'][0]['margin'] ?? 1.1, 1)); ?> × casting (<?php echo e($rec['family_label']); ?>), neck ≥ 1.1 ×</span></div>
    <div class="table-wrap"><table class="no-row-click ca-wrap">
        <thead><tr><th>For</th><th>Type</th><th>Diameter × height</th><th>Weight</th><th>Sized by</th><th>Neck</th><th>Placement</th></tr></thead>
        <tbody><?php $__currentLoopData = $rec['feeders']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><strong><?php echo e($f['hotspot']); ?></strong> <span class="muted small">M <?php echo e($n($f['casting_modulus_cm'], 2)); ?> cm</span><?php if($f['optional']): ?><div class="small muted">Optional in grey iron; try without in a trial.</div><?php endif; ?></td>
                <td><?php echo e($f['type_label']); ?></td>
                <td><?php echo e($f['diameter_mm']); ?> × <?php echo e($f['height_mm']); ?> mm</td>
                <td><?php echo e($n($f['weight_kg'], 1)); ?> kg</td>
                <td class="small"><?php echo e($f['governs']); ?> (feeder M <?php echo e($n($f['feeder_modulus_cm'], 2)); ?> cm)</td>
                <td class="small">M ≥ <?php echo e($n($f['neck_modulus_cm'], 2)); ?> cm, about Ø<?php echo e($f['neck_diameter_mm']); ?> mm and short</td>
                <td class="small"><?php echo e($f['position']); ?></td>
            </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody>
    </table></div>
    <div class="panel-body small muted">Diameter is the larger of the modulus requirement and the feeding-volume requirement. Height is 1.5 × diameter. Use a breaker core under the feeder to make fettling easier. Treat these as a starting point for the first trial.</div>
</div>

<?php if(! empty($rec['chills'])): ?>
<div class="panel">
    <div class="panel-header"><h2>Chills for cut-off hotspots</h2></div>
    <div class="table-wrap"><table class="no-row-click ca-wrap">
        <thead><tr><th>Hotspot</th><th>Chill</th><th>Thickness</th><th>Contact area</th><th>Effect</th></tr></thead>
        <tbody><?php $__currentLoopData = $rec['chills']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr><td><strong><?php echo e($c['hotspot']); ?></strong></td><td class="small"><?php echo e($c['material']); ?></td><td><?php echo e($c['thickness_mm']); ?> mm</td><td><?php echo e($n($c['area_cm2'], 0)); ?> cm²</td><td class="small"><?php echo e($c['effect']); ?> <?php echo e($c['caution']); ?></td></tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody>
    </table></div>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header"><h2>Gating</h2></div>
    <div class="panel-body">
        <div class="detail-grid">
            <div><div class="detail-label">Poured weight</div><div class="detail-value"><?php echo e($n($rec['weight']['poured_kg'], 1)); ?> kg</div><div class="muted small">casting <?php echo e($n($rec['weight']['casting_kg'], 1)); ?> + feeders <?php echo e($n($rec['weight']['feeders_kg'], 1)); ?> + gating <?php echo e($n($rec['weight']['gating_kg'], 1)); ?></div></div>
            <div><div class="detail-label">Pouring time</div><div class="detail-value">≈ <?php echo e($n($gt['pour_time_s'], 0)); ?> s</div></div>
            <div><div class="detail-label">Choke area</div><div class="detail-value">≈ <?php echo e($n($gt['choke_area_cm2'], 1)); ?> cm²</div><div class="muted small">head <?php echo e($n($gt['effective_head_mm'], 0)); ?> mm, top gating</div></div>
            <div><div class="detail-label">Ratio sprue : runner : ingates</div><div class="detail-value"><?php echo e($gt['ratio_label']); ?></div></div>
            <div><div class="detail-label">Areas (cm²)</div><div class="detail-value">sprue <?php echo e($n($gt['sprue_area_cm2'], 1)); ?> · runner <?php echo e($n($gt['runner_area_cm2'], 1)); ?> · ingates <?php echo e($n($gt['ingate_area_cm2'], 1)); ?></div></div>
            <div><div class="detail-label">Filter</div><div class="detail-value small"><?php echo e($gt['filter']); ?></div></div>
        </div>
        <p class="muted small" style="margin-top:12px">Estimated from poured weight and a typical pouring-time rule. Bottom gating lowers the head and lengthens the time; adjust for your pattern layout.</p>
    </div>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/casting-analyses/_tooling.blade.php ENDPATH**/ ?>