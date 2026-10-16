<?php $__env->startSection('title', 'Check the import'); ?>
<?php $__env->startSection('breadcrumb'); ?><a href="<?php echo e(route('imports.index')); ?>">Import data</a> / <?php echo e($def['label']); ?> / Check <?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<style>.cnt { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; } .cnt a { border: 1px solid var(--border); border-radius: 10px; padding: 8px 14px; background: #fff; color: inherit; text-decoration: none; min-width: 110px; } .cnt a.on { border-color: var(--accent); box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent) 22%, transparent); } .cnt b { font-size: 20px; display: block; } .pv td { vertical-align: top; white-space: normal; } .err { color: #b00020; } .chg { font-size: 12.5px; }</style>
<?php
    $cls = ['CREATE' => 'status-completed', 'UPDATE' => 'status-in-progress', 'UNCHANGED' => 'status-on-hold', 'SKIP' => 'status-on-hold', 'ERROR' => 'status-cancelled'];
    $lab = ['CREATE' => 'Will add', 'UPDATE' => 'Will update', 'UNCHANGED' => 'No change', 'SKIP' => 'Skipped', 'ERROR' => 'Errors'];
    $doable = $counts['CREATE'] + $counts['UPDATE'];
?>
<div class="page-heading"><div><h1>Check before importing</h1><p><?php echo e($b->source_label); ?>. This is a dry run: nothing has been saved.</p></div>
<div class="actions"><a class="btn" href="<?php echo e(route('imports.map', $b)); ?>">Change the mapping</a></div></div>
<?php if($errors->any()): ?><div class="alert alert-error"><?php echo e($errors->first()); ?></div><?php endif; ?>
<div class="cnt">
    <a href="<?php echo e(route('imports.preview', $b)); ?>" class="<?php echo e($show === 'ALL' ? 'on' : ''); ?>"><b><?php echo e($counts['TOTAL']); ?></b>All rows</a>
    <?php $__currentLoopData = $lab; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e(route('imports.preview', [$b, 'show' => $k])); ?>" class="<?php echo e($show === $k ? 'on' : ''); ?>"><b><?php echo e($counts[$k]); ?></b><?php echo e($l); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<?php if($unknown): ?>
<div class="panel"><div class="panel-header"><h2>Values we do not recognise</h2></div><div class="panel-body">
<p class="muted">Say what each one means. Rows with these values stay as errors until you do.</p>
<form method="POST" action="<?php echo e(route('imports.values', $b)); ?>"><?php echo csrf_field(); ?>
<table><thead><tr><th>Field</th><th>Your value</th><th>Rows</th><th>Means</th></tr></thead><tbody>
<?php $__currentLoopData = $unknown; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $vals): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php $f = $def['fields'][$field]; ?>
<?php $__currentLoopData = $vals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $raw => $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><td><?php echo e($f['label']); ?></td><td><strong><?php echo e($raw); ?></strong></td><td><?php echo e($n); ?></td>
<td><select name="value_map[<?php echo e($field); ?>][<?php echo e(base64_encode((string) $raw)); ?>]"><option value="">— choose —</option>
<?php if($f['type'] === 'enum'): ?><?php $__currentLoopData = $f['enum']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ev): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($ev); ?>"><?php echo e($ev); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><option value="__SKIP__">Ignore this value (leave the field blank)</option>
<?php else: ?><option value="1">Yes</option><option value="0">No</option><?php endif; ?></select></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table>
<p><button class="btn btn-primary" type="submit">Apply and check again</button></p></form>
</div></div>
<?php endif; ?>

<div class="panel"><div class="panel-header"><h2>Rows</h2><span class="muted small"><?php echo e($total); ?> shown</span></div>
<?php if($rows === []): ?><div class="empty-state">No rows in this view.</div><?php else: ?>
<div class="table-wrap"><table class="pv"><thead><tr><th>Row</th><th>Result</th><?php $__currentLoopData = $def['columns'] ?? ['Code', 'Name']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $heading): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><th><?php echo e($heading); ?></th><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><th>Details</th></tr></thead><tbody>
<?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><td class="muted"><?php echo e($r['n']); ?></td><td><span class="status <?php echo e($cls[$r['action']]); ?>"><?php echo e($lab[$r['action']]); ?></span></td>
<td><?php echo e($r['code'] ?? ($r['action'] === 'CREATE' ? 'auto' : '—')); ?></td><td><?php echo e($r['name'] ?? '—'); ?></td>
<td><?php $__currentLoopData = $r['errors']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="err"><?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?> <?php $__currentLoopData = $r['notes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="muted small"><?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php $__currentLoopData = $r['changes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="chg"><strong><?php echo e($def['fields'][$field]['label'] ?? $field); ?></strong>: <?php echo e($c[0]); ?> → <?php echo e($c[1]); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div>
<?php if($total > $per): ?>
<div class="panel-body" style="display:flex;gap:8px;align-items:center">
<?php if($page > 1): ?><a class="btn" href="<?php echo e(route('imports.preview', [$b, 'show' => $show, 'page' => $page - 1])); ?>">← Earlier</a><?php endif; ?>
<span class="muted small">Rows <?php echo e(($page - 1) * $per + 1); ?>–<?php echo e(min($total, $page * $per)); ?> of <?php echo e($total); ?></span>
<?php if($page * $per < $total): ?><a class="btn" href="<?php echo e(route('imports.preview', [$b, 'show' => $show, 'page' => $page + 1])); ?>">Later →</a><?php endif; ?>
</div><?php endif; ?>
<?php endif; ?></div>

<div class="panel"><div class="panel-body">
<?php if($doable === 0): ?><p>Nothing would be added or changed. Fix the errors or change the mapping.</p>
<?php else: ?>
<p><strong><?php echo e($counts['CREATE']); ?></strong> will be added and <strong><?php echo e($counts['UPDATE']); ?></strong> updated.<?php if($counts['ERROR']): ?>
 <span class="err"><?php echo e($counts['ERROR']); ?> row(s) with errors will be left out</span><?php echo e(! empty($b->options['stop_on_error']) ? ', and because you chose to stop on errors nothing will be imported until they are fixed' : ''); ?>.<?php endif; ?></p>
<form method="POST" action="<?php echo e(route('imports.commit', $b)); ?>" onsubmit="this.querySelector('button').disabled=true"><?php echo csrf_field(); ?><button class="btn btn-primary" type="submit">Import now</button></form>
<?php endif; ?>
</div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/imports/preview.blade.php ENDPATH**/ ?>