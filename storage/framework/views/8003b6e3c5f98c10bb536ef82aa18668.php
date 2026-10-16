<?php $__env->startSection('title', 'My payslips'); ?>
<?php $__env->startSection('breadcrumb', 'My payslips'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>My payslips</h1><p>Your own pay, once each month's payroll is approved.</p></div></div>
<?php if(! $me): ?><div class="alert alert-error">Your login is not linked to an employee record, so there are no payslips to show. Ask HR to link it.</div>
<?php else: ?><div class="panel"><?php if($slips->isEmpty()): ?><div class="empty-state">No approved payslip yet.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Month</th><th>Gross</th><th>Deductions</th><th>Net pay</th><th>Payment</th></tr></thead><tbody>
<?php $__currentLoopData = $slips; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><a href="<?php echo e(route('payslips.show', $s)); ?>"><strong><?php echo e($s->period->month->format('F Y')); ?></strong></a></td><td><?php echo e(number_format($s->gross)); ?></td><td><?php echo e(number_format($s->total_deductions)); ?></td><td><strong><?php echo e(number_format($s->net)); ?></strong></td><td><?php echo e($s->status === 'PAID' ? 'Paid '.$s->paid_on?->format('d M') : ucfirst(strtolower($s->status))); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div><?php endif; ?></div><?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/payroll/my.blade.php ENDPATH**/ ?>