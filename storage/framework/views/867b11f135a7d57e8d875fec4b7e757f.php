<?php $__env->startSection('title', 'Enquiries'); ?>
<?php $__env->startSection('breadcrumb', 'Enquiries'); ?>
<?php $__env->startSection('content'); ?>
<?php $cls = ['NEW' => 'status-on-hold', 'FEASIBILITY' => 'status-on-hold', 'QUOTED' => 'status-in-progress', 'WON' => 'status-completed', 'LOST' => 'status-cancelled', 'CANCELLED' => 'status-cancelled']; ?>
<div class="page-heading">
    <div><h1>Enquiries</h1><p>What customers ask for, whether we can make it, and what we quoted.</p></div>
    <div class="actions"><a class="btn btn-primary" href="<?php echo e(route('enquiries.wizard')); ?>">+ New request (guided)</a> <a class="btn" href="<?php echo e(route('enquiries.create')); ?>">Quick enquiry</a></div>
</div>
<div class="panel"><div class="panel-body">
    <form method="GET" action="<?php echo e(route('enquiries.index')); ?>" class="filters">
        <div class="field"><label for="search">Search</label><input id="search" name="search" value="<?php echo e(request('search')); ?>" placeholder="Code, title or customer reference"></div>
        <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All</option><?php $__currentLoopData = ['NEW', 'FEASIBILITY', 'QUOTED', 'WON', 'LOST', 'CANCELLED']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s); ?>" <?php if(request('status') === $s): echo 'selected'; endif; ?>><?php echo e(ucfirst(strtolower($s))); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
        <div class="field"><label for="source">Kind</label><select id="source" name="source"><option value="">All</option><?php $__currentLoopData = \App\Models\Enquiry::SOURCES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(request('source') === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
        <button class="btn btn-primary" type="submit">Filter</button><a class="btn" href="<?php echo e(route('enquiries.index')); ?>">Reset</a>
    </form>
</div></div>
<div class="panel">
    <div class="panel-header"><h2>Enquiries</h2><span class="muted small"><?php echo e($rows->total()); ?> records</span></div>
    <?php if($rows->isEmpty()): ?>
        <div class="empty-state"><strong>No enquiries yet</strong><p>Record each request as it comes in, whether it is a repeat, a drawing, a sample or a concept.</p><a class="btn btn-primary" href="<?php echo e(route('enquiries.wizard')); ?>">Start a request</a></div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Code</th><th>Customer</th><th>Title</th><th>Kind</th><th>Parts</th><th>Due</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><strong><?php echo e($r->code); ?></strong></td><td><?php echo e($r->customer?->name); ?></td>
                    <td><?php echo e($r->title); ?><?php if($r->customer_reference): ?><div class="muted small">Their ref <?php echo e($r->customer_reference); ?></div><?php endif; ?></td>
                    <td class="small"><?php echo e(\App\Models\Enquiry::SOURCES[$r->source_type] ?? $r->source_type); ?></td>
                    <td><?php echo e($r->items_count); ?></td>
                    <td class="small"><?php echo e($r->due_on?->format('d M Y') ?? '—'); ?></td>
                    <td><span class="status <?php echo e($cls[$r->status] ?? ''); ?>"><?php echo e($r->status); ?></span></td>
                    <td><a href="<?php echo e(route('enquiries.show', $r)); ?>">Open →</a></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table></div>
        <div class="pagination"><?php echo e($rows->links()); ?></div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/enquiries/index.blade.php ENDPATH**/ ?>