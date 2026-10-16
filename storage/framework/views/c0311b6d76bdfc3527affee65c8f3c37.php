<?php $__env->startSection('title', $pattern->code); ?>
<?php $__env->startSection('breadcrumb', 'Patterns / ' . $pattern->code); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div>
        <h1><?php echo e($pattern->code); ?></h1>
        <p><?php echo e($pattern->name); ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="<?php echo e(route('patterns.index')); ?>">All patterns</a>
        <a class="btn btn-primary" href="#new-revision">+ Add revision</a>
    </div>
</div>

<div class="tab-marker" data-tab-start="Tooling"></div>
<div class="panel">
    <div class="panel-header">
        <h2>Physical tooling record</h2>
        <span class="status <?php echo e($pattern->status === 'ACTIVE' ? 'status-completed' : 'status-cancelled'); ?>">
            <?php echo e($pattern->status); ?>

        </span>
    </div>
    <div class="panel-body">
        <?php echo $__env->make('attachments._cover', ['type' => 'pattern', 'id' => $pattern->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('tools._mark', ['tool' => $pattern, 'type' => 'pattern'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="detail-grid">
            <div><span class="muted small">Pattern code</span><strong><?php echo e($pattern->code); ?></strong></div>
            <div><span class="muted small">Name</span><strong><?php echo e($pattern->name); ?></strong></div>
            <div><span class="muted small">Type</span><strong><?php echo e($pattern->pattern_type); ?></strong></div>
            <div><span class="muted small">Material</span><strong><?php echo e($pattern->material ?: '—'); ?></strong></div>
            <div><span class="muted small">Made for sand</span><strong><?php echo e($pattern->sand_system ?: 'not recorded'); ?></strong><?php if($pattern->compatible_sands): ?><div class="muted small">also works on <?php echo e(implode(', ', $pattern->compatible_sands)); ?></div><?php endif; ?></div>
            <div>
                <span class="muted small">Dimensions (L × W × H)</span>
                <strong>
                    <?php echo e($pattern->length_mm ?? '—'); ?> ×
                    <?php echo e($pattern->width_mm ?? '—'); ?> ×
                    <?php echo e($pattern->height_mm ?? '—'); ?> mm
                </strong>
            </div>
        </div>

        <?php if($pattern->description): ?>
            <div class="field-full" style="margin-top: 20px">
                <span class="muted small">Description</span>
                <p><?php echo e($pattern->description); ?></p>
            </div>
        <?php endif; ?>

        <?php if($pattern->notes): ?>
            <div class="field-full" style="margin-top: 16px">
                <span class="muted small">General tooling notes</span>
                <p><?php echo e($pattern->notes); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="tab-marker" data-tab-start="Sand system"></div>
<div class="panel"><div class="panel-header"><h2>Sand system</h2></div><div class="panel-body">
    <p class="muted small" style="margin-top:0">A pattern is made with the taper and allowances of one sand system. The work list warns when it is put on a line running a sand system that is not listed here.</p>
    <form method="POST" action="<?php echo e(route('patterns.sand', $pattern)); ?>" class="form-grid"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
        <?php echo $__env->make('patterns._sand', ['sands' => \App\Models\SandSystem::choices($pattern->sand_system, ...($pattern->compatible_sands ?? []))], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div><button class="btn btn-primary">Save sand system</button></div>
    </form>
</div></div>

<div class="tab-marker" data-tab-start="Revisions"></div>
<div class="panel">
    <div class="panel-header"><h2>Engineering revisions</h2></div>

    <?php if($pattern->revisions->isEmpty()): ?>
        <div class="empty-state">No revisions recorded.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Revision</th>
                        <th>Status</th>
                        <th>Parting</th>
                        <th>Cavities / mould</th>
                        <th>Layers / mould</th>
                        <th>Weight (kg)</th>
                        <th>Effective dates</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $pattern->revisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $revision): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><strong><?php echo e($revision->revision); ?></strong></td>
                            <td><?php echo e($revision->status); ?></td>
                            <td><?php echo e($revision->parting_type ?: '—'); ?></td>
                            <td><?php echo e($revision->cavities_per_mould ?? '—'); ?></td>
                            <td><?php echo e($revision->layers_per_mould ?? '—'); ?></td>
                            <td>
                                <?php echo e($revision->pattern_weight_kg !== null
                                    ? number_format((float) $revision->pattern_weight_kg, 4)
                                    : '—'); ?>

                            </td>
                            <td>
                                <?php echo e($revision->effective_from?->format('Y-m-d') ?? '—'); ?>

                                to
                                <?php echo e($revision->effective_to?->format('Y-m-d') ?? '—'); ?>

                            </td>
                        </tr>
                        <?php if($revision->change_description || $revision->engineering_notes): ?>
                            <tr>
                                <td colspan="7">
                                    <?php if($revision->change_description): ?>
                                        <strong>Change:</strong> <?php echo e($revision->change_description); ?>

                                    <?php endif; ?>
                                    <?php if($revision->engineering_notes): ?>
                                        <div class="muted small" style="margin-top: 5px">
                                            <strong>Engineering notes:</strong> <?php echo e($revision->engineering_notes); ?>

                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="tab-marker" data-tab-start="Cavity map"></div>
<?php echo $__env->make('patterns._cavity-map', ['pattern' => $pattern, 'productRevisions' => $productRevisions], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="tab-marker" data-tab-start="New revision"></div>
<div class="panel" id="new-revision">
    <div class="panel-header">
        <h2>Add engineering revision</h2>
    </div>
    <div class="panel-body">
        <p class="muted small">
            This creates a new revision; it does not modify existing revisions.
            Record a meaningful change description for traceability.
        </p>

        <?php if($errors->any()): ?>
            <div class="alert alert-error">
                <strong>Please correct the following:</strong>
                <ul>
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li><?php echo e($error); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo e(route('patterns.revisions.store', $pattern)); ?>">
            <?php echo csrf_field(); ?>
            <div class="form-grid">
                <div class="field">
                    <label for="revision">Revision code *</label>
                    <input id="revision" name="revision" required maxlength="20"
                           value="<?php echo e(old('revision')); ?>" placeholder="e.g. B">
                </div>
                <div class="field">
                    <label for="status">Revision status *</label>
                    <select id="status" name="status" required>
                        <?php $__currentLoopData = ['DRAFT', 'APPROVED', 'OBSOLETE']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($status); ?>" <?php if(old('status', 'DRAFT') === $status): echo 'selected'; endif; ?>>
                                <?php echo e($status); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="field">
                    <label for="parting_type">Parting arrangement</label>
                    <input id="parting_type" name="parting_type" maxlength="30"
                           value="<?php echo e(old('parting_type')); ?>" placeholder="e.g. HORIZONTAL">
                </div>
                <div class="field">
                    <label for="cavities_per_mould">Cavities per mould</label>
                    <input id="cavities_per_mould" name="cavities_per_mould"
                           type="number" min="1" step="1" value="<?php echo e(old('cavities_per_mould')); ?>">
                </div>
                <div class="field">
                    <label for="layers_per_mould">Layers per mould</label>
                    <input id="layers_per_mould" name="layers_per_mould"
                           type="number" min="1" step="1" value="<?php echo e(old('layers_per_mould', 2)); ?>">
                </div>
                <div class="field">
                    <label for="pattern_weight_kg">Pattern / plate weight (kg)</label>
                    <input id="pattern_weight_kg" name="pattern_weight_kg"
                           type="number" min="0.0001" step="0.0001"
                           value="<?php echo e(old('pattern_weight_kg')); ?>">
                </div>
                <div class="field">
                    <label for="effective_from">Effective from</label>
                    <input id="effective_from" name="effective_from" type="date"
                           value="<?php echo e(old('effective_from')); ?>">
                </div>
                <div class="field">
                    <label for="effective_to">Effective to</label>
                    <input id="effective_to" name="effective_to" type="date"
                           value="<?php echo e(old('effective_to')); ?>">
                </div>
                <div class="field field-full">
                    <label for="change_description">Change description *</label>
                    <textarea id="change_description" name="change_description" rows="3"
                              required><?php echo e(old('change_description')); ?></textarea>
                </div>
                <div class="field field-full">
                    <label for="engineering_notes">Engineering notes</label>
                    <textarea id="engineering_notes" name="engineering_notes" rows="3"><?php echo e(old('engineering_notes')); ?></textarea>
                </div>
            </div>
            <div class="actions" style="margin-top: 18px">
                <button class="btn btn-primary" type="submit">Save new revision</button>
            </div>
        </form>
    </div>
</div>
<div class="tab-marker" data-tab-start="Cooling"></div>
<?php echo $__env->make('patterns._cooling', ['pattern' => $pattern], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="tab-marker" data-tab-start="Files & history"></div>
<?php echo $__env->make('custom-fields._panel', ['entity' => 'pattern', 'id' => $pattern->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('attachments._panel', ['type' => 'pattern', 'id' => $pattern->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('audit._history', ['type' => 'Pattern', 'id' => $pattern->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/patterns/show.blade.php ENDPATH**/ ?>