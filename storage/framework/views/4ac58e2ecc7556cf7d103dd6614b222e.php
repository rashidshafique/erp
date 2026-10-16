<div style="display:flex;gap:6px;margin:0 0 14px;flex-wrap:wrap">
<?php $__currentLoopData = ['hr.attendance' => 'Daily sheet', 'hr.attendance.exceptions' => 'Exceptions', 'hr.attendance.overtime' => 'Overtime', 'hr.attendance.import' => 'Import punches']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<a class="btn <?php echo e(request()->routeIs($r, $r.'.run') ? 'btn-primary' : ''); ?>" href="<?php echo e(route($r)); ?>"><?php echo e($l); ?></a>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/attendance/_tabs.blade.php ENDPATH**/ ?>