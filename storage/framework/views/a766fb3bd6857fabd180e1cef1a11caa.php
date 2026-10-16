<?php $__env->startSection('title', 'Patterns'); ?>
<?php $__env->startSection('breadcrumb', 'Patterns'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div>
        <h1>Pattern master</h1>
        <p>Manage physical tooling, pattern revisions and moulding configurations.</p>
    </div>
    <div class="actions">
        <a class="btn" href="<?php echo e(route('imports.create', ['entity' => 'pattern'])); ?>">Import</a> <a class="btn btn-primary" href="<?php echo e(route('patterns.create')); ?>">+ New pattern</a>
    </div>
</div>

<div class="metrics">
    <div class="metric">
        <div class="metric-label">Total patterns</div>
        <div class="metric-value"><?php echo e(number_format($totalPatterns)); ?></div>
    </div>
    <div class="metric">
        <div class="metric-label">Active</div>
        <div class="metric-value"><?php echo e(number_format($activePatterns)); ?></div>
    </div>
    <div class="metric">
        <div class="metric-label">Inactive / retired</div>
        <div class="metric-value"><?php echo e(number_format($otherPatterns)); ?></div>
    </div>
    <div class="metric">
        <div class="metric-label">Matching results</div>
        <div class="metric-value"><?php echo e(number_format($patterns->total())); ?></div>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" action="<?php echo e(route('patterns.index')); ?>" class="filters">
            <div class="field">
                <label for="search">Search pattern code or name</label>
                <input id="search" name="search" value="<?php echo e(request('search')); ?>"
                       placeholder="e.g. VALVE-001">
            </div>
            <div class="field">
                <label for="status">Tooling status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    <?php $__currentLoopData = ['ACTIVE', 'INACTIVE', 'RETIRED']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($status); ?>" <?php if(request('status') === $status): echo 'selected'; endif; ?>>
                            <?php echo e($status); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn" href="<?php echo e(route('patterns.index')); ?>">Reset</a>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>Patterns</h2>
        <span class="muted small"><?php echo e($patterns->total()); ?> records</span>
    </div>

    <?php if($patterns->isEmpty()): ?>
        <div class="empty-state">
            <strong>No patterns found</strong>
            <p>Create a pattern and its first engineering revision.</p>
            <a class="btn btn-primary" href="<?php echo e(route('patterns.create')); ?>">Create pattern</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Material</th>
                        <th>Latest revision</th>
                        <th>Cavities / mould</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $patterns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pattern): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php ($latestRevision = $pattern->revisions()->orderByDesc('created_at')->first()); ?>
                        <tr>
                            <td><strong><?php echo e($pattern->code); ?></strong></td>
                            <td><?php echo e($pattern->name); ?></td>
                            <td><?php echo e($pattern->pattern_type); ?></td>
                            <td><?php echo e($pattern->material ?: '—'); ?></td>
                            <td><?php echo e($latestRevision?->revision ?? '—'); ?></td>
                            <td><?php echo e($latestRevision?->cavities_per_mould ?? '—'); ?></td>
                            <td>
                                <span class="status <?php echo e($pattern->status === 'ACTIVE' ? 'status-completed' : 'status-cancelled'); ?>">
                                    <?php echo e($pattern->status); ?>

                                </span>
                            </td>
                            <td><a href="<?php echo e(route('patterns.show', $pattern)); ?>">Open →</a></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <div class="muted small">
                Showing <?php echo e($patterns->firstItem()); ?>–<?php echo e($patterns->lastItem()); ?>

                of <?php echo e($patterns->total()); ?>

            </div>
            <div class="pagination-links"><?php echo e($patterns->links()); ?></div>
        </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/patterns/index.blade.php ENDPATH**/ ?>