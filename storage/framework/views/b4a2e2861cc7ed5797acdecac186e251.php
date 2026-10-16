<?php $__env->startSection('title', 'Melting Heats'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div>
        <h1>Melting heats</h1>
        <p>Review heat records and enter manual power or process readings.</p>
        <div class="actions">
            <a class="btn btn-primary" href="<?php echo e(route('heats.create')); ?>">+ Create heat</a>
        </div>  
    </div>
</div>

<?php if(session('success')): ?>
    <div class="panel">
        <div class="panel-body" style="color:var(--success)">
            <?php echo e(session('success')); ?>

        </div>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <h2>Heat register</h2>
        <span class="muted small"><?php echo e($heats->total()); ?> records</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Heat</th>
                    <th>Furnace</th>
                    <th>Process</th>
                    <th>Status</th>
                    <th>Target metal</th>
                    <th>Power limit</th>
                    <th>Power readings</th>
                    <th>Process readings</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $heats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $heat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><strong><?php echo e($heat->heat_number); ?></strong></td>
                        <td><?php echo e($heat->furnace?->name ?? '—'); ?></td>
                        <td><?php echo e($heat->furnace?->furnace_type ?? $heat->process_type); ?></td>
                        <td><span class="status"><?php echo e($heat->status); ?></span></td>
                        <td>
                            <?php echo e($heat->target_metal_weight_kg !== null
                                ? number_format((float) $heat->target_metal_weight_kg, 1).' kg'
                                : '—'); ?>

                        </td>
                        <td>
                            <?php echo e($heat->operating_power_limit_kw !== null
                                ? number_format((float) $heat->operating_power_limit_kw, 1).' kW'
                                : '—'); ?>

                        </td>
                        <td><?php echo e($heat->power_readings_count); ?></td>
                        <td><?php echo e($heat->process_readings_count); ?></td>
                        <td>
                            <a class="btn" href="<?php echo e(route('heats.show', $heat)); ?>">
                                Open heat
                            </a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;padding:32px" class="muted">
                            No heats have been recorded yet. Create a heat before entering readings.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="panel-body"><?php echo e($heats->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/heats/index.blade.php ENDPATH**/ ?>