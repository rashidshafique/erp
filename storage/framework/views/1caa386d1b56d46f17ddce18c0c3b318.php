<?php $__env->startSection('title', $a->code); ?>
<?php $__env->startSection('breadcrumb'); ?>
<a href="<?php echo e(route('casting-analyses.index')); ?>">Casting analysis</a> / <?php echo e($a->code); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<style>.ca-wrap td, .ca-wrap th { white-space: normal; vertical-align: top; } input[type=range], input[type=checkbox], input[type=radio] { accent-color: var(--accent); }</style>
<?php
    $res = $a->result;
    $rec = $a->recommendation;
    $ok = $a->status === 'COMPLETE' && $res && $rec;
    $sevClass = ['HIGH' => 'status-cancelled', 'MEDIUM' => 'status-on-hold', 'LOW' => 'status-in-progress', 'INFO' => 'status-completed'];
    $roleLabel = ['PRIMARY' => 'Last to freeze', 'ISOLATED_SEVERE' => 'Cut off (severe)', 'ISOLATED' => 'Cut off'];
    $n = fn ($v, $d = 1) => number_format((float) $v, $d);
?>
<div class="page-heading">
    <div>
        <h1><?php echo e($a->name); ?></h1>
        <p><?php echo e($a->code); ?> · <?php echo e($a->original_name); ?> (<?php echo e($a->source_type); ?>, <?php echo e($n($a->file_size / 1024, 0)); ?> KB)<?php echo $a->product ? ' · <a href="'.e(route('products.show', $a->product)).'">'.e($a->product->code).'</a>' : ''; ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="<?php echo e(route('casting-analyses.index')); ?>">All analyses</a>
        <form method="POST" action="<?php echo e(route('casting-analyses.destroy', $a)); ?>" onsubmit="return confirm('Delete this analysis and its stored model?')" style="display:inline">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button class="btn btn-danger" type="submit">Delete</button>
        </form>
    </div>
</div>

<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>
<?php if(session('error')): ?><div class="alert alert-error"><?php echo e(session('error')); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="alert alert-error"><?php echo e($errors->first()); ?></div><?php endif; ?>

<?php if(! $ok): ?>
    <div class="alert alert-error"><strong>This analysis did not complete.</strong> <?php echo e($a->error); ?></div>
<?php endif; ?>

<?php if($ok): ?>
<div class="tab-marker" data-tab-start="Summary"></div>

<div class="metrics">
    <div class="metric">
        <div class="metric-label">Manufacturability</div>
        <div class="metric-value"><?php echo e($rec['score']['total']); ?> <span class="muted" style="font-size:15px">/ 100 · <?php echo e($rec['score']['letter']); ?></span></div>
        <div class="metric-note"><?php echo e(ucfirst($rec['score']['verdict'])); ?></div>
    </div>
    <div class="metric">
        <div class="metric-label">Casting weight</div>
        <div class="metric-value"><?php echo e($n($rec['weight']['casting_kg'], 2)); ?> kg</div>
        <div class="metric-note">Poured <?php echo e($n($rec['weight']['poured_kg'], 1)); ?> kg · yield <?php echo e($n($rec['weight']['yield_pct'], 0)); ?>%</div>
    </div>
    <div class="metric">
        <div class="metric-label">Last place to freeze</div>
        <div class="metric-value"><?php echo e($n($res['hotspots'][0]['modulus_cm'], 2)); ?> cm</div>
        <div class="metric-note">≈ <?php echo e($n($rec['times']['hotspots_min'][$res['hotspots'][0]['id']], 1)); ?> min · <?php echo e($n($res['modulus']['max_cm'] / max($res['modulus']['part_cm'], 1e-9), 1)); ?>× the average</div>
    </div>
    <div class="metric">
        <div class="metric-label">Cut-off hotspots</div>
        <div class="metric-value"><?php echo e(collect($res['hotspots'])->where('role', '!=', 'PRIMARY')->count()); ?></div>
        <div class="metric-note"><?php echo e($rec['family_label']); ?> · confidence <?php echo e(strtolower($res['quality']['confidence'])); ?></div>
    </div>
</div>

<?php if(! empty($res['quality']['warnings'])): ?>
    <div class="alert alert-error" style="background:#fff8e6;border-color:#f0d9a0;color:#7a5b00">
        <strong>Check before relying on this:</strong>
        <ul style="margin:6px 0 0 18px"><?php $__currentLoopData = $res['quality']['warnings']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $w): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($w); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header"><h2>What to look at first</h2></div>
    <div class="table-wrap">
        <table class="ca-wrap">
            <tbody>
            <?php $__currentLoopData = $rec['findings']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td style="width:90px"><span class="status <?php echo e($sevClass[$f['severity']]); ?>"><?php echo e($f['severity']); ?></span></td>
                    <td style="width:110px"><strong><?php echo e($f['area']); ?></strong></td>
                    <td><?php echo e($f['text']); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
</div>

<?php if(! empty($rec['design_changes'])): ?>
<div class="panel">
    <div class="panel-header"><h2>Design changes that would help</h2></div>
    <div class="panel-body"><ul style="margin:0 0 0 18px"><?php $__currentLoopData = $rec['design_changes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li style="margin-bottom:6px"><?php echo e($d); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header"><h2>How the score is made</h2><span class="muted small">weighted average</span></div>
    <div class="table-wrap">
        <table class="ca-wrap">
            <thead><tr><th>Part</th><th style="width:200px">Score</th><th>Weight</th><th>Why</th></tr></thead>
            <tbody>
            <?php $__currentLoopData = ['feeding' => 'Feeding and shrinkage', 'walls' => 'Wall thickness', 'uniformity' => 'Section uniformity', 'moulding' => 'Moulding and cores', 'yield' => 'Yield']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php ($p = $rec['score']['parts'][$k]); ?>
                <tr>
                    <td><strong><?php echo e($label); ?></strong></td>
                    <td><div style="background:#e8ece9;border-radius:4px;height:10px;position:relative"><div style="background:var(--accent);height:10px;border-radius:4px;width:<?php echo e($p['score']); ?>%"></div></div><span class="small"><?php echo e($p['score']); ?> / 100</span></td>
                    <td><?php echo e($p['weight']); ?></td>
                    <td class="small"><?php echo e(implode(' ', $p['why'])); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Change the choices and run again</h2></div>
    <div class="panel-body">
        <form method="POST" action="<?php echo e(route('casting-analyses.rerun', $a)); ?>" class="form-grid" onsubmit="this.querySelector('button[type=submit]').disabled=true;this.querySelector('button[type=submit]').textContent='Analysing…'">
            <?php echo csrf_field(); ?>
            <?php ($o = $a->options ?? []); ?>
            <div class="field"><label>Units</label><select name="units"><?php $__currentLoopData = ['mm' => 'Millimetres', 'cm' => 'Centimetres', 'm' => 'Metres', 'in' => 'Inches']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if($a->units === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
            <div class="field"><label>Quality</label><select name="quality"><?php $__currentLoopData = ['DRAFT' => 'Draft', 'STANDARD' => 'Standard', 'FINE' => 'Fine']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if($a->quality === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
            <div class="field"><label>Iron family</label><select name="family"><?php $__currentLoopData = ['AUTO' => 'Let the analyser choose', 'GREY' => 'Grey iron', 'DUCTILE' => 'Ductile iron']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if($a->family_requested === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
            <div class="field"><label>Iron grade</label><select name="iron_grade_id"><option value="">— none —</option><?php $__currentLoopData = \App\Models\IronGrade::where('status', 'ACTIVE')->whereIn('family', ['GREY', 'DUCTILE'])->orderBy('code')->get(['id', 'code']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($g->id); ?>" <?php if($a->iron_grade_id === $g->id): echo 'selected'; endif; ?>><?php echo e($g->code); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
            <div class="field"><label>Minimum tensile (MPa)</label><input type="number" name="min_tensile" value="<?php echo e($o['min_tensile'] ?? ''); ?>"></div>
            <div class="field"><label>Minimum elongation (%)</label><input type="number" step="0.5" name="min_elongation" value="<?php echo e($o['min_elongation'] ?? ''); ?>"></div>
            <div class="field"><label>Feeder type</label><select name="feeder"><?php $__currentLoopData = ['AUTO' => 'Automatic', 'CONVENTIONAL' => 'Sand riser', 'INSULATED' => 'Insulating sleeve', 'EXOTHERMIC' => 'Exothermic sleeve']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(($o['feeder'] ?? 'AUTO') === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
            <div class="field"><label>Minutes from treatment to last mould</label><input type="number" step="0.5" name="pour_window_min" value="<?php echo e($o['pour_window_min'] ?? 10); ?>"></div>
            <div class="field"><label>Chvorinov B (min/cm²)</label><input type="number" step="0.05" name="b_override" value="<?php echo e($o['b_override'] ?? ''); ?>" placeholder="Default"></div>
            <div class="field" style="align-self:end"><button class="btn btn-primary" type="submit">Run again</button></div>
        </form>
    </div>
</div>

<div class="tab-marker" data-tab-start="Solidification"></div>
<?php echo $__env->make('casting-analyses._viewer', ['a' => $a, 'res' => $res, 'rec' => $rec, 'roleLabel' => $roleLabel, 'n' => $n], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="tab-marker" data-tab-start="Feeding & tooling"></div>
<?php echo $__env->make('casting-analyses._tooling', ['a' => $a, 'res' => $res, 'rec' => $rec, 'n' => $n], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="tab-marker" data-tab-start="Chemistry"></div>
<?php echo $__env->make('casting-analyses._chemistry', ['a' => $a, 'res' => $res, 'rec' => $rec, 'n' => $n], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="tab-marker" data-tab-start="How it was worked out"></div>
<?php echo $__env->make('casting-analyses._method', ['a' => $a, 'res' => $res, 'rec' => $rec, 'n' => $n], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/casting-analyses/show.blade.php ENDPATH**/ ?>