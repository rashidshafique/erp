<?php $__env->startSection('title', 'Pour Register'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div>
        <h1>Pour Register</h1>
        <p>Track each pouring operation from heat selection to casting traceability.</p>
    </div>
    <div class="actions">
        <a class="btn btn-primary" href="<?php echo e(route('pours.create')); ?>">+ Create pour</a>
    </div>
</div>

<div class="metrics">
    <div class="metric">
        <div class="metric-label">Total pours</div>
        <div class="metric-value"><?php echo e(number_format($totalPours)); ?></div>
        <div class="metric-note">All recorded pours</div>
    </div>
    <div class="metric">
        <div class="metric-label">Active pours</div>
        <div class="metric-value"><?php echo e(number_format($activePours)); ?></div>
        <div class="metric-note">Planned or in progress</div>
    </div>
    <div class="metric">
        <div class="metric-label">Completed pours</div>
        <div class="metric-value"><?php echo e(number_format($completedPours)); ?></div>
        <div class="metric-note">Marked completed</div>
    </div>
    <div class="metric">
        <div class="metric-label">Showing</div>
        <div class="metric-value"><?php echo e($pours->count()); ?></div>
        <div class="metric-note">Pours on this page</div>
    </div>
</div>

<?php if(session('success')): ?>
    <div class="panel">
        <div class="panel-body" style="color:var(--success)">
            <?php echo e(session('success')); ?>

        </div>
    </div>
<?php endif; ?>

<?php if($errors->any()): ?>
    <div class="panel">
        <div class="panel-body" style="color:var(--danger)">
            <strong>Please correct the following:</strong>
            <ul>
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <h2>Pour history</h2>
        <span class="muted small"><?php echo e($pours->total()); ?> records</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Pour number</th>
                    <th>Heat</th>
                    <th>Furnace</th>
                    <th>Status</th>
                    <th>Planned time</th>
                    <th>Temperature</th>
                    <th>Poured weight</th>
                    <th>Assemblies</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $pours; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pour): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td>
                            <a href="<?php echo e(route('pours.show', $pour)); ?>">
                                <strong><?php echo e($pour->pour_number); ?></strong>
                            </a>
                        </td>
                        <td><?php echo e($pour->heat?->heat_number ?? '—'); ?></td>
                        <td>
                            <?php if($pour->heat?->furnace): ?>
                                <?php echo e($pour->heat->furnace->code); ?>

                                <div class="muted small"><?php echo e($pour->heat->furnace->name); ?></div>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status status-<?php echo e(strtolower(str_replace('_', '-', $pour->status))); ?>">
                                <?php echo e(str_replace('_', ' ', $pour->status)); ?>

                            </span>
                        </td>
                        <td><?php echo e($pour->planned_at?->format('d M Y, H:i') ?? '—'); ?></td>
                        <td>
                            <?php echo e($pour->pour_temperature_c !== null
                                ? number_format((float) $pour->pour_temperature_c, 0) . ' °C'
                                : '—'); ?>

                        </td>
                        <td>
                            <?php echo e($pour->total_poured_weight_kg !== null
                                ? number_format((float) $pour->total_poured_weight_kg, 3) . ' kg'
                                : '—'); ?>

                        </td>
                        <td><?php echo e($pour->mould_assemblies_count); ?></td>
                        <td>
                            <a class="btn" href="<?php echo e(route('pours.show', $pour)); ?>">Open</a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="9" class="muted" style="padding:32px;text-align:center">
                            No pours recorded yet. Create a pour to start tracking castings.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="panel-body">
        <?php echo e($pours->links()); ?>

    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/pours/index.blade.php ENDPATH**/ ?>