<?php $__env->startSection('title', 'Organisation'); ?>
<?php $__env->startSection('breadcrumb', 'People organisation'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Organisation</h1><p>Departments, designations and grades, locations, cost centres and the joining and leaving checklists.</p></div></div>
<?php if(session('error')): ?><div class="alert alert-error"><?php echo e(session('error')); ?></div><?php endif; ?>
<?php
    $tab = request('tab');
    $codeHelp = 'Leave blank to generate one.';
    $deptOpts = $departments->mapWithKeys(fn ($d) => [$d->id => $d->path()])->all();
    $ccOpts = $costCentres->mapWithKeys(fn ($c) => [$c->id => $c->code.' · '.$c->name])->all();
    $headOpts = $heads->mapWithKeys(fn ($h) => [$h->id => $h->code.' · '.$h->full_name])->all();
    $gradeOpts = $grades->mapWithKeys(fn ($g) => [$g->id => $g->name])->all();
    $money = fn ($v) => $v === null ? '—' : 'PKR '.number_format($v);
?>
<style>.row-edit summary { cursor: pointer; color: var(--accent); font-weight: 600; } .row-edit[open] { padding: 10px 0; }</style>


<div class="tab-marker" data-tab-start="Departments"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Department</th><th>Part of</th><th>Cost centre</th><th>Head</th><th>People</th><th></th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr><td><strong><?php echo e($d->code); ?></strong></td><td><?php echo e($d->name); ?> <?php if (! ($d->active)): ?><span class="status status-cancelled">Inactive</span><?php endif; ?></td><td><?php echo e($d->parent?->name ?? '—'); ?></td><td><?php echo e($d->costCentre?->code ?? '—'); ?></td><td><?php echo e($d->head?->full_name ?? '—'); ?></td><td><?php echo e($d->employees_count); ?></td>
<td><details class="row-edit"><summary>Edit</summary>
<form method="POST" action="<?php echo e(route('hr.organisation.update', ['departments', $d->id])); ?>" class="form-grid"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
    <?php echo $__env->make('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => $d->code], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->make('hr._in', ['name' => 'name', 'label' => 'Name', 'val' => $d->name, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'parent_id', 'label' => 'Part of', 'items' => array_diff_key($deptOpts, [$d->id => 1]), 'sel' => $d->parent_id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'cost_centre_id', 'label' => 'Cost centre', 'items' => $ccOpts, 'sel' => $d->cost_centre_id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'head_employee_id', 'label' => 'Head', 'items' => $headOpts, 'sel' => $d->head_employee_id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="field"><label><input type="checkbox" name="active" value="1" <?php if($d->active): echo 'checked'; endif; ?>> Active</label></div>
    <div class="field"><button class="btn btn-primary">Save</button></div></form>
<form method="POST" action="<?php echo e(route('hr.organisation.destroy', ['departments', $d->id])); ?>" onsubmit="return confirm('Delete this department?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-small btn-danger">Delete</button></form></details></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="7" class="muted">None yet. Add the first below.</td></tr><?php endif; ?></tbody></table></div>
<div class="panel-body"><form method="POST" action="<?php echo e(route('hr.organisation.store', 'departments')); ?>"><?php echo csrf_field(); ?><div class="form-grid">
    <?php echo $__env->make('hr._in', ['name' => 'name', 'label' => 'New department', 'val' => '', 'required' => true, 'ph' => 'e.g. Moulding'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'parent_id', 'label' => 'Part of', 'items' => $deptOpts, 'sel' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'cost_centre_id', 'label' => 'Cost centre', 'items' => $ccOpts, 'sel' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => '', 'help' => $codeHelp], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div><p><button class="btn btn-primary">Add department</button></p></form></div></div>


<div class="tab-marker" data-tab-start="Designations"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Designation</th><th>Grade</th><th>People</th><th></th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $designations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr><td><strong><?php echo e($d->code); ?></strong></td><td><?php echo e($d->name); ?> <?php if (! ($d->active)): ?><span class="status status-cancelled">Inactive</span><?php endif; ?></td><td><?php echo e($d->grade?->name ?? '—'); ?></td><td><?php echo e($employeeCounts['designation_id'][$d->id] ?? 0); ?></td>
<td><details class="row-edit"><summary>Edit</summary><form method="POST" action="<?php echo e(route('hr.organisation.update', ['designations', $d->id])); ?>" class="form-grid"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
    <?php echo $__env->make('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => $d->code], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->make('hr._in', ['name' => 'name', 'label' => 'Name', 'val' => $d->name, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'grade_id', 'label' => 'Grade', 'items' => $gradeOpts, 'sel' => $d->grade_id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="field"><label><input type="checkbox" name="active" value="1" <?php if($d->active): echo 'checked'; endif; ?>> Active</label></div><div class="field"><button class="btn btn-primary">Save</button></div></form>
<form method="POST" action="<?php echo e(route('hr.organisation.destroy', ['designations', $d->id])); ?>" onsubmit="return confirm('Delete this designation?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-small btn-danger">Delete</button></form></details></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5" class="muted">None yet.</td></tr><?php endif; ?></tbody></table></div>
<div class="panel-body"><form method="POST" action="<?php echo e(route('hr.organisation.store', 'designations')); ?>"><?php echo csrf_field(); ?><div class="form-grid">
    <?php echo $__env->make('hr._in', ['name' => 'name', 'label' => 'New designation', 'val' => '', 'required' => true, 'ph' => 'e.g. Moulder'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'grade_id', 'label' => 'Grade', 'items' => $gradeOpts, 'sel' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => '', 'help' => $codeHelp], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div><p><button class="btn btn-primary">Add designation</button></p></form></div></div>


<div class="tab-marker" data-tab-start="Grades"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Grade</th><th>Rank</th><th>Salary band</th><th></th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $grades; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr><td><strong><?php echo e($g->code); ?></strong></td><td><?php echo e($g->name); ?> <?php if (! ($g->active)): ?><span class="status status-cancelled">Inactive</span><?php endif; ?></td><td><?php echo e($g->rank); ?></td><td><?php echo e($money($g->min_salary)); ?> – <?php echo e($money($g->max_salary)); ?></td>
<td><details class="row-edit"><summary>Edit</summary><form method="POST" action="<?php echo e(route('hr.organisation.update', ['grades', $g->id])); ?>" class="form-grid"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
    <?php echo $__env->make('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => $g->code], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->make('hr._in', ['name' => 'name', 'label' => 'Name', 'val' => $g->name, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'rank', 'label' => 'Rank', 'type' => 'number', 'val' => $g->rank, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'min_salary', 'label' => 'Band from (PKR)', 'type' => 'number', 'val' => $g->min_salary], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->make('hr._in', ['name' => 'max_salary', 'label' => 'Band to (PKR)', 'type' => 'number', 'val' => $g->max_salary], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="field"><label><input type="checkbox" name="active" value="1" <?php if($g->active): echo 'checked'; endif; ?>> Active</label></div><div class="field"><button class="btn btn-primary">Save</button></div></form>
<form method="POST" action="<?php echo e(route('hr.organisation.destroy', ['grades', $g->id])); ?>" onsubmit="return confirm('Delete this grade?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-small btn-danger">Delete</button></form></details></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5" class="muted">None yet.</td></tr><?php endif; ?></tbody></table></div>
<div class="panel-body"><form method="POST" action="<?php echo e(route('hr.organisation.store', 'grades')); ?>"><?php echo csrf_field(); ?><div class="form-grid">
    <?php echo $__env->make('hr._in', ['name' => 'name', 'label' => 'New grade', 'val' => '', 'required' => true, 'ph' => 'e.g. Skilled worker'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'rank', 'label' => 'Rank', 'type' => 'number', 'val' => ($grades->max('rank') ?? 0) + 1, 'required' => true, 'help' => 'Higher is more senior.'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'min_salary', 'label' => 'Band from (PKR)', 'type' => 'number', 'val' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->make('hr._in', ['name' => 'max_salary', 'label' => 'Band to (PKR)', 'type' => 'number', 'val' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => '', 'help' => $codeHelp], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div><p><button class="btn btn-primary">Add grade</button></p></form></div></div>


<div class="tab-marker" data-tab-start="Locations"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Location</th><th>Kind</th><th>City</th><th>People</th><th></th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $locations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr><td><strong><?php echo e($l->code); ?></strong></td><td><?php echo e($l->name); ?> <?php if (! ($l->active)): ?><span class="status status-cancelled">Inactive</span><?php endif; ?></td><td><?php echo e(config('hr.location_kinds')[$l->kind] ?? $l->kind); ?></td><td><?php echo e($l->city ?: '—'); ?></td><td><?php echo e($employeeCounts['location_id'][$l->id] ?? 0); ?></td>
<td><details class="row-edit"><summary>Edit</summary><form method="POST" action="<?php echo e(route('hr.organisation.update', ['locations', $l->id])); ?>" class="form-grid"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
    <?php echo $__env->make('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => $l->code], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->make('hr._in', ['name' => 'name', 'label' => 'Name', 'val' => $l->name, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'kind', 'label' => 'Kind', 'items' => config('hr.location_kinds'), 'sel' => $l->kind, 'blank' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'city', 'label' => 'City', 'val' => $l->city], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->make('hr._in', ['name' => 'address', 'label' => 'Address', 'val' => $l->address], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="field"><label><input type="checkbox" name="active" value="1" <?php if($l->active): echo 'checked'; endif; ?>> Active</label></div><div class="field"><button class="btn btn-primary">Save</button></div></form>
<form method="POST" action="<?php echo e(route('hr.organisation.destroy', ['locations', $l->id])); ?>" onsubmit="return confirm('Delete this location?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-small btn-danger">Delete</button></form></details></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="6" class="muted">None yet.</td></tr><?php endif; ?></tbody></table></div>
<div class="panel-body"><form method="POST" action="<?php echo e(route('hr.organisation.store', 'locations')); ?>"><?php echo csrf_field(); ?><div class="form-grid">
    <?php echo $__env->make('hr._in', ['name' => 'name', 'label' => 'New location', 'val' => '', 'required' => true, 'ph' => 'e.g. Main foundry'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'kind', 'label' => 'Kind', 'items' => config('hr.location_kinds'), 'sel' => 'PLANT', 'blank' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'city', 'label' => 'City', 'val' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->make('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => '', 'help' => $codeHelp], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div><p><button class="btn btn-primary">Add location</button></p></form></div></div>


<div class="tab-marker" data-tab-start="Cost centres"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Cost centre</th><th>People</th><th></th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $costCentres; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr><td><strong><?php echo e($c->code); ?></strong></td><td><?php echo e($c->name); ?> <?php if (! ($c->active)): ?><span class="status status-cancelled">Inactive</span><?php endif; ?></td><td><?php echo e($employeeCounts['cost_centre_id'][$c->id] ?? 0); ?></td>
<td><details class="row-edit"><summary>Edit</summary><form method="POST" action="<?php echo e(route('hr.organisation.update', ['cost-centres', $c->id])); ?>" class="form-grid"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
    <?php echo $__env->make('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => $c->code], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?> <?php echo $__env->make('hr._in', ['name' => 'name', 'label' => 'Name', 'val' => $c->name, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="field"><label><input type="checkbox" name="active" value="1" <?php if($c->active): echo 'checked'; endif; ?>> Active</label></div><div class="field"><button class="btn btn-primary">Save</button></div></form>
<form method="POST" action="<?php echo e(route('hr.organisation.destroy', ['cost-centres', $c->id])); ?>" onsubmit="return confirm('Delete this cost centre?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-small btn-danger">Delete</button></form></details></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="4" class="muted">None yet.</td></tr><?php endif; ?></tbody></table></div>
<div class="panel-body"><form method="POST" action="<?php echo e(route('hr.organisation.store', 'cost-centres')); ?>"><?php echo csrf_field(); ?><div class="form-grid">
    <?php echo $__env->make('hr._in', ['name' => 'name', 'label' => 'New cost centre', 'val' => '', 'required' => true, 'ph' => 'e.g. Melt shop'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => '', 'help' => $codeHelp], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div><p><button class="btn btn-primary">Add cost centre</button></p></form></div></div>


<div class="tab-marker" data-tab-start="Checklists"></div>
<?php $__currentLoopData = ['ONBOARDING' => 'Joining checklist', 'EXIT' => 'Leaving checklist']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kind => $title): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div class="panel"><div class="panel-header"><h2><?php echo e($title); ?></h2><span class="muted small">Copied to each person when they join or leave</span></div>
<div class="table-wrap"><table><thead><tr><th>Task</th><th>Owner</th><th>Due (days after)</th><th></th></tr></thead><tbody>
<?php $__empty_1 = true; $__currentLoopData = $checklists->get($kind, collect()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr><td <?php if (! ($i->active)): ?> class="muted" <?php endif; ?>><?php echo e($i->title); ?></td><td><?php echo e(\App\Models\EmployeeTask::OWNERS[$i->owner] ?? $i->owner); ?></td><td><?php echo e($i->due_days); ?></td>
<td><details class="row-edit"><summary>Edit</summary><form method="POST" action="<?php echo e(route('hr.checklist.update', $i)); ?>" class="form-grid"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
    <?php echo $__env->make('hr._in', ['name' => 'title', 'label' => 'Task', 'val' => $i->title, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'owner', 'label' => 'Owner', 'items' => \App\Models\EmployeeTask::OWNERS, 'sel' => $i->owner, 'blank' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'due_days', 'label' => 'Due after (days)', 'type' => 'number', 'val' => $i->due_days, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="field"><label><input type="checkbox" name="active" value="1" <?php if($i->active): echo 'checked'; endif; ?>> Use it</label></div><div class="field"><button class="btn btn-primary">Save</button></div></form>
<form method="POST" action="<?php echo e(route('hr.checklist.destroy', $i)); ?>" onsubmit="return confirm('Remove this item?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-small btn-danger">Remove</button></form></details></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="4" class="muted">Empty.</td></tr><?php endif; ?></tbody></table></div>
<div class="panel-body"><form method="POST" action="<?php echo e(route('hr.checklist.store')); ?>"><?php echo csrf_field(); ?><input type="hidden" name="kind" value="<?php echo e($kind); ?>"><div class="form-grid">
    <?php echo $__env->make('hr._in', ['name' => 'title', 'label' => 'Add a task', 'val' => '', 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._sel', ['name' => 'owner', 'label' => 'Owner', 'items' => \App\Models\EmployeeTask::OWNERS, 'sel' => 'HR', 'blank' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('hr._in', ['name' => 'due_days', 'label' => 'Due after (days)', 'type' => 'number', 'val' => 0, 'required' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div><p><button class="btn btn-primary">Add</button></p></form></div></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php if($tab): ?><script>document.addEventListener('DOMContentLoaded', function () { var m = {departments:'Departments',designations:'Designations',grades:'Grades',locations:'Locations','cost-centres':'Cost centres',checklists:'Checklists'}[<?php echo json_encode($tab, 15, 512) ?>]; if (!m) return; var b = [].slice.call(document.querySelectorAll('.tabbar button')).filter(function (x) { return x.textContent.trim() === m; })[0]; if (b) b.click(); });</script><?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/organisation.blade.php ENDPATH**/ ?>