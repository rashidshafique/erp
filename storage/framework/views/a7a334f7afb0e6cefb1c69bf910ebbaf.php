<?php
    $rows = $d['daily']; $n = count($rows);
    $W = 640; $H = 190; $pl = 34; $pr = 8; $pt = 10; $pb = 24; $iw = $W - $pl - $pr; $ih = $H - $pt - $pb;
    $x = fn ($i) => $pl + ($n > 1 ? $i * $iw / ($n - 1) : 0);
    $ys = array_filter(array_column($rows, 'yield'), fn ($v) => $v !== null);
    $lo = $ys ? max(0, floor(min($ys) / 10) * 10 - 10) : 0; $hi = 100;
    $yy = fn ($v) => $pt + $ih - ($v - $lo) / (($hi - $lo) ?: 1) * $ih;
    $line = []; foreach ($rows as $i => $r) { if ($r['yield'] !== null) { $line[] = round($x($i), 1).','.round($yy($r['yield']), 1); } }
    $smax = max(array_merge(array_filter(array_column($rows, 'scrap'), fn ($v) => $v !== null), [5]));
    $tmax = max(array_merge(array_column($rows, 'poured_kg'), [1]));
    $bw = max(4, $iw / max($n, 1) * 0.5);
    $tot = array_sum(array_column($rows, 'poured_kg'));
    $pieces = array_sum(array_column($rows, 'poured')); $rej = array_sum(array_column($rows, 'rejected'));
?>
<div class="card" data-chart>
    <div class="chead"><div><h2>Yield &amp; scrap</h2><p class="sub">Last <?php echo e($n); ?> days: good metal against metal poured, and pieces rejected.</p></div>
        <div class="tabs"><button type="button" class="on" data-t="y">Yield &amp; scrap</button><button type="button" data-t="t">Tonnes poured</button></div></div>
    <div data-pane="y">
        <svg viewBox="0 0 <?php echo e($W); ?> <?php echo e($H); ?>" style="width:100%;height:auto;display:block" role="img" aria-label="Pouring yield and scrap rate by day">
            <?php $__currentLoopData = [0, .5, 1]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php $gy = $pt + $ih * $g; ?><line x1="<?php echo e($pl); ?>" x2="<?php echo e($W - $pr); ?>" y1="<?php echo e($gy); ?>" y2="<?php echo e($gy); ?>" stroke="#e2e8f0"/><text x="<?php echo e($pl - 6); ?>" y="<?php echo e($gy + 4); ?>" text-anchor="end" font-size="10" fill="#64748b"><?php echo e(round($hi - ($hi - $lo) * $g)); ?>%</text><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if($r['scrap'] !== null && $r['scrap'] > 0): ?><?php $bh = max(2, $r['scrap'] / $smax * ($ih * .45)); ?><rect x="<?php echo e(round($x($i) - $bw / 2, 1)); ?>" y="<?php echo e(round($pt + $ih - $bh, 1)); ?>" width="<?php echo e(round($bw, 1)); ?>" height="<?php echo e(round($bh, 1)); ?>" rx="2" fill="#ef4444" opacity=".28"><title><?php echo e($r['date']->format('d M')); ?>: scrap <?php echo e($r['scrap']); ?>%</title></rect><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php if(count($line) > 1): ?><polyline points="<?php echo e(implode(' ', $line)); ?>" fill="none" stroke="var(--accent)" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/><?php endif; ?>
            <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if($r['yield'] !== null): ?><circle cx="<?php echo e(round($x($i), 1)); ?>" cy="<?php echo e(round($yy($r['yield']), 1)); ?>" r="3" fill="var(--accent)"><title><?php echo e($r['date']->format('d M')); ?>: yield <?php echo e($r['yield']); ?>%</title></circle><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php $__currentLoopData = [0, intdiv($n, 2), $n - 1]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(isset($rows[$i])): ?><text x="<?php echo e(round($x($i), 1)); ?>" y="<?php echo e($H - 6); ?>" text-anchor="<?php echo e($i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle')); ?>" font-size="10" fill="#64748b"><?php echo e($rows[$i]['date']->format('d M')); ?></text><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </svg>
        <div style="font-size:12px;color:var(--dx-sub);display:flex;gap:18px;margin-top:6px"><span><i class="dot"></i>Pouring yield</span><span><i class="dot" style="background:#ef4444;opacity:.5"></i>Rejected pieces (%)</span></div>
        <?php if(! count($line)): ?><div class="empty">No finished pours in this window yet, so there is nothing to plot.</div><?php endif; ?>
    </div>
    <div data-pane="t" hidden>
        <svg viewBox="0 0 <?php echo e($W); ?> <?php echo e($H); ?>" style="width:100%;height:auto;display:block" role="img" aria-label="Metal poured per day in tonnes">
            <?php $__currentLoopData = [0, .5, 1]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php $gy = $pt + $ih * $g; ?><line x1="<?php echo e($pl); ?>" x2="<?php echo e($W - $pr); ?>" y1="<?php echo e($gy); ?>" y2="<?php echo e($gy); ?>" stroke="#e2e8f0"/><text x="<?php echo e($pl - 6); ?>" y="<?php echo e($gy + 4); ?>" text-anchor="end" font-size="10" fill="#64748b"><?php echo e(number_format($tmax / 1000 * (1 - $g), 1)); ?></text><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if($r['poured_kg'] > 0): ?><?php $bh = $r['poured_kg'] / $tmax * $ih; ?><rect x="<?php echo e(round($x($i) - $bw / 2, 1)); ?>" y="<?php echo e(round($pt + $ih - $bh, 1)); ?>" width="<?php echo e(round($bw, 1)); ?>" height="<?php echo e(round($bh, 1)); ?>" rx="2" fill="var(--accent)"><title><?php echo e($r['date']->format('d M')); ?>: <?php echo e(number_format($r['poured_kg'] / 1000, 2)); ?> t</title></rect><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php $__currentLoopData = [0, intdiv($n, 2), $n - 1]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(isset($rows[$i])): ?><text x="<?php echo e(round($x($i), 1)); ?>" y="<?php echo e($H - 6); ?>" text-anchor="<?php echo e($i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle')); ?>" font-size="10" fill="#64748b"><?php echo e($rows[$i]['date']->format('d M')); ?></text><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </svg>
        <div style="font-size:12px;color:var(--dx-sub);margin-top:6px">Tonnes of liquid metal poured per day.</div>
    </div>
    <div class="mini">
        <div><div class="l">Overall pour yield</div><div class="v mono"><?php echo e($d['yieldTotal'] !== null ? $d['yieldTotal'].'%' : '—'); ?></div></div>
        <div><div class="l">Metal poured</div><div class="v mono"><?php echo e(number_format($tot / 1000, 1)); ?> t</div></div>
        <div><div class="l">Pieces rejected</div><div class="v mono"><?php echo e($pieces > 0 ? number_format(100 * $rej / $pieces, 2).'%' : '—'); ?></div></div>
    </div>
</div>
<script>
document.querySelectorAll('[data-chart]').forEach(function (c) {
    c.querySelectorAll('.tabs button').forEach(function (b) {
        b.addEventListener('click', function () {
            c.querySelectorAll('.tabs button').forEach(function (o) { o.classList.toggle('on', o === b); });
            c.querySelectorAll('[data-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== b.getAttribute('data-t'); });
        });
    });
});
</script>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/dashboard/_chart.blade.php ENDPATH**/ ?>