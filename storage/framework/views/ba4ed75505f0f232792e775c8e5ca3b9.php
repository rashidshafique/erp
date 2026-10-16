<?php $__env->startSection('title', 'Importing'); ?>
<?php $__env->startSection('breadcrumb'); ?><a href="<?php echo e(route('imports.index')); ?>">Import data</a> / <?php echo e($def['label']); ?> / Importing <?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<?php $total = max(1, (int) ($b->counts['TOTAL'] ?? 1)); $done = (int) $b->progress; $pct = min(100, round($done / $total * 100)); ?>
<div class="page-heading"><div><h1>Importing <?php echo e(strtolower($def['label'])); ?>…</h1><p>Large files are saved in steps. Keep this page open; it carries on by itself.</p></div></div>
<div class="panel"><div class="panel-body">
    <div style="background:#e6ebe8;border-radius:999px;height:14px;overflow:hidden"><div style="width:<?php echo e($pct); ?>%;height:100%;background:var(--accent)"></div></div>
    <p><strong><?php echo e(number_format($done)); ?></strong> of <?php echo e(number_format($total)); ?> rows handled · <?php echo e($b->counts['CREATE']); ?> added · <?php echo e($b->counts['UPDATE']); ?> updated · <?php echo e($b->counts['ERROR']); ?> errors</p>
    <form method="POST" action="<?php echo e(route('imports.commit', $b)); ?>" id="goOn"><?php echo csrf_field(); ?><button class="btn btn-primary" type="submit">Continue</button></form>
    <p class="muted small">If this page was closed or the connection dropped, open it again from Import data → Recent imports and press Continue. Rows already saved are not repeated.</p>
</div></div>
<script>setTimeout(function () { var f = document.getElementById('goOn'); f.querySelector('button').disabled = true; f.submit(); }, 400);</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/imports/progress.blade.php ENDPATH**/ ?>