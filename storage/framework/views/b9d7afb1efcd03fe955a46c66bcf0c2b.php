<?php $__env->startSection('title', 'Casting analysis'); ?>
<?php $__env->startSection('breadcrumb', 'Casting analysis'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div>
        <h1>Casting analysis</h1>
        <p>Upload an STL or STEP model to see where it freezes last, how to feed it, how castable it is and what to melt.</p>
    </div>
    <div class="actions">
        <a class="btn btn-primary" href="<?php echo e(route('casting-analyses.create')); ?>">+ New analysis</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" action="<?php echo e(route('casting-analyses.index')); ?>" class="filters">
            <div class="field">
                <label for="search">Search code or name</label>
                <input id="search" name="search" value="<?php echo e(request('search')); ?>" placeholder="e.g. CAN-00012 or bracket">
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All</option>
                    <?php $__currentLoopData = ['COMPLETE', 'FAILED']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($s); ?>" <?php if(request('status') === $s): echo 'selected'; endif; ?>><?php echo e(ucfirst(strtolower($s))); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn" href="<?php echo e(route('casting-analyses.index')); ?>">Reset</a>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>Analyses</h2>
        <span class="muted small"><?php echo e($rows->total()); ?> records</span>
    </div>
    <?php if($rows->isEmpty()): ?>
        <div class="empty-state">
            <strong>No analyses yet</strong>
            <p>Export a part as STL from your CAD system and upload it. STEP files work when the converter is installed.</p>
            <a class="btn btn-primary" href="<?php echo e(route('casting-analyses.create')); ?>">Start an analysis</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Code</th><th>Name</th><th>Product</th><th>Material</th><th>Score</th><th>Last to freeze</th><th>Status</th><th>When</th><th></th></tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php ($rec = $r->recommendation); ?>
                        <tr>
                            <td><strong><?php echo e($r->code); ?></strong></td>
                            <td><?php echo e($r->name); ?><div class="muted small"><?php echo e($r->original_name); ?></div></td>
                            <td><?php echo e($r->product ? $r->product->code : '—'); ?></td>
                            <td><?php echo e($rec['family_label'] ?? '—'); ?></td>
                            <td><?php if($r->score !== null): ?><strong><?php echo e($r->score); ?></strong> <span class="muted">(<?php echo e($r->score_letter); ?>)</span><?php else: ?> — <?php endif; ?></td>
                            <td><?php if($rec): ?><?php echo e(number_format($rec['times']['hotspots_min'][array_key_first($rec['times']['hotspots_min'])] ?? 0, 1)); ?> min <?php else: ?> — <?php endif; ?></td>
                            <td><span class="status <?php echo e($r->status === 'COMPLETE' ? 'status-completed' : 'status-cancelled'); ?>"><?php echo e($r->status); ?></span></td>
                            <td class="muted small"><?php echo e($r->created_at->diffForHumans()); ?></td>
                            <td><a href="<?php echo e(route('casting-analyses.show', $r)); ?>">Open →</a></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
        <div class="pagination"><?php echo e($rows->links()); ?></div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/casting-analyses/index.blade.php ENDPATH**/ ?>