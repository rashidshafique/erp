<?php $__env->startSection('title', 'Employees'); ?>
<?php $__env->startSection('breadcrumb', 'Employees'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Employees</h1><p>Everyone on the payroll, past and present.</p></div>
<div class="actions"><a class="btn" href="<?php echo e(route('directory.index')); ?>">Directory</a><a class="btn btn-primary" href="<?php echo e(route('hr.employees.create')); ?>">+ New employee</a></div></div>
<div class="panel"><div class="panel-body">
<form method="GET" class="filters">
    <div class="field"><label for="search">Search</label><input id="search" name="search" value="<?php echo e(request('search')); ?>" placeholder="Name, code, CNIC or phone"></div>
    <div class="field"><label for="department">Department</label><select id="department" name="department"><option value="">All departments</option><?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($d->id); ?>" <?php if(request('department') === $d->id): echo 'selected'; endif; ?>><?php echo e($d->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <div class="field"><label for="location">Location</label><select id="location" name="location"><option value="">All locations</option><?php $__currentLoopData = $locations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($l->id); ?>" <?php if(request('location') === $l->id): echo 'selected'; endif; ?>><?php echo e($l->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">Current staff</option><?php $__currentLoopData = config('hr.statuses'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(request('status') === $k): echo 'selected'; endif; ?>><?php echo e($t); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <button class="btn btn-primary" type="submit">Filter</button><a class="btn" href="<?php echo e(route('hr.employees.index')); ?>">Reset</a>
</form></div></div>
<div class="panel">
<?php if($employees->isEmpty()): ?><div class="empty-state">No employees found. <a href="<?php echo e(route('hr.employees.create')); ?>">Add the first one</a>.</div>
<?php else: ?>
<div class="table-wrap"><table>
<thead><tr><th>Code</th><th>Name</th><th>Designation</th><th>Department</th><th>Location</th><th>Joined</th><th>Status</th></tr></thead>
<tbody><?php $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $x): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<tr><td><a href="<?php echo e(route('hr.employees.show', $x)); ?>"><strong><?php echo e($x->code); ?></strong></a></td><td><?php echo e($x->full_name); ?></td><td><?php echo e($x->designation?->name ?? '—'); ?></td><td><?php echo e($x->department?->name ?? '—'); ?></td><td><?php echo e($x->location?->name ?? '—'); ?></td><td><?php echo e($x->joined_on->format('d M Y')); ?></td><td><?php echo $__env->make('hr._chip', ['s' => $x->status], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div>
<div class="panel-body"><?php echo e($employees->links()); ?></div>
<?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/employees/index.blade.php ENDPATH**/ ?>