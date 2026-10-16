<?php $__env->startSection('title', 'Directory'); ?>
<?php $__env->startSection('breadcrumb', 'People directory'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Directory</h1><p>Who is who, and who reports to whom.</p></div></div>

<div class="tab-marker" data-tab-start="People"></div>
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="search">Search</label><input id="search" name="search" value="<?php echo e(request('search')); ?>" placeholder="Name, role or department"></div>
    <div class="field"><label for="department">Department</label><select id="department" name="department"><option value="">All departments</option><?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($d->id); ?>" <?php if(request('department') === $d->id): echo 'selected'; endif; ?>><?php echo e($d->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <div class="field"><label for="location">Location</label><select id="location" name="location"><option value="">All locations</option><?php $__currentLoopData = $locations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($l->id); ?>" <?php if(request('location') === $l->id): echo 'selected'; endif; ?>><?php echo e($l->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <button class="btn btn-primary">Filter</button><a class="btn" href="<?php echo e(route('directory.index')); ?>">Reset</a></form></div></div>
<?php if($people->isEmpty()): ?><div class="panel"><div class="empty-state">No one matches.</div></div><?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px">
<?php $__currentLoopData = $people; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php $ph = \App\Models\Attachment::coverFor('employee', $p->id); ?>
<div class="panel" style="margin:0"><div class="panel-body" style="display:flex;gap:12px;align-items:flex-start">
    <?php if($ph): ?><img src="<?php echo e(route('attachments.download', $ph)); ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:50%;padding:0;min-height:0;border:1px solid var(--border)"><?php else: ?><div style="width:48px;height:48px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex:none"><?php echo e(strtoupper(mb_substr($p->first_name, 0, 1).mb_substr((string) $p->last_name, 0, 1))); ?></div><?php endif; ?>
    <div style="min-width:0"><strong><?php echo e($p->full_name); ?></strong><div class="muted small"><?php echo e($p->designation?->name ?? '—'); ?></div><div class="small"><?php echo e($p->department?->name); ?><?php echo e($p->location ? ' · '.$p->location->name : ''); ?></div>
    <?php if($p->phone): ?><div class="small"><?php echo e($p->phone); ?></div><?php endif; ?> <?php if($p->work_email): ?><div class="small"><?php echo e($p->work_email); ?></div><?php endif; ?></div>
</div></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>

<div class="tab-marker" data-tab-start="Reporting lines"></div>
<div class="panel"><div class="panel-body">
<?php if($roots->isEmpty()): ?><div class="empty-state">No one has been added yet.</div><?php else: ?>
<ul class="org-tree"><?php $__currentLoopData = $roots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo $__env->make('hr._tree', ['node' => $r, 'byManager' => $byManager, 'depth' => 0], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul><?php endif; ?>
</div></div>
<style>.org-tree, .org-tree ul { list-style: none; margin: 0; padding-left: 22px; border-left: 1px dashed var(--border); } .org-tree { border: 0; padding-left: 0; } .org-tree li { margin: 6px 0; }</style>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/directory.blade.php ENDPATH**/ ?>