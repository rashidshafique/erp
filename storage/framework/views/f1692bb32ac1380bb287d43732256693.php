<?php $__env->startSection('title', $pour->pour_number); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div>
        <h1><?php echo e($pour->pour_number); ?></h1>
        <p>
            Heat <?php echo e($pour->heat?->heat_number ?? '—'); ?>

            · <?php echo e($pour->heat?->furnace?->name ?? 'Furnace unavailable'); ?>

        </p>
    </div>
    <div class="actions">
        <a class="btn" href="<?php echo e(route('pours.index')); ?>">Back to pours</a>
    </div>
</div>

<?php if(session('success')): ?>
    <div class="panel">
        <div class="panel-body" style="color:var(--success)">
            <?php echo e(session('success')); ?>

        </div>
    </div>
<?php endif; ?>

<div class="metrics">
    <div class="metric">
        <div class="metric-label">Status</div>
        <div class="metric-value" style="font-size:21px">
            <?php echo e(str_replace('_', ' ', $pour->status)); ?>

        </div>
        <div class="metric-note">Pour lifecycle</div>
    </div>
    <div class="metric">
        <div class="metric-label">Mould assemblies</div>
        <div class="metric-value"><?php echo e($pour->mouldAssemblies->count()); ?></div>
        <div class="metric-note">Assigned to this pour</div>
    </div>
    <div class="metric">
        <div class="metric-label">Poured weight</div>
        <div class="metric-value" style="font-size:23px">
            <?php echo e($pour->total_poured_weight_kg !== null
                ? number_format((float) $pour->total_poured_weight_kg, 1) . ' kg'
                : '—'); ?>

        </div>
        <div class="metric-note">Recorded total</div>
    </div>
    <div class="metric">
        <div class="metric-label">Pour temperature</div>
        <div class="metric-value" style="font-size:23px">
            <?php echo e($pour->pour_temperature_c !== null
                ? number_format((float) $pour->pour_temperature_c, 0) . ' °C'
                : '—'); ?>

        </div>
        <div class="metric-note">Recorded temperature</div>
    </div>
</div>

<div class="tab-marker" data-tab-start="Overview"></div>
<?php echo $__env->make('pours._gate', ['pour' => $pour], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="panel">
    <div class="panel-header">
        <h2>Pour information</h2>
    </div>
    <div class="panel-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px">
            <div>
                <div class="muted small">Heat number</div>
                <strong><?php echo e($pour->heat?->heat_number ?? '—'); ?></strong>
            </div>
            <div>
                <div class="muted small">Furnace</div>
                <strong><?php echo e($pour->heat?->furnace?->code ?? '—'); ?></strong>
                <div class="muted small"><?php echo e($pour->heat?->furnace?->name ?? ''); ?></div>
            </div>
            <div>
                <div class="muted small">Planned time</div>
                <strong><?php echo e($pour->planned_at?->format('d M Y, H:i') ?? '—'); ?></strong>
            </div>
            <div>
                <div class="muted small">Started</div>
                <strong><?php echo e($pour->started_at?->format('d M Y, H:i') ?? '—'); ?></strong>
            </div>
            <div>
                <div class="muted small">Completed</div>
                <strong><?php echo e($pour->completed_at?->format('d M Y, H:i') ?? '—'); ?></strong>
            </div>
            <div>
                <div class="muted small">Operator</div>
                <strong><?php echo e($pour->operator_name ?: '—'); ?></strong>
            </div>
            <div>
                <div class="muted small">Ladle</div>
                <strong><?php echo e($pour->ladle_reference ?: '—'); ?></strong>
            </div>
        </div>

        <?php if($pour->notes): ?>
            <div style="margin-top:22px">
                <div class="muted small">Notes</div>
                <p><?php echo e($pour->notes); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="tab-marker" data-tab-start="Moulds"></div>
<div class="panel">
    <div class="panel-header">
        <h2>Mould assemblies assigned to this pour</h2>
        <span class="muted small"><?php echo e($pour->mouldAssemblies->count()); ?> assemblies</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Assembly</th>
                    <th>Configuration</th>
                    <th>Production order</th>
                    <th>Castings poured</th>
                    <th>Poured weight</th>
                    <th>Casting lots</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $pour->mouldAssemblies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td>
                            <?php echo e($assignment->mouldAssembly?->assembly_number ?? '—'); ?>

                        </td>
                        <td>
                            <?php echo e($assignment->mouldAssembly?->configuration?->name ?? '—'); ?>

                        </td>
                        <td>
                            <?php echo e($assignment->productionOrder?->production_order_number ?? '—'); ?>

                            <?php if($assignment->productionOrder?->product): ?>
                                <div class="muted small">
                                    <?php echo e($assignment->productionOrder->product->code); ?>

                                    — <?php echo e($assignment->productionOrder->product->name); ?>

                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e(number_format((int) $assignment->castings_poured_qty)); ?></td>
                        <td>
                            <?php echo e($assignment->poured_weight_kg !== null
                                ? number_format((float) $assignment->poured_weight_kg, 3) . ' kg'
                                : '—'); ?>

                        </td>
                        <td><?php $__currentLoopData = $assignment->castingLots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e(route('lots.show', $lot)); ?>"><?php echo e($lot->lot_number); ?></a><div class="muted small"><?php echo e($lot->product?->code); ?> × <?php echo e($lot->quantity_poured); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> <?php if($assignment->castingLots->isEmpty()): ?>—<?php endif; ?></td>
                        <td><?php if($pour->status !== 'COMPLETED'): ?><form method="POST" action="<?php echo e(route('pours.assemblies.detach', [$pour, $assignment])); ?>" onsubmit="return confirm('Remove this mould from the pour?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn" type="submit">Remove</button></form><?php endif; ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="7" class="muted" style="padding:30px;text-align:center">
                            No mould assemblies have been assigned yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="panel-body">
        <?php $__errorArgs = ['assemblies'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="alert alert-error"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        <?php if($candidates->isNotEmpty()): ?>
            <form method="POST" action="<?php echo e(route('pours.assemblies.attach', $pour)); ?>"><?php echo csrf_field(); ?>
                <div class="field"><label>Add moulds to this pour</label>
                    <div style="max-height:220px;overflow:auto;border:1px solid var(--border);border-radius:6px;padding:8px">
                        <?php $__currentLoopData = $candidates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <label style="display:block;font-weight:400;padding:3px 0"><input type="checkbox" name="assembly_ids[]" value="<?php echo e($c->id); ?>">
                                <?php echo e($c->assembly_number); ?> — <?php echo e($c->mouldingRun?->productionOrder?->production_order_number); ?> · <?php echo e($c->mouldingRun?->productionOrder?->product?->code); ?></label>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <span class="muted small">Only moulds from released production orders are listed. Each mould is checked against the heat's iron grade.</span></div>
                <button class="btn btn-primary" type="submit">Add selected moulds</button>
            </form>
        <?php elseif(in_array($pour->status, ['PLANNED', 'IN_PROGRESS'], true)): ?>
            <p class="muted small">No unpoured moulds from released orders. Assemble moulds on a moulding run first.</p>
        <?php endif; ?>
    </div>
</div>
<div class="tab-marker" data-tab-start="Files & history"></div>
<?php echo $__env->make('custom-fields._panel', ['entity' => 'pour', 'id' => $pour->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('attachments._panel', ['type' => 'pour', 'id' => $pour->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('audit._history', ['type' => 'Pour', 'id' => $pour->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/pours/show.blade.php ENDPATH**/ ?>