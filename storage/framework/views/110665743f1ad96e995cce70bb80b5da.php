<?php $__env->startSection('title', 'Melting plan'); ?>
<?php $__env->startSection('breadcrumb', 'Melting plan'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div><h1>Melting plan</h1><p>Released production orders grouped by iron grade, sequenced to limit changeover, placed on eligible furnaces.</p></div>
    <div class="actions">
        <form method="POST" action="<?php echo e(route('heat-plan.rebuild')); ?>" style="display:flex;gap:8px;align-items:center"><?php echo csrf_field(); ?>
            <input type="date" name="from" value="<?php echo e(old('from', today()->toDateString())); ?>" min="<?php echo e(today()->toDateString()); ?>" required>
            <button class="btn btn-primary" type="submit">Rebuild plan</button></form>
    </div>
</div>
<?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="alert alert-error"><ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div><?php endif; ?>
<?php if(! empty($skipped)): ?>
    <div class="alert alert-error"><strong>Not planned:</strong><ul><?php $__currentLoopData = $skipped; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($s); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div>
<?php endif; ?>
<p class="muted small">Rebuilding replaces heats that are still only planned. Released heats are kept and their metal is not planned twice. Planned weights come from the cavity map (poured weights), else casting weight or net weight divided by the default yield.</p>

<div class="panel">
    <?php if($heats->isEmpty()): ?>
        <div class="empty-state">No heats planned. Release production orders, then rebuild the plan.</div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Date</th><th>Plan</th><th>Grade</th><th>Furnace</th><th>Metal (kg)</th><th>Orders</th><th>Flags</th><th></th></tr></thead>
            <tbody>
            <?php $__currentLoopData = $heats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($h->planned_date->format('d M')); ?></td>
                    <td><strong><?php echo e($h->plan_number); ?></strong><div class="muted small"><?php echo e($h->status); ?></div></td>
                    <td><?php echo e($h->ironGrade?->code); ?></td>
                    <td>
                        <?php if($h->status === 'PLANNED'): ?>
                            <form method="POST" action="<?php echo e(route('heat-plan.assign', $h)); ?>"><?php echo csrf_field(); ?>
                                <select name="furnace_id" onchange="this.form.submit()">
                                    <?php $__currentLoopData = $furnaces; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($f->id); ?>" <?php if($h->furnace_id === $f->id): echo 'selected'; endif; ?>><?php echo e($f->code); ?> (<?php echo e($f->furnace_type); ?>)</option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select></form>
                        <?php else: ?> <?php echo e($h->furnace?->code); ?> <?php endif; ?>
                    </td>
                    <td><?php echo e(number_format((float) $h->planned_metal_kg, 1)); ?></td>
                    <td style="white-space:normal"><?php $__currentLoopData = $h->orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo e($o->productionOrder?->production_order_number); ?> (<?php echo e(number_format((float) $o->metal_kg, 0)); ?> kg)<br><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></td>
                    <td style="white-space:normal"><?php if($h->changeover_required): ?><span class="status status-on-hold">Changeover</span> <?php endif; ?> <?php if($h->late_risk): ?><span class="status status-cancelled">Late risk</span><?php endif; ?></td>
                    <td>
                        <?php if($h->status === 'PLANNED'): ?>
                            <form method="POST" action="<?php echo e(route('heat-plan.release', $h)); ?>"><?php echo csrf_field(); ?>
                                <input name="operator_name" placeholder="Operator" maxlength="100">
                                <button class="btn btn-primary" type="submit">Release heat</button></form>
                        <?php elseif($h->heat): ?>
                            <a href="<?php echo e(route('heats.show', $h->heat)); ?>"><?php echo e($h->heat->heat_number); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/heat-plan/index.blade.php ENDPATH**/ ?>