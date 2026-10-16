<?php $__env->startSection('title', 'Approval rules'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Approval rules</h1><p>Switch an action on to require sign-off before it can happen. Segregation of duties: the person who raised a request cannot approve it (unless allowed here) and nobody can approve twice.</p></div></div>
<form method="POST" action="<?php echo e(route('approval-policies.update')); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Action</th><th>Needs approval</th><th>Approvals needed</th><th>Who can approve</th><th>Requester may approve own</th></tr></thead><tbody>
<?php $__currentLoopData = $actions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php ($p = $policies[$key] ?? null); ?><tr><td><?php echo e($label); ?><div class="muted small"><?php echo e($key); ?></div></td>
<td><input type="checkbox" name="p[<?php echo e($key); ?>][is_active]" value="1" <?php if($p?->is_active): echo 'checked'; endif; ?>></td>
<td><input type="number" min="1" max="5" name="p[<?php echo e($key); ?>][required_approvals]" value="<?php echo e($p?->required_approvals ?? 1); ?>" style="width:70px"></td>
<td><select name="p[<?php echo e($key); ?>][permission]"><?php $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pk => $pl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($pk); ?>" <?php if(($p?->permission ?? 'approvals.decide') === $pk): echo 'selected'; endif; ?>><?php echo e($pl); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></td>
<td><input type="checkbox" name="p[<?php echo e($key); ?>][allow_self_approval]" value="1" <?php if($p?->allow_self_approval): echo 'checked'; endif; ?>></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div><div class="panel-body"><button class="btn btn-primary">Save</button></div></div></form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/approvals/policies.blade.php ENDPATH**/ ?>