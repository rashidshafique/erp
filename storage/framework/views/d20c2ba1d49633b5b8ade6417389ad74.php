<?php
    use App\Models\AttendanceRecord as AR;
    $span = max(1, $hi - $lo);
    $pos = fn ($m) => round(100 * ($m - $lo) / $span, 2);
    $clock = fn ($m) => sprintf('%02d:%02d', intdiv(((int) $m) % 1440, 60), ((int) $m) % 60);
    $st = config('hr.attendance.day_status');
    $mon = ['Pending' => 'PENDING'];
    $ini = strtoupper(mb_substr($e->first_name, 0, 1).mb_substr((string) $e->last_name, 0, 1));
    $ticks = range(intdiv($lo, 60), intdiv($hi, 60), max(1, (int) round(($hi - $lo) / 60 / 6)));
?>
<div class="hr-dh" data-person="<?php echo e($e->id); ?>">
    <span class="hr-avatar" aria-hidden="true"><?php echo e($ini); ?></span>
    <div style="min-width:0"><h2><?php echo e($e->full_name); ?> <span class="hr-chip"><?php echo e(ucfirst(strtolower($e->status))); ?></span></h2>
        <div class="sub"><?php echo e($e->code); ?> &middot; <?php echo e($e->designation?->name ?? 'No title'); ?><?php echo e($e->department ? ' · '.$e->department->name : ''); ?><?php echo e($e->work_email ? ' · '.$e->work_email : ''); ?></div></div>
    <div class="hr-nav"><button type="button" data-step="-1" aria-label="Previous person">&lsaquo;</button><button type="button" data-step="1" aria-label="Next person">&rsaquo;</button><span data-pos></span><button type="button" data-close-drawer aria-label="Close">&times;</button></div>
</div>
<div class="hr-tabs" role="tablist"><button type="button" class="on" data-pane="a">Attendance</button><button type="button" data-pane="o">Overtime</button><button type="button" data-pane="t">Time off</button></div>

<div class="hr-pane on" data-p="a">
    <div class="hr-tools">
        <select data-days aria-label="Range"><?php $__currentLoopData = [7, 14, 30]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($n); ?>" <?php if($days === $n): echo 'selected'; endif; ?>>Last <?php echo e($n); ?> days</option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
        <span class="muted small"><?php echo e($from->format('M d')); ?> &ndash; <?php echo e($to->format('M d, Y')); ?></span>
        <a class="btn btn-small" href="<?php echo e(route('hr.employees.show', $e)); ?>">Open employee</a>
    </div>
    <div class="hr-mini"><?php $__currentLoopData = $cards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div><div class="l"><?php echo e($c['label']); ?></div><div class="n"><?php echo e($c['n']); ?>

            <?php if($c['delta'] !== null): ?><span class="hr-d <?php echo e($c['delta'] == 0 ? 'flat' : (($c['delta'] > 0) === $c['good'] ? 'up' : 'down')); ?>"><?php echo e($c['delta'] > 0 ? '+' : ''); ?><?php echo e($c['delta']); ?>% vs previous</span><?php endif; ?></div></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
    <div class="hr-key"><span><i style="background:#7c3aed"></i>Working time</span><span><i style="background:#f59e0b"></i>Overtime</span><span><i style="background:#ef4444"></i>Late</span></div>
    <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php $r = $row['rec']; $b = $row['bar']; ?>
        <div class="tl-row">
            <div class="tl-head"><?php echo e($row['date']->isToday() ? 'Today' : ($row['date']->isYesterday() ? 'Yesterday' : $row['date']->format('l, d M'))); ?>

                <span><?php if($r && $r->worked_minutes): ?>Duration: <?php echo e(AR::hm($r->worked_minutes)); ?><?php elseif($r): ?><?php echo e($st[$r->status] ?? $r->status); ?><?php else: ?> No record <?php endif; ?></span></div>
            <?php if($b): ?>
                <div class="tl-main">
                    <div class="tl-t"><small>Clock-in</small><?php echo e($r->check_in->format('g:i A')); ?></div>
                    <div><div class="tl-axis"><?php $__currentLoopData = $ticks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span style="left:<?php echo e($pos($h * 60)); ?>%"><?php echo e(sprintf('%02d:00', $h % 24)); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
                        <div class="tl-track">
                            <?php if($b['late'] > 0): ?><div class="tl-seg l" style="left:<?php echo e($pos($b['in'] - $b['late'])); ?>%;width:<?php echo e(round(100 * $b['late'] / $span, 2)); ?>%" title="Late <?php echo e($b['late']); ?> min"></div><?php endif; ?>
                            <?php if($b['out']): ?>
                                <div class="tl-seg w" style="left:<?php echo e($pos($b['in'])); ?>%;width:<?php echo e(round(100 * max(0, $b['out'] - $b['in'] - $b['ot']) / $span, 2)); ?>%" title="Working time"></div>
                                <?php if($b['ot'] > 0): ?><div class="tl-seg o" style="left:<?php echo e($pos($b['out'] - $b['ot'])); ?>%;width:<?php echo e(round(100 * $b['ot'] / $span, 2)); ?>%" title="Overtime <?php echo e(AR::hm($b['ot'])); ?>"></div><?php endif; ?>
                            <?php else: ?>
                                <div class="tl-seg w" style="left:<?php echo e($pos($b['in'])); ?>%;width:1.2%" title="Still in, or no check-out"></div>
                            <?php endif; ?>
                        </div></div>
                    <div class="tl-t r"><small>Clock-out</small><?php echo e($r->check_out ? $r->check_out->format('g:i A') : 'Not yet'); ?></div>
                </div>
                <?php if($r->note): ?><div class="tl-note"><?php echo e($r->note); ?></div><?php endif; ?>
            <?php else: ?>
                <div class="tl-track off" title="<?php echo e($r ? ($st[$r->status] ?? '') : 'No record'); ?>"></div>
                <?php if($r && $r->note): ?><div class="tl-note"><?php echo e($r->note); ?></div><?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <p class="muted small" style="margin-top:6px">Lunch and tea breaks are not punched in this system, so they are not drawn. Worked time already has the shift's break taken off.</p>
</div>

<div class="hr-pane" data-p="o">
    <?php if($overtime->isEmpty()): ?><div class="empty-state">No overtime in this range.</div><?php else: ?>
    <table class="hr-tbl"><thead><tr><th>Day</th><th>Overtime</th><th>Approved</th><th>Status</th></tr></thead><tbody>
    <?php $__currentLoopData = $overtime; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($r->work_date->format('D, d M')); ?></td><td><?php echo e(AR::hm($r->overtime_minutes)); ?></td><td><?php echo e(AR::hm($r->overtime_approved_minutes)); ?></td><td><span class="status <?php echo e(['APPROVED' => 'status-completed', 'REJECTED' => 'status-cancelled', 'PENDING' => 'status-on-hold'][$r->overtime_status] ?? ''); ?>"><?php echo e(ucfirst(strtolower($r->overtime_status))); ?></span></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody></table>
    <p style="margin-top:12px"><a class="btn btn-small" href="<?php echo e(route('hr.attendance.overtime')); ?>">Approve overtime</a></p><?php endif; ?>
</div>

<div class="hr-pane" data-p="t">
    <?php if($leaves->isEmpty()): ?><div class="empty-state">No leave in this range.</div><?php else: ?>
    <table class="hr-tbl"><thead><tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th></tr></thead><tbody>
    <?php $__currentLoopData = $leaves; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($l->type->name); ?></td><td><?php echo e($l->from_date->format('d M')); ?></td><td><?php echo e($l->to_date->format('d M')); ?></td><td><?php echo e(rtrim(rtrim(number_format($l->days, 2), '0'), '.')); ?><?php echo e($l->half_day ? ' (half)' : ''); ?></td><td><span class="status <?php echo e(['APPROVED' => 'status-completed', 'REJECTED' => 'status-cancelled', 'PENDING' => 'status-on-hold'][$l->status] ?? ''); ?>"><?php echo e(ucfirst(strtolower($l->status))); ?></span></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody></table><?php endif; ?>
    <p style="margin-top:12px"><a class="btn btn-small" href="<?php echo e(route('hr.leave')); ?>">Leave balances</a></p>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/attendance/_person.blade.php ENDPATH**/ ?>