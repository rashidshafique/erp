<?php $__env->startSection('title', 'Attendance'); ?>
<?php $__env->startSection('breadcrumb', 'Attendance'); ?>
<?php $__env->startSection('content'); ?>
<?php
    $st = config('hr.attendance.day_status');
    $stCls = ['PRESENT' => 'status-completed', 'HALF_DAY' => 'status-on-hold', 'ABSENT' => 'status-cancelled', 'LEAVE' => 'status-in-progress', 'OFF' => '', 'HOLIDAY' => ''];
    $dep = request('department');
?>
<div class="page-heading"><div><h1>Attendance</h1><p><?php echo e($date->format('l, d F Y')); ?></p></div>
<div class="actions"><a class="btn" href="<?php echo e(route('hr.attendance', ['date' => $date->copy()->subDay()->toDateString(), 'department' => $dep])); ?>">&larr; Previous</a>
<a class="btn" href="<?php echo e(route('hr.attendance', ['department' => $dep])); ?>">Today</a>
<a class="btn" href="<?php echo e(route('hr.attendance', ['date' => $date->copy()->addDay()->toDateString(), 'department' => $dep])); ?>">Next &rarr;</a></div></div>
<?php echo $__env->make('hr.attendance._tabs', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if(session('error')): ?><div class="alert alert-error"><?php echo e(session('error')); ?></div><?php endif; ?>
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
    <div class="field"><label for="date">Day</label><input id="date" type="date" name="date" value="<?php echo e($date->toDateString()); ?>"></div>
    <div class="field"><label for="department">Department</label><select id="department" name="department"><option value="">Everyone</option><?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $x): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($x->id); ?>" <?php if($dep === $x->id): echo 'selected'; endif; ?>><?php echo e($x->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <button class="btn btn-primary">Show</button></form>
</div></div>
<?php echo $__env->make('hr._ui', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="hr-cards"><?php $__currentLoopData = $cards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div class="hr-card"><span class="hr-ico"><?php echo \App\Support\NavIcons::get(['present' => 'check-circle', 'late' => 'clock', 'absent' => 'alert', 'leave' => 'calendar-minus', 'overtime' => 'zap', 'open' => 'inbox'][$c['key']], 18); ?></span>
<div><div class="hr-l"><?php echo e($c['label']); ?></div><div class="hr-n"><?php echo e($c['n']); ?><?php if($c['delta'] !== null): ?><span class="hr-d <?php echo e($c['delta'] == 0 ? 'flat' : (($c['delta'] > 0) === $c['good'] ? 'up' : 'down')); ?>"><?php echo e($c['delta'] > 0 ? '+' : ''); ?><?php echo e($c['delta']); ?>% vs prev day</span><?php endif; ?></div></div></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
<?php if($missing > 0): ?><p class="small muted" style="margin:-4px 0 12px"><?php echo e($missing); ?> with no record yet.</p><?php endif; ?>

<form method="POST" action="<?php echo e(route('hr.attendance.save')); ?>"><?php echo csrf_field(); ?><input type="hidden" name="date" value="<?php echo e($date->toDateString()); ?>"><input type="hidden" name="department" value="<?php echo e($dep); ?>">
<div class="panel">
<?php if($people->isEmpty()): ?><div class="empty-state">No one to show for this day.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Employee</th><th>Plan</th><th style="width:110px">In</th><th style="width:110px">Out</th><th>Result</th><th>Worked</th><th>Note</th></tr></thead><tbody>
<?php $__currentLoopData = $people; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php $r = $recs->get($p->id); $pl = $plans[$p->id]; ?>
<tr>
<td><a href="<?php echo e(route('hr.employees.show', $p)); ?>"><strong><?php echo e($p->code); ?></strong></a> <button type="button" class="hr-person" data-person-url="<?php echo e(route('hr.attendance.person', [$p, 'to' => $date->toDateString()])); ?>"><?php echo e($p->full_name); ?></button><div class="muted small"><?php echo e($p->department?->name); ?></div></td>
<td class="small"><?php if($pl['kind'] === 'WORK'): ?><?php echo e($pl['shift']?->label() ?? 'No shift set'); ?><?php elseif($pl['kind'] === 'HOLIDAY'): ?>Holiday: <?php echo e($pl['holiday']); ?><?php elseif($pl['kind'] === 'LEAVE'): ?>On leave: <?php echo e($pl['leave']->type->name); ?><?php else: ?> Weekly off <?php endif; ?></td>
<td><input type="time" name="rows[<?php echo e($p->id); ?>][in]" value="<?php echo e($r?->check_in?->format('H:i')); ?>" style="min-height:30px;padding:3px 6px"></td>
<td><input type="time" name="rows[<?php echo e($p->id); ?>][out]" value="<?php echo e($r?->check_out?->format('H:i')); ?>" style="min-height:30px;padding:3px 6px"></td>
<td><?php if($r): ?><span class="status <?php echo e($stCls[$r->status] ?? ''); ?>"><?php echo e($st[$r->status]); ?></span> <?php echo $__env->make('hr._dayflags', ['flags' => $r->flags], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php if($r->late_minutes): ?><span class="muted small"><?php echo e($r->late_minutes); ?> min late</span><?php endif; ?> <?php else: ?> <span class="muted">—</span> <?php endif; ?></td>
<td class="small"><?php echo e($r ? \App\Models\AttendanceRecord::hm($r->worked_minutes) : '—'); ?><?php if($r && $r->overtime_minutes): ?><div class="muted">+<?php echo e(\App\Models\AttendanceRecord::hm($r->overtime_minutes)); ?> OT</div><?php endif; ?></td>
<td><input name="rows[<?php echo e($p->id); ?>][note]" value="<?php echo e($r?->note); ?>" style="min-height:30px;padding:3px 6px;max-width:200px"></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div>
<div class="panel-body"><button class="btn btn-primary" type="submit">Save the sheet</button></div>
<?php endif; ?>
</div></form>
<?php if($missing > 0 && $date->lte(now())): ?>
<form method="POST" action="<?php echo e(route('hr.attendance.close')); ?>" onsubmit="return confirm('Give everyone without a record an absent, day-off, holiday or leave record for this day?')"><?php echo csrf_field(); ?><input type="hidden" name="date" value="<?php echo e($date->toDateString()); ?>">
<p class="muted">When the day is over: <button class="btn" type="submit">Close the day (<?php echo e($missing); ?> without a record)</button> &mdash; people who did not come in are marked absent and show up under Exceptions.</p></form><?php endif; ?>
<div class="hr-veil" id="hr-veil"></div>
<aside class="hr-drawer" id="hr-drawer" aria-label="Person attendance" aria-hidden="true"><div class="hr-drawer-body" id="hr-body"></div></aside>
<script>
(function () {
    var veil = document.getElementById('hr-veil'), dr = document.getElementById('hr-drawer'), body = document.getElementById('hr-body');
    var btns = [].slice.call(document.querySelectorAll('[data-person-url]')), cur = -1, pane = 'a';
    function open(i, days) {
        if (i < 0 || i >= btns.length) return; cur = i;
        var u = new URL(btns[i].getAttribute('data-person-url'), location.href); if (days) u.searchParams.set('days', days);
        dr.classList.add('on'); veil.classList.add('on'); dr.setAttribute('aria-hidden', 'false');
        body.innerHTML = '<p class="muted" style="padding:24px">Loading…</p>';
        fetch(u, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw 0; return r.text(); }).then(function (h) { body.innerHTML = h; wire(); }).catch(function () { body.innerHTML = '<p class="alert alert-error" style="margin:20px">Could not load this person.</p>'; });
    }
    function close() { dr.classList.remove('on'); veil.classList.remove('on'); dr.setAttribute('aria-hidden', 'true'); }
    function show(p) { pane = p; body.querySelectorAll('.hr-tabs button').forEach(function (b) { b.classList.toggle('on', b.dataset.pane === p); }); body.querySelectorAll('.hr-pane').forEach(function (x) { x.classList.toggle('on', x.dataset.p === p); }); }
    function wire() {
        show(pane);
        body.querySelectorAll('.hr-tabs button').forEach(function (b) { b.onclick = function () { show(b.dataset.pane); }; });
        body.querySelectorAll('[data-step]').forEach(function (b) { var n = cur + parseInt(b.dataset.step, 10); b.disabled = n < 0 || n >= btns.length; b.onclick = function () { open(n, body.querySelector('[data-days]') && body.querySelector('[data-days]').value); }; });
        var pos = body.querySelector('[data-pos]'); if (pos) pos.textContent = (cur + 1) + ' of ' + btns.length;
        var sel = body.querySelector('[data-days]'); if (sel) sel.onchange = function () { open(cur, sel.value); };
        body.querySelectorAll('[data-close-drawer]').forEach(function (b) { b.onclick = close; });
    }
    btns.forEach(function (b, i) { b.addEventListener('click', function () { open(i); }); });
    veil.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/attendance/daily.blade.php ENDPATH**/ ?>