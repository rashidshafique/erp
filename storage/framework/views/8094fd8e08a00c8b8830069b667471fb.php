<?php $__env->startSection('title', $product->code); ?>
<?php $__env->startSection('breadcrumb'); ?>
<a href="<?php echo e(route('products.index')); ?>">Products</a> / <?php echo e($product->code); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div>
        <h1><?php echo e($product->name); ?></h1>
        <p><?php echo e($product->code); ?> · <?php echo e($product->product_type); ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="<?php echo e(route('products.index')); ?>">All products</a>
        <a class="btn btn-primary" href="<?php echo e(route('products.edit', $product)); ?>">Edit product</a>
    </div>
</div>

<div class="tab-marker" data-tab-start="Specification"></div>
<div class="panel">
    <div class="panel-header">
        <h2>Product specification</h2>
        <span class="status <?php echo e($product->active ? 'status-completed' : 'status-cancelled'); ?>">
            <?php echo e($product->active ? 'ACTIVE' : 'INACTIVE'); ?>

        </span>
    </div>
    <div class="panel-body">
        <?php echo $__env->make('attachments._cover', ['type' => 'product', 'id' => $product->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div style="margin:6px 0 10px"><?php echo \App\Support\Code128::svg($product->code, 40, 2); ?> <a class="small" href="<?php echo e(route('labels.show', ['product', $product->id])); ?>" target="_blank">Print label →</a></div>
        <div class="detail-grid">
            <div>
                <div class="detail-label">Product code</div>
                <div class="detail-value"><?php echo e($product->code); ?></div>
            </div>
            <div>
                <div class="detail-label">Product name</div>
                <div class="detail-value"><?php echo e($product->name); ?></div>
            </div>
            <div>
                <div class="detail-label">Product type</div>
                <div class="detail-value"><?php echo e($product->product_type); ?></div>
            </div>
            <div>
                <div class="detail-label">Base unit</div>
                <div class="detail-value"><?php echo e($product->base_uom); ?></div>
            </div>
            <div>
                <div class="detail-label">Nominal weight</div>
                <div class="detail-value">
                    <?php echo e($product->nominal_weight_kg !== null
                        ? number_format((float) $product->nominal_weight_kg, 4).' kg'
                        : 'Not specified'); ?>

                </div>
            </div>
            <div>
                <div class="detail-label">Revision count</div>
                <div class="detail-value"><?php echo e($product->revisions->count()); ?></div>
            </div>
        </div>

        <?php if($product->description): ?>
            <div style="margin-top:20px">
                <div class="detail-label">Description</div>
                <div><?php echo e($product->description); ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="tab-marker" data-tab-start="Revisions"></div>
<div class="panel">
    <div class="panel-header">
        <h2>Revision history</h2>
    </div>

    <?php if($product->revisions->isEmpty()): ?>
        <div class="empty-state">No revisions have been recorded for this product.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Revision</th>
                        <th>Status</th>
                        <th>Net weight</th>
                        <th>Effective from</th>
                        <th>Effective to</th>
                        <th>Engineering notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $product->revisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $revision): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><strong><?php echo e($revision->revision); ?></strong></td>
                            <td><span class="status"><?php echo e($revision->status); ?></span></td>
                            <td>
                                <?php echo e($revision->net_weight_kg !== null
                                    ? number_format((float) $revision->net_weight_kg, 4).' kg'
                                    : '—'); ?>

                            </td>
                            <td><?php echo e($revision->effective_from?->format('d M Y') ?? '—'); ?></td>
                            <td><?php echo e($revision->effective_to?->format('d M Y') ?? '—'); ?></td>
                            <td style="white-space:normal;min-width:220px">
                                <?php echo e($revision->engineering_notes ?: '—'); ?>

                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php echo $__env->make('products._made-on-patterns', ['product' => $product], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="panel">
    <div class="panel-header"><h2>Add revision</h2></div>
    <div class="panel-body">
        <form method="POST" action="<?php echo e(route('products.revisions.store', $product)); ?>">
            <?php echo csrf_field(); ?>
            <div class="form-grid">
                <div class="field">
                    <label for="revision">Revision identifier <span class="required">*</span></label>
                    <input id="revision" name="revision" maxlength="20" required
                           value="<?php echo e(old('revision')); ?>" placeholder="e.g. B">
                </div>

                <div class="field">
                    <label for="status">Revision status <span class="required">*</span></label>
                    <select id="status" name="status" required>
                        <?php $__currentLoopData = ['DRAFT', 'APPROVED', 'OBSOLETE']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($status); ?>" <?php if(old('status', 'DRAFT') === $status): echo 'selected'; endif; ?>>
                                <?php echo e($status); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="field">
                    <label for="net_weight_kg">Net weight (kg)</label>
                    <input id="net_weight_kg" name="net_weight_kg" type="number"
                           step="0.0001" min="0" value="<?php echo e(old('net_weight_kg')); ?>">
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
                    <label for="engineering_notes">Engineering notes</label>
                    <textarea id="engineering_notes" name="engineering_notes"><?php echo e(old('engineering_notes')); ?></textarea>
                </div>
            </div>

            <div class="actions" style="margin-top:18px">
                <button class="btn btn-primary" type="submit">Add revision</button>
            </div>
        </form>
    </div>
</div>
<div class="tab-marker" data-tab-start="Casting analysis"></div>
<?php ($analyses = \App\Models\CastingAnalysis::where('product_id', $product->id)->orderByDesc('created_at')->limit(10)->get()); ?>
<div class="panel">
    <div class="panel-header">
        <h2>Casting analysis</h2>
        <a class="btn btn-primary btn-small" href="<?php echo e(route('casting-analyses.create', ['product' => $product->id])); ?>">+ Analyse a model</a>
    </div>
    <?php if($analyses->isEmpty()): ?>
        <div class="panel-body muted">No analysis yet. Attach an STL or STEP model on the Files tab, or upload one here, to find hotspots, size feeders and get a manufacturability score.</div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Code</th><th>Name</th><th>Score</th><th>Status</th><th>When</th><th></th></tr></thead>
            <tbody><?php $__currentLoopData = $analyses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $an): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr><td><strong><?php echo e($an->code); ?></strong></td><td><?php echo e($an->name); ?></td><td><?php echo e($an->score !== null ? $an->score.' ('.$an->score_letter.')' : '—'); ?></td>
                    <td><span class="status <?php echo e($an->status === 'COMPLETE' ? 'status-completed' : 'status-cancelled'); ?>"><?php echo e($an->status); ?></span></td>
                    <td class="muted small"><?php echo e($an->created_at->diffForHumans()); ?></td><td><a href="<?php echo e(route('casting-analyses.show', $an)); ?>">Open →</a></td></tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody>
        </table></div>
    <?php endif; ?>
</div>
<div class="tab-marker" data-tab-start="Files & history"></div>
<?php echo $__env->make('custom-fields._panel', ['entity' => 'product', 'id' => $product->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('attachments._panel', ['type' => 'product', 'id' => $product->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('audit._history', ['type' => 'Product', 'id' => $product->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/products/show.blade.php ENDPATH**/ ?>