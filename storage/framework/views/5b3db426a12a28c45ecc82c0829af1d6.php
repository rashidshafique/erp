<?php $__env->startSection('title', 'Payroll'); ?>
<?php $__env->startSection('breadcrumb', 'Payroll'); ?>
<?php $__env->startSection('content'); ?>
<?php $cls = ['DRAFT' => 'status-on-hold', 'CALCULATED' => 'status-in-progress', 'APPROVED' => 'status-released', 'PAID' => 'status-completed']; $m = fn ($v) => number_format((float) $v); ?>
<div class="page-heading"><div><h1>Payroll</h1><p>One payroll a month: calculate from attendance and leave, check, approve, then pay.</p></div>
<div class="actions"><a class="btn" href="<?php echo e(route('hr.payroll.advances')); ?>">Advances &amp; loans</a><a class="btn" href="<?php echo e(route('hr.payroll.settlements')); ?>">Final settlements</a><a class="btn" href="<?php echo e(route('hr.payroll.statutory')); ?>">Statutory reports</a><a class="btn" href="<?php echo e(route('hr.payroll.setup')); ?>">Payroll setup</a></div></div>
<?php if(session('error')): ?><div class="alert alert-error"><?php echo e(session('error')); ?></div><?php endif; ?>
<?php echo $__env->make('hr.payroll._tabs_note', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('hr._ui', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php
    $by = $periods->groupBy('status'); $last = $periods->first(); $ls = $last?->summary ?? []; $prevP = $periods->skip(1)->first(); $ps = $prevP?->summary ?? [];
    $delta = isset($ls['net'], $ps['net']) && $ps['net'] > 0 ? round(100 * ($ls['net'] - $ps['net']) / $ps['net'], 1) : null;
    $strip = [['Draft', $by->get('DRAFT', collect())->count(), 'file-text'], ['Calculated', $by->get('CALCULATED', collect())->count(), 'list-check'], ['Approved', $by->get('APPROVED', collect())->count(), 'check-circle'], ['Paid', $by->get('PAID', collect())->count(), 'wallet']];
?>
<div class="hr-cards">
<?php $__currentLoopData = $strip; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$l, $n, $ic]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div class="hr-card"><span class="hr-ico"><?php echo \App\Support\NavIcons::get($ic, 18); ?></span><div><div class="hr-l"><?php echo e($l); ?></div><div class="hr-n"><?php echo e(str_pad((string) $n, 2, '0', STR_PAD_LEFT)); ?></div></div></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<div class="hr-card"><span class="hr-ico"><?php echo \App\Support\NavIcons::get('receipt', 18); ?></span><div><div class="hr-l"><?php echo e($last ? $last->month->format('M Y').' net pay' : 'Latest net pay'); ?></div><div class="hr-n"><?php echo e(isset($ls['net']) ? $m($ls['net']) : '—'); ?><?php if($delta !== null): ?><span class="hr-d <?php echo e($delta == 0 ? 'flat' : ($delta > 0 ? 'up' : 'down')); ?>"><?php echo e($delta > 0 ? '+' : ''); ?><?php echo e($delta); ?>% vs prev</span><?php endif; ?></div></div></div>
</div>
<div class="panel"><div class="panel-body"><form method="POST" action="<?php echo e(route('hr.payroll.store')); ?>" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap"><?php echo csrf_field(); ?>
<div class="field" style="margin:0"><label for="month">Open a payroll for</label><input id="month" type="month" name="month" value="<?php echo e($next->format('Y-m')); ?>" required style="width:170px"></div><button class="btn btn-primary">Open payroll</button></form></div></div>
<div class="panel"><?php if($periods->isNotEmpty()): ?><div class="panel-body" style="padding-bottom:0"><input id="pr-q" type="search" placeholder="Search month or status…" style="max-width:320px" aria-label="Search payrolls" autocomplete="off"></div><?php endif; ?> <?php if($periods->isEmpty()): ?><div class="empty-state">No payroll has been opened yet.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Month</th><th>People</th><th>Gross</th><th>Deductions</th><th>Net pay</th><th>Status</th></tr></thead><tbody>
<?php $__currentLoopData = $periods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php $s = $p->summary ?? []; ?>
<tr data-q="<?php echo e(mb_strtolower($p->month->format('F Y').' '.$p->code.' '.$p->status)); ?>"><td><a href="<?php echo e(route('hr.payroll.show', $p)); ?>"><strong><?php echo e($p->month->format('F Y')); ?></strong></a><div class="muted small"><?php echo e($p->code); ?></div></td>
<td><?php echo e($s['people'] ?? '—'); ?></td><td><?php echo e(isset($s['gross']) ? $m($s['gross']) : '—'); ?></td><td><?php echo e(isset($s['gross']) ? $m($s['gross'] - $s['net']) : '—'); ?></td><td><strong><?php echo e(isset($s['net']) ? $m($s['net']) : '—'); ?></strong></td>
<td><span class="status <?php echo e($cls[$p->status]); ?>"><?php echo e(ucfirst(strtolower($p->status))); ?></span></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div><?php endif; ?></div>
<script>(function(){var q=document.getElementById('pr-q');if(!q)return;q.addEventListener('input',function(){var t=q.value.trim().toLowerCase();document.querySelectorAll('tr[data-q]').forEach(function(r){r.style.display=t===''||r.dataset.q.indexOf(t)>=0?'':'none';});});})();</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/payroll/index.blade.php ENDPATH**/ ?>