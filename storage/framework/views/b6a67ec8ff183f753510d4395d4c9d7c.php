<?php $__env->startSection('title', 'Import result'); ?>
<?php $__env->startSection('breadcrumb'); ?><a href="<?php echo e(route('imports.index')); ?>">Import data</a> / <?php echo e($def['label']); ?> / Result <?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<?php $c = $b->counts; $cls = ['CREATE' => 'status-completed', 'UPDATE' => 'status-in-progress', 'UNCHANGED' => 'status-on-hold', 'SKIP' => 'status-on-hold', 'ERROR' => 'status-cancelled']; $bad = collect($b->report)->where('action', 'ERROR'); ?>
<div class="page-heading"><div><h1>Import finished</h1><p><?php echo e($def['label']); ?> from <?php echo e($b->source_label); ?>, <?php echo e($b->committed_at?->format('d M Y H:i')); ?> by <?php echo e($b->created_by_name ?? '—'); ?>.</p></div>
<div class="actions"><a class="btn" href="<?php echo e(route('imports.report', $b)); ?>">Download the full report (CSV)</a> <a class="btn btn-primary" href="<?php echo e(route('imports.create', ['entity' => $b->entity])); ?>">Import another file</a></div></div>
<div class="panel"><div class="panel-body"><strong><?php echo e($c['CREATE']); ?></strong> added · <strong><?php echo e($c['UPDATE']); ?></strong> updated · <?php echo e($c['UNCHANGED']); ?> unchanged · <?php echo e($c['SKIP']); ?> skipped · <span style="<?php echo e($c['ERROR'] ? 'color:#b00020' : ''); ?>"><?php echo e($c['ERROR']); ?> with errors</span> (of <?php echo e($c['TOTAL']); ?> rows)</div></div>
<?php if($bad->isNotEmpty()): ?>
<div class="panel"><div class="panel-header"><h2>Rows that were not imported</h2></div><div class="table-wrap"><table><thead><tr><th>Row</th><th><?php echo e($def['columns'][1] ?? 'Name'); ?></th><th>Why</th></tr></thead><tbody>
<?php $__currentLoopData = $bad->take(200); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($r['n']); ?></td><td><?php echo e($r['name'] ?? '—'); ?></td><td style="white-space:normal;color:#b00020"><?php echo e($r['message']); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div><div class="panel-body muted small">Fix these in your sheet and import it again. Rows already imported are matched and not duplicated.</div></div>
<?php endif; ?>
<?php echo $__env->make('audit._history', ['type' => 'ImportBatch', 'id' => $b->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/imports/show.blade.php ENDPATH**/ ?>