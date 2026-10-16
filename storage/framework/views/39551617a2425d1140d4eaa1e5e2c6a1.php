<?php $__env->startSection('title', $run->run_number); ?>
<?php $__env->startSection('breadcrumb'); ?>
    <a href="<?php echo e(route('moulding-runs.index')); ?>">Moulding runs</a>
    / <?php echo e($run->run_number); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-heading">
        <div>
            <h1><?php echo e($run->run_number); ?></h1>
            <p>
                Run recorded for <?php echo e($run->run_date?->format('d F Y') ?? 'an unspecified date'); ?>.
                Review the production order, output and downtime below.
            </p>
        </div>
        <div class="actions">
            <a href="<?php echo e(route('moulding-runs.index')); ?>" class="btn">Back to runs</a>
            <a href="<?php echo e(route('moulding-runs.create')); ?>" class="btn btn-primary">
                + New run
            </a>
        </div>
    </div>

    <div class="metrics">
        <div class="metric">
            <div class="metric-label">Moulds made</div>
            <div class="metric-value"><?php echo e(number_format($run->moulds_made_qty)); ?></div>
            <div class="metric-note">Recorded total output</div>
        </div>
        <div class="metric">
            <div class="metric-label">Accepted moulds</div>
            <div class="metric-value"><?php echo e(number_format($run->accepted_mould_qty)); ?></div>
            <div class="metric-note">Accepted at moulding stage</div>
        </div>
        <div class="metric">
            <div class="metric-label">Damaged moulds</div>
            <div class="metric-value"><?php echo e(number_format($run->damaged_mould_qty)); ?></div>
            <div class="metric-note">Moulding damage only</div>
        </div>
        <div class="metric">
            <div class="metric-label">Downtime</div>
            <div class="metric-value"><?php echo e(number_format($run->downtime_minutes)); ?></div>
            <div class="metric-note">Minutes recorded</div>
        </div>
    </div>

<div class="tab-marker" data-tab-start="Run"></div>
    <section class="panel">
        <div class="panel-header">
            <h2>Run information</h2>
            <span class="status status-<?php echo e(strtolower(str_replace('_', '-', $run->status))); ?>">
                <?php echo e(str_replace('_', ' ', $run->status)); ?>

            </span>
        </div>

        <div class="panel-body">
            <div class="detail-grid">
                <div>
                    <div class="detail-label">Production order</div>
                    <div class="detail-value">
                        <?php echo e($run->productionOrder?->production_order_number ?? '—'); ?>

                    </div>
                </div>

                <div>
                    <div class="detail-label">Product</div>
                    <div class="detail-value">
                        <?php echo e($run->productionOrder?->product?->name ?? '—'); ?>

                    </div>
                </div>

                <div>
                    <div class="detail-label">Order status</div>
                    <div class="detail-value">
                        <?php echo e($run->productionOrder?->status ?? '—'); ?>

                    </div>
                </div>

                <div>
                    <div class="detail-label">Pattern</div>
                    <div class="detail-value">
                        <?php echo e($run->patternRevision?->pattern?->name ?? 'Not specified'); ?>

                    </div>
                </div>

                <div>
                    <div class="detail-label">Pattern revision</div>
                    <div class="detail-value">
                        <?php echo e($run->patternRevision?->revision ?? '—'); ?>

                    </div>
                </div>

                <?php if($perMould !== null): ?>
                <div>
                    <div class="detail-label">Expected products per mould (cavity map)</div>
                    <div class="detail-value">
                        <?php echo e(rtrim(rtrim(number_format($perMould, 3), '0'), '.')); ?>

                        &middot; accepted moulds <?php echo e($run->accepted_mould_qty); ?>

                        &rarr; about <?php echo e(rtrim(rtrim(number_format($perMould * $run->accepted_mould_qty, 1), '0'), '.')); ?> products
                    </div>
                </div>
                <?php endif; ?>

                <div>
                    <div class="detail-label">Mould configuration</div>
                    <div class="detail-value">
                        <?php echo e($run->mouldConfiguration?->name ?? 'Not specified'); ?>

                    </div>
                </div>

                <div>
                    <div class="detail-label">Shift</div>
                    <div class="detail-value"><?php echo e($run->shift ?: '—'); ?></div>
                </div>

                <div>
                    <div class="detail-label">Moulding method</div>
                    <div class="detail-value"><?php echo e($run->moulding_method ?: '—'); ?></div>
                </div>

                <div>
                    <div class="detail-label">Sand system</div>
                    <div class="detail-value"><?php echo e($run->sand_system ?: '—'); ?></div>
                </div>

                <div>
                    <div class="detail-label">Operator</div>
                    <div class="detail-value"><?php echo e($run->operator_name ?: '—'); ?></div>
                </div>

                <div>
                    <div class="detail-label">Team</div>
                    <div class="detail-value"><?php echo e($run->team_name ?: '—'); ?></div>
                </div>

                <div>
                    <div class="detail-label">Station</div>
                    <div class="detail-value"><?php echo e($run->station?->code ?? '—'); ?><?php if($run->workListItem): ?> · <a href="<?php echo e(route('work-lists.show', $run->workListItem->moulding_work_list_id)); ?>">work list</a><?php endif; ?></div>
                </div>

                <div>
                    <div class="detail-label">Planned moulds</div>
                    <div class="detail-value">
                        <?php echo e(number_format($run->planned_mould_qty ?? 0)); ?>

                    </div>
                </div>

                <div>
                    <div class="detail-label">Started at</div>
                    <div class="detail-value">
                        <?php echo e($run->started_at?->format('d M Y, H:i') ?? '—'); ?>

                    </div>
                </div>

                <div>
                    <div class="detail-label">Completed at</div>
                    <div class="detail-value">
                        <?php echo e($run->completed_at?->format('d M Y, H:i') ?? '—'); ?>

                    </div>
                </div>

                <div>
                    <div class="detail-label">Record created</div>
                    <div class="detail-value">
                        <?php echo e($run->created_at?->format('d M Y, H:i') ?? '—'); ?>

                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Downtime and notes</h2>
        </div>

        <div class="panel-body">
            <div style="margin-bottom:20px">
                <div class="detail-label">Downtime reason</div>
                <div style="white-space:pre-wrap"><?php echo e($run->downtime_reason ?: 'No downtime reason recorded.'); ?></div>
            </div>

            <div>
                <div class="detail-label">Notes</div>
                <div style="white-space:pre-wrap"><?php echo e($run->notes ?: 'No additional notes.'); ?></div>
            </div>
        </div>
    </section>

<div class="tab-marker" data-tab-start="Moulds"></div>
    <?php if(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?>
    <?php if($errors->any()): ?><div class="alert alert-error"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>

    <section class="panel" id="assemblies">
        <div class="panel-header">
            <h2>Mould assemblies</h2>
            <span class="muted small"><?php echo e($run->mouldAssemblies->count()); ?> of <?php echo e($run->accepted_mould_qty); ?> accepted moulds assembled</span>
        </div>
        <div class="panel-body">
            <p class="muted small">Each assembly is one finished mould that will be poured. Its cavities are copied from the pattern's cavity map, so every cavity already knows which product (or part of a product) it makes.</p>
            <?php if($run->mouldAssemblies->isNotEmpty()): ?>
                <div class="table-wrap"><table>
                    <thead><tr><th>Assembly</th><th>Status</th><th>Assembled</th><th>Pour</th></tr></thead>
                    <tbody>
                    <?php $__currentLoopData = $run->mouldAssemblies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr><td><?php echo e($a->assembly_number); ?></td><td><?php echo e(str_replace('_', ' ', $a->status)); ?></td>
                            <td><?php echo e($a->assembled_at?->format('d M H:i')); ?><div class="muted small"><?php echo e($a->assembled_by); ?></div></td>
                            <td><?php if($a->pourAssignment?->pour): ?><a href="<?php echo e(route('pours.show', $a->pourAssignment->pour)); ?>"><?php echo e($a->pourAssignment->pour->pour_number); ?></a><?php else: ?><span class="muted">not poured</span><?php endif; ?></td></tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table></div>
            <?php endif; ?>

            <?php if($run->status !== 'CANCELLED' && $run->accepted_mould_qty > $run->mouldAssemblies->count()): ?>
                <h3 style="margin-top:20px;font-size:15px">Assemble moulds</h3>
                <form method="POST" action="<?php echo e(route('moulding-runs.assemble', $run)); ?>"><?php echo csrf_field(); ?>
                    <div class="form-grid">
                        <div class="field"><label for="count">How many moulds *</label><input id="count" name="count" type="number" min="1" max="<?php echo e($run->accepted_mould_qty - $run->mouldAssemblies->count()); ?>" value="<?php echo e(old('count', $run->accepted_mould_qty - $run->mouldAssemblies->count())); ?>" required></div>
                        <div class="field"><label for="mould_configuration_id">Mould configuration *</label>
                            <select id="mould_configuration_id" name="mould_configuration_id" required>
                                <?php $__currentLoopData = $configurations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($c->id); ?>" <?php if(old('mould_configuration_id', $run->mould_configuration_id) === $c->id): echo 'selected'; endif; ?>><?php echo e($c->code); ?> — <?php echo e($c->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select></div>
                        <div class="field"><label for="layers">Layers per mould *</label><input id="layers" name="layers" type="number" min="1" max="12" value="<?php echo e(old('layers', $run->mouldConfiguration?->nominal_layer_count ?? 2)); ?>" required><span class="muted small">2 = drag and cope. More layers are stack moulds.</span></div>
                        <div class="field"><label for="assembled_by">Assembled by</label><input id="assembled_by" name="assembled_by" maxlength="100" value="<?php echo e(old('assembled_by')); ?>"></div>
                        <div class="field"><label for="checked_by">Checked by</label><input id="checked_by" name="checked_by" maxlength="100" value="<?php echo e(old('checked_by')); ?>"></div>
                    </div>
                    <button class="btn btn-primary" type="submit">Assemble moulds</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
<div class="tab-marker" data-tab-start="Output"></div>
    <?php if(! in_array($run->status, ['COMPLETED', 'CANCELLED'])): ?>
    <section class="panel">
        <div class="panel-header"><h2>Record output</h2></div>
        <div class="panel-body">
            <?php if($errors->has('output')): ?><div class="alert alert-error"><?php echo e($errors->first('output')); ?></div><?php endif; ?>
            <form method="POST" action="<?php echo e(route('moulding-runs.output', $run)); ?>"><?php echo csrf_field(); ?>
                <div class="form-grid">
                    <div class="field"><label>Moulds made</label><input type="number" name="moulds_made_qty" min="0" value="<?php echo e(old('moulds_made_qty', $run->moulds_made_qty)); ?>" required></div>
                    <div class="field"><label>Accepted</label><input type="number" name="accepted_mould_qty" min="0" value="<?php echo e(old('accepted_mould_qty', $run->accepted_mould_qty)); ?>" required></div>
                    <div class="field"><label>Damaged</label><input type="number" name="damaged_mould_qty" min="0" value="<?php echo e(old('damaged_mould_qty', $run->damaged_mould_qty)); ?>" required></div>
                    <div class="field"><label>Downtime (min)</label><input type="number" name="downtime_minutes" min="0" value="<?php echo e(old('downtime_minutes', $run->downtime_minutes)); ?>"></div>
                    <div class="field"><label>Downtime reason</label><input name="downtime_reason" value="<?php echo e(old('downtime_reason', $run->downtime_reason)); ?>"></div>
                </div>
                <button class="btn" type="submit">Save output</button>
                <button class="btn btn-primary" type="submit" name="complete" value="1">Save and complete run</button>
            </form>
        </div>
    </section>
    <?php endif; ?>

<div class="tab-marker" data-tab-start="Sand"></div>
<?php echo $__env->make('moulding-runs._sand', ['run' => $run], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="tab-marker" data-tab-start="Files & history"></div>
<?php echo $__env->make('custom-fields._panel', ['entity' => 'moulding_run', 'id' => $run->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('attachments._panel', ['type' => 'moulding_run', 'id' => $run->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('audit._history', ['type' => 'MouldingRun', 'id' => $run->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/moulding-runs/show.blade.php ENDPATH**/ ?>