<?php $__env->startSection('title', 'Import data'); ?>
<?php $__env->startSection('breadcrumb', 'Import data'); ?>
<?php $__env->startSection('content'); ?>
<style>.im-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px; } .im-card { display: block; border: 1px solid var(--border); border-radius: 10px; padding: 12px; background: #fff; color: inherit; text-decoration: none; } .im-card:hover { border-color: var(--accent); } .im-card strong { display: block; margin-bottom: 3px; } .im-card span { font-size: 12.5px; color: var(--muted); }</style>
<?php $cls = ['UPLOADED' => 'status-on-hold', 'MAPPED' => 'status-in-progress', 'RUNNING' => 'status-in-progress', 'COMMITTED' => 'status-completed', 'DISCARDED' => 'status-cancelled']; ?>
<div class="page-heading"><div><h1>Import data</h1><p>Bring in products, patterns and tooling from a spreadsheet: Excel, CSV or a Google Sheet.</p></div></div>
<?php if($errors->any()): ?><div class="alert alert-error"><?php echo e($errors->first()); ?></div><?php endif; ?>
<div class="panel"><div class="panel-header"><h2>What do you want to import?</h2></div><div class="panel-body">
<div class="im-cards">
<?php $__currentLoopData = $entities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a class="im-card" href="<?php echo e(route('imports.create', ['entity' => $k])); ?>"><strong><?php echo e($e['label']); ?></strong><span><?php echo e($e['blurb']); ?></span></a>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<p class="muted small" style="margin-top:10px">Order matters for tooling: import cores before the core boxes that refer to them, and patterns and products before the pattern cavities that link them. Every import shows a preview first and changes nothing until you confirm.</p>
</div></div>
<div class="tab-marker" data-tab-start="Recent imports"></div>
<div class="panel"><div class="panel-header"><h2>Recent imports</h2></div>
<?php if($recent->isEmpty()): ?><div class="empty-state">No imports yet.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>When</th><th>What</th><th>Source</th><th>Result</th><th>Status</th><th>By</th><th></th></tr></thead><tbody>
<?php $__currentLoopData = $recent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><td class="small"><?php echo e($r->created_at->format('d M H:i')); ?></td><td><?php echo e(config('imports.entities.'.$r->entity.'.label')); ?></td><td class="small"><?php echo e($r->source_type); ?> · <?php echo e(\Illuminate\Support\Str::limit($r->source_label, 40)); ?></td>
<td class="small"><?php if($r->counts): ?><?php echo e($r->counts['CREATE']); ?> added · <?php echo e($r->counts['UPDATE']); ?> updated · <?php echo e($r->counts['ERROR']); ?> errors <?php else: ?> — <?php endif; ?></td>
<td><span class="status <?php echo e($cls[$r->status] ?? ''); ?>"><?php echo e($r->status); ?></span></td><td class="small"><?php echo e($r->created_by_name ?? '—'); ?></td>
<td><?php if($r->status !== 'DISCARDED'): ?><a href="<?php echo e(route('imports.show', $r)); ?>"><?php echo e($r->status === 'COMMITTED' ? 'Open' : 'Continue'); ?> →</a><?php endif; ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div><?php endif; ?>
</div>
<div class="tab-marker" data-tab-start="Saved mappings"></div>
<div class="panel"><div class="panel-header"><h2>Saved mappings</h2></div>
<?php if($templates->isEmpty()): ?><div class="empty-state">None yet. On the mapping screen, give the mapping a name to reuse it for the next file with the same headings.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>For</th><th>Columns</th><th></th></tr></thead><tbody>
<?php $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($t->name); ?></td><td><?php echo e(config('imports.entities.'.$t->entity.'.label')); ?></td><td class="small muted"><?php echo e(implode(', ', array_slice($t->headers, 0, 8))); ?><?php echo e(count($t->headers) > 8 ? '…' : ''); ?></td>
<td><form method="POST" action="<?php echo e(route('imports.templates.destroy', $t)); ?>" onsubmit="return confirm('Remove this saved mapping?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-danger" type="submit">Remove</button></form></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/imports/index.blade.php ENDPATH**/ ?>