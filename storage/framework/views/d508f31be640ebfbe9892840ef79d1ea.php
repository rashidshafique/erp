<?php $__env->startSection('title', 'Iron grades'); ?>
<?php $__env->startSection('breadcrumb', 'Iron grades'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Iron grades</h1><p>Chemistry windows, standards equivalents, furnace capability and thermal acceptance windows.</p></div></div>
<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>
<div class="alert">Chemistry windows are starting values. Review each grade against your control plan and the customer's specification before relying on the gates.</div>

<div class="panel"><div class="table-wrap"><table>
    <thead><tr><th>Grade</th><th>Family</th><th>Min tensile</th><th>Min yield</th><th>Elong.</th><th>CE</th><th>Equivalents</th><th>Thermal window</th></tr></thead>
    <tbody>
    <?php $__currentLoopData = $grades; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td><a href="<?php echo e(route('iron-grades.show', $g)); ?>"><strong><?php echo e($g->code); ?></strong></a></td>
            <td><?php echo e($g->family); ?></td>
            <td><?php echo e($g->min_tensile_mpa ? $g->min_tensile_mpa.' MPa' : '—'); ?></td>
            <td><?php echo e($g->min_yield_mpa ? $g->min_yield_mpa.' MPa' : '—'); ?></td>
            <td><?php echo e($g->min_elongation_pct !== null ? $g->min_elongation_pct.' %' : '—'); ?></td>
            <td><?php echo e($g->ce_min ?? '—'); ?> – <?php echo e($g->ce_max ?? '—'); ?></td>
            <td><?php echo e($g->aliases_count); ?></td>
            <td><?php echo e(in_array($g->id, $windows, true) ? 'Set' : 'Not set'); ?></td>
        </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
</table></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/iron-grades/index.blade.php ENDPATH**/ ?>