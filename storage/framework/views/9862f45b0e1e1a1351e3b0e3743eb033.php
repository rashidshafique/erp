<div class="panel">
    <div class="panel-header"><h2>Step by step</h2></div>
    <div class="panel-body">
        <ol style="margin:0 0 0 20px;padding:0">
            <li style="margin-bottom:10px"><strong>Read and check the model.</strong> <?php echo e($res['triangles']); ?> triangles, size <?php echo e(implode(' × ', array_map(fn ($v) => $n($v, 1), $res['size_mm']))); ?> mm, volume <?php echo e($n($res['volume']['mesh_cm3'], 1)); ?> cm³, surface <?php echo e($n($res['area_cm2'], 0)); ?> cm². Then the part is filled with <?php echo e(number_format($res['grid']['solid_cells'])); ?> cells of <?php echo e($n($res['grid']['h_mm'], 2)); ?> mm (volume within <?php echo e($n(abs($res['volume']['error_pct']), 1)); ?>% of the mesh).</li>
            <li style="margin-bottom:10px"><strong>Measure the section modulus everywhere.</strong> Each cell is grouped with the centre line or plane of its own section. The volume and the mould-contact surface around that centre give M = V ÷ A. Reference values reproduced: cube a/6, sphere R/3, plate T/2.</li>
            <li style="margin-bottom:10px"><strong>Follow the modulus to find the last places to freeze.</strong> Metal freezes in order of modulus. Starting from the heaviest section and walking down, each separate peak is a hotspot and the lowest point on the way to a heavier one is its neck. <?php echo e(count($res['hotspots'])); ?> hotspot(s) were kept; <?php echo e($res['noise_peaks']); ?> tiny bumps were ignored as numerical noise.</li>
            <?php $__currentLoopData = $rec['steps']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li style="margin-bottom:10px"><strong><?php echo e($s['title']); ?>.</strong> <?php echo e($s['text']); ?></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ol>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>What this does and does not do</h2></div>
    <div class="panel-body">
        <p style="margin-top:0">This is a fast geometric study based on the modulus method that foundries use for risering by hand. It is good at ranking sections, finding where shrinkage will want to form and sizing a first feeding layout. It is <strong>not</strong> a full thermal and flow simulation.</p>
        <ul style="margin:0 0 0 18px">
            <li>Not covered: mould filling, gas and sand defects, inclusions, hot tears, distortion and residual stress.</li>
            <li>Freezing times use a single constant B per iron family. Calibrate B against your own pours before reading the minutes literally; the ranking of hotspots does not depend on it.</li>
            <li>Mould, chill and feeder effects are rule-of-thumb factors from <code>config/casting.php</code>. Adjust them to your plant practice.</li>
            <li>Chemistry windows are section-driven starting points. Your spectrometer, thermal analysis and the customer specification decide the final heat.</li>
            <li>Confidence for this run: <strong><?php echo e(strtolower($res['quality']['confidence'])); ?></strong>; cell size <?php echo e($n($res['quality']['cell_mm'], 2)); ?> mm<?php echo e($res['quality']['refined'] ? ' (refined automatically for thin walls)' : ''); ?>.</li>
        </ul>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Wall thickness distribution</h2><span class="muted small">share of surface area</span></div>
    <div class="table-wrap"><table class="no-row-click ca-wrap">
        <thead><tr><th>Wall (mm)</th><th style="width:60%">Share</th></tr></thead>
        <tbody><?php $__currentLoopData = $res['thickness']['hist']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr><td><?php echo e($b['to'] === null ? '≥ '.$b['from'] : $b['from'].' – '.$b['to']); ?></td><td><div style="background:#e8ece9;border-radius:4px;height:10px"><div style="background:var(--accent);height:10px;border-radius:4px;width:<?php echo e($b['pct']); ?>%"></div></div> <span class="small"><?php echo e($b['pct']); ?>%</span></td></tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody>
    </table></div>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/casting-analyses/_method.blade.php ENDPATH**/ ?>