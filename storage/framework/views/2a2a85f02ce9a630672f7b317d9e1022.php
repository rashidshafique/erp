<?php $__env->startSection('title', 'Products'); ?>
<?php $__env->startSection('breadcrumb', 'Products'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div>
        <h1>Product master</h1>
        <p>Manage casting codes, product specifications and revisions.</p>
    </div>
    <div class="actions">
        <a class="btn" href="<?php echo e(route('imports.create', ['entity' => 'product'])); ?>">Import</a> <a class="btn btn-primary" href="<?php echo e(route('products.create')); ?>">+ New product</a>
    </div>
</div>

<div class="metrics">
    <div class="metric">
        <div class="metric-label">Total products</div>
        <div class="metric-value"><?php echo e(number_format($totalProducts)); ?></div>
    </div>
    <div class="metric">
        <div class="metric-label">Active</div>
        <div class="metric-value"><?php echo e(number_format($activeProducts)); ?></div>
    </div>
    <div class="metric">
        <div class="metric-label">Inactive</div>
        <div class="metric-value"><?php echo e(number_format($inactiveProducts)); ?></div>
    </div>
    <div class="metric">
        <div class="metric-label">Matching results</div>
        <div class="metric-value"><?php echo e(number_format($products->total())); ?></div>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" action="<?php echo e(route('products.index')); ?>" class="filters">
            <div class="field">
                <label for="search">Search code or name</label>
                <input id="search" name="search" value="<?php echo e(request('search')); ?>"
                       placeholder="e.g. VALVE-001">
            </div>
            <div class="field">
                <label for="active">Product status</label>
                <select id="active" name="active">
                    <option value="">All products</option>
                    <option value="1" <?php if(request('active') === '1'): echo 'selected'; endif; ?>>Active</option>
                    <option value="0" <?php if(request('active') === '0'): echo 'selected'; endif; ?>>Inactive</option>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn" href="<?php echo e(route('products.index')); ?>">Reset</a>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>Products</h2>
        <span class="muted small"><?php echo e($products->total()); ?> records</span>
    </div>

    <?php if($products->isEmpty()): ?>
        <div class="empty-state">
            <strong>No products found</strong>
            <p>Create your first product to start building the production workflow.</p>
            <a class="btn btn-primary" href="<?php echo e(route('products.create')); ?>">Create product</a>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Nominal weight</th>
                        <th>Revisions</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><strong><?php echo e($product->code); ?></strong></td>
                            <td><?php echo e($product->name); ?></td>
                            <td><?php echo e($product->product_type); ?></td>
                            <td>
                                <?php echo e($product->nominal_weight_kg !== null
                                    ? number_format((float) $product->nominal_weight_kg, 4).' kg'
                                    : '—'); ?>

                            </td>
                            <td><?php echo e($product->revisions_count); ?></td>
                            <td>
                                <span class="status <?php echo e($product->active ? 'status-completed' : 'status-cancelled'); ?>">
                                    <?php echo e($product->active ? 'ACTIVE' : 'INACTIVE'); ?>

                                </span>
                            </td>
                            <td><a href="<?php echo e(route('products.show', $product)); ?>">Open →</a></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
        <div class="pagination">
            <div class="muted small">
                Showing <?php echo e($products->firstItem()); ?>–<?php echo e($products->lastItem()); ?>

                of <?php echo e($products->total()); ?>

            </div>
            <div class="pagination-links"><?php echo e($products->links()); ?></div>
        </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/products/index.blade.php ENDPATH**/ ?>