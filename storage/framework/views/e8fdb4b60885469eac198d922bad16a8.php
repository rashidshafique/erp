<?php $__env->startSection('title', $list->list_number); ?>
<?php $__env->startSection('content'); ?>
<?php
    $editable = in_array($list->status, ['DRAFT','ISSUED']);
    $groups = $list->items->groupBy('moulding_station_id');
?>
<div class="page-heading"><div><h1><?php echo e($list->list_number); ?></h1><p><?php echo e($list->list_date->format('l d M Y')); ?> · <?php echo e($list->shift); ?> shift · <span class="status"><?php echo e($list->status); ?></span><?php if($list->issued_by): ?> · issued by <?php echo e($list->issued_by); ?><?php endif; ?></p></div>
<div class="actions"><a class="btn" href="<?php echo e(route('work-lists.print', $list)); ?>" target="_blank">Print for teams</a></div></div>
<?php if(session("success")): ?><div class="alert alert-success"><?php echo e(session("success")); ?></div><?php endif; ?>
<?php if($errors->any()): ?><div class="alert alert-error"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($e); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>

<?php $__empty_1 = true; $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stationId => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<?php
    $st = $items->first()->station;
    $cap = $st->capacity();
    $now = $running[$stationId] ?? 0;
    $planned = $items->where('status', 'PLANNED')->count();
    $teams_here = $items->map(fn ($i) => $i->team ? $i->team->code.' — '.$i->team->lead_name : null)->filter()->unique()->implode(', ') ?: 'not set for this line';
    $customers = $items->map(fn ($i) => $i->productionOrder?->customerLabel() ?? 'unassigned')->unique()->implode(', ');
?>
<div class="panel">
    <div class="panel-header" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <h2 style="margin:0"><?php echo e($st->code); ?> <span class="muted small" style="font-weight:500"><?php echo e($st->name); ?></span></h2>
        <span class="status"><?php echo e($st->isAutomated() ? 'One pattern at a time' : 'Up to '.$cap.' pattern'.($cap > 1 ? 's' : '').' at once'); ?></span>
        <span class="muted small"><?php echo e($now); ?> of <?php echo e($cap); ?> running · Team <?php echo e($teams_here); ?> · <?php echo e($items->pluck('sand_system')->unique()->implode(', ')); ?> · Customers: <?php echo e($customers); ?></span>
        <?php if($editable && $planned > 0): ?>
            <details class="row-edit"><summary>Change sand</summary>
                <form method="POST" action="<?php echo e(route('work-lists.stations.change', [$list, $st])); ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:6px"><?php echo csrf_field(); ?>
                    <select name="sand_system"><?php $__currentLoopData = $sands; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sd): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option <?php if($items->first()->sand_system === $sd): echo 'selected'; endif; ?>><?php echo e($sd); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
                    <label style="font-weight:400;display:flex;gap:5px;align-items:center"><input type="checkbox" name="accept_unsuited" value="1"> Accept where a plate is not suited</label>
                    <button class="btn btn-small">Apply to planned rows</button></form></details>
        <?php endif; ?>
        <?php if($list->status === 'ISSUED' && $planned > 0): ?>
            <form method="POST" action="<?php echo e(route('work-lists.stations.start', [$list, $st])); ?>" style="margin-left:auto"><?php echo csrf_field(); ?>
                <button class="btn btn-primary" <?php if($now >= $cap): echo 'disabled'; endif; ?>><?php echo e($st->isAutomated() ? 'Start next' : 'Start all planned'); ?></button></form>
        <?php endif; ?>
    </div>
    <div class="table-wrap"><table><thead><tr><th>#</th><th>Customer</th><th>Order / product</th><th>Pattern</th><th>Sand</th><th>Moulds</th><th>Result</th><th>Status</th><th></th></tr></thead><tbody>
    <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr>
        <td><?php echo e($i->sequence); ?></td>
        <td><?php echo e($i->productionOrder?->customerLabel() ?? '—'); ?></td>
        <td><?php if($i->productionOrder): ?><?php echo e($i->productionOrder->production_order_number); ?><?php if($i->order_link === 'AUTO'): ?> <span class="status" title="Found by the system: the open order that needs this pattern">auto-linked</span><?php elseif($i->order_link === 'STOCK'): ?> <span class="status">stock order</span><?php endif; ?>
            <div class="muted small"><?php echo e($i->productionOrder->product?->code); ?> · <?php echo e($i->productionOrder->order_type); ?></div>
            <?php else: ?><span class="muted">No order yet</span><div class="muted small"><?php if($i->status === 'PLANNED'): ?>links to an open order, or a stock order is raised when the run starts <?php endif; ?></div>
            <?php if($editable && $i->status === 'PLANNED'): ?><form method="POST" action="<?php echo e(route('work-lists.items.link', [$list, $i])); ?>"><?php echo csrf_field(); ?><button class="btn btn-small">Find open order</button></form><?php endif; ?> <?php endif; ?></td>
        <td><?php echo e($i->patternRevision->pattern->code); ?> rev <?php echo e($i->patternRevision->revision); ?><div class="muted small"><?php echo e($i->patternRevision->pattern->pattern_type); ?></div></td>
        <td><?php echo e($i->sand_system); ?><?php if($i->sand_override): ?><div><span class="status" style="color:var(--danger,#b42318)" title="This plate is not listed as suited to this sand system; someone chose to run it anyway">sand mismatch accepted</span></div><?php endif; ?></td>
        <td><?php echo e($i->planned_moulds); ?><?php if(($needed[$i->id] ?? null) !== null): ?><div class="muted small">order still needs ≈<?php echo e($needed[$i->id]); ?></div><?php endif; ?></td>
        <td><?php if($i->run): ?><a href="<?php echo e(route('moulding-runs.show', $i->run)); ?>"><?php echo e($i->run->accepted_mould_qty); ?> ok / <?php echo e($i->run->damaged_mould_qty); ?> dmg</a><?php else: ?>—<?php endif; ?></td>
        <td><?php echo e($i->status); ?></td>
        <td><?php if($list->status === 'ISSUED' && $i->status === 'PLANNED'): ?><form method="POST" action="<?php echo e(route('work-lists.items.start', [$list, $i])); ?>"><?php echo csrf_field(); ?><button class="btn btn-primary">Start run</button></form>
        <?php elseif($editable && $i->status === 'PLANNED'): ?><form method="POST" action="<?php echo e(route('work-lists.items.remove', [$list, $i])); ?>"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-danger">Remove</button></form><?php endif; ?></td></tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<div class="panel"><div class="panel-body muted">Nothing on the list yet. Pick a station below and add the patterns it will mould.</div></div>
<?php endif; ?>

<?php if($editable): ?>
<div class="panel"><div class="panel-header"><h2>Add work for a moulding line</h2></div><div class="panel-body">
<p class="muted small" style="margin-top:0">Pick the moulding line: its team is the line's own team, and its sand system fills in by itself and can be changed. Then list the pattern plates and how many moulds of each. An automated line takes one pattern at a time, so its rows run in order; a manual station can take several plates at once.</p>
<form method="POST" action="<?php echo e(route('work-lists.rows.add', $list)); ?>" id="add-work"><?php echo csrf_field(); ?>
<div class="form-grid">
<label>Moulding line<select name="moulding_station_id" id="aw-station" required><?php $__currentLoopData = $stations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s->id); ?>" data-team="<?php echo e($teamOf[$s->id] ?? ''); ?>" data-sand="<?php echo e($sandFor[$s->id] ?? ''); ?>" data-note="<?php echo e($s->isAutomated() ? 'One pattern at a time: the rows below run in order.' : 'Takes up to '.$s->capacity().' pattern'.($s->capacity() > 1 ? 's' : '').' at once.'); ?>" <?php if(old('moulding_station_id')===$s->id): echo 'selected'; endif; ?>><?php echo e($s->code); ?> — <?php echo e($s->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
<label>Sand system<select name="sand_system" id="aw-sand"><?php $__currentLoopData = $sands; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sd): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option <?php if(old('sand_system')===$sd): echo 'selected'; endif; ?>><?php echo e($sd); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
</div>
<div class="muted small" id="aw-linenote" style="margin:4px 0 10px"></div>
<div class="table-wrap"><table style="width:auto"><thead><tr><th>Pattern plate</th><th>Production order <span class="muted small" style="font-weight:400">(optional)</span></th><th>Moulds</th><th></th></tr></thead>
<tbody id="aw-rows"><?php $__currentLoopData = old('rows', [[]]); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo $__env->make('work-lists._row', ['i' => $k, 'row' => $row], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tbody></table></div>
<div style="margin:10px 0"><button type="button" class="btn" id="aw-add">+ Add row</button></div>
<label>Notes for the team <span class="muted small">(optional)</span><input name="instructions" value="<?php echo e(old('instructions')); ?>" style="max-width:420px"></label>
<div style="margin-top:12px"><button class="btn btn-primary">Add to list</button></div>
</form>
<template id="aw-tpl"><table><tbody><?php echo $__env->make('work-lists._row', ['i' => '__i__', 'row' => []], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></tbody></table></template>
</div></div>
<script>
(function () {
    var form = document.getElementById('add-work'); if (!form) return;
    var st = document.getElementById('aw-station'), sd = document.getElementById('aw-sand'),
        body = document.getElementById('aw-rows'), tpl = document.getElementById('aw-tpl'), auto = <?php echo json_encode($autoOrder, 15, 512) ?>, psand = <?php echo json_encode($patternSand, 15, 512) ?>,
        next = body.querySelectorAll('.aw-row').length + 1000;
    function line() {
        var o = st.selectedOptions[0]; if (!o) return;
        if (o.dataset.sand) sd.value = o.dataset.sand;
        document.getElementById('aw-linenote').textContent = (o.dataset.team ? 'Team: ' + o.dataset.team + '. ' : 'No team is set for this line (set it in Teams & stations). ') + (o.dataset.note || '');
        sandAll();
    }
    function row(r) {
        var od = r.querySelector('.aw-order'), pt = r.querySelector('.aw-pattern'), pv = r.querySelector('.aw-preview'), prod = od.selectedOptions[0] ? od.selectedOptions[0].dataset.product : '', first = null;
        // with an order chosen, only offer the plates that have a cavity for its product (plates with no cavity map stay available)
        [].forEach.call(pt.options, function (o) {
            var list = (o.dataset.products || '').split(',').filter(Boolean), ok = !prod || !list.length || list.indexOf(prod) > -1;
            o.hidden = !ok; o.disabled = !ok; if (ok && !first) first = o;
        });
        if (pt.selectedOptions[0] && pt.selectedOptions[0].disabled && first) pt.value = first.value;
        pv.textContent = od.value ? '' : (auto[pt.value] ? 'Will link to ' + auto[pt.value] : 'No open order: a stock order is raised when the run starts.');
    }
    // A pattern is made for one sand system; on another it usually needs a different taper allowance.
    function suits(id) { var p = psand[id], s = sd.value; return !p || !p.for || p.for === s || (p.also || []).indexOf(s) > -1; }
    function sandCheck(r) {
        var pt = r.querySelector('.aw-pattern'), box = r.querySelector('.aw-sandwarn'), p = psand[pt.value];
        if (suits(pt.value)) { box.hidden = true; r.querySelector('.aw-any').checked = false; return; }
        r.querySelector('.aw-sandtext').textContent = p.code + ' was made for ' + p.for + ' and is not listed for ' + sd.value + ': it usually needs a different taper allowance.' + (p.note ? ' ' + p.note : '') + ' ';
        box.hidden = false;
    }
    function sandAll() {
        [].forEach.call(body.querySelectorAll('.aw-row'), function (r) {
            [].forEach.call(r.querySelector('.aw-pattern').options, function (o) { // mark plates that do not suit the line's sand with a warning sign
                var base = o.dataset.label || (o.dataset.label = o.textContent);
                o.textContent = suits(o.value) ? base : '⚠ ' + base;
            });
            sandCheck(r);
        });
    }
    function wire(r) {
        r.querySelector('.aw-order').addEventListener('change', function () { row(r); });
        r.querySelector('.aw-pattern').addEventListener('change', function () { row(r); sandCheck(r); });
        r.querySelector('.aw-del').addEventListener('click', function () {
            if (body.querySelectorAll('.aw-row').length > 1) r.remove();
        });
        row(r); sandAll();
    }
    function fix() { // the last remaining row cannot be deleted
        var rows = body.querySelectorAll('.aw-row');
        [].forEach.call(rows, function (r) { r.querySelector('.aw-del').disabled = rows.length < 2; });
    }
    document.getElementById('aw-add').addEventListener('click', function () {
        var html = tpl.innerHTML.replace(/__i__/g, next++), t = document.createElement('table');
        t.innerHTML = html; var r = t.querySelector('.aw-row'); body.appendChild(r); wire(r); fix();
        var last = r.querySelector('.aw-pattern'); if (last) last.focus();
    });
    body.addEventListener('click', function (e) { if (e.target.closest('.aw-del')) setTimeout(fix, 0); });
    [].forEach.call(body.querySelectorAll('.aw-row'), wire); fix();
    st.addEventListener('change', line);
    sd.addEventListener('change', sandAll);
    <?php if(! old('sand_system')): ?> line(); <?php else: ?>(); <?php endif; ?>
})();
</script>
<?php if($list->status === 'DRAFT'): ?><div class="panel"><div class="panel-header"><h2>Issue</h2></div><div class="panel-body"><form method="POST" action="<?php echo e(route('work-lists.issue', $list)); ?>" style="display:flex;gap:10px"><?php echo csrf_field(); ?><input name="issued_by" placeholder="Issued by" required><button class="btn btn-primary">Issue to teams</button></form></div></div><?php endif; ?>
<?php if (! ($list->items->contains(fn($i) => $i->moulding_run_id))): ?><form method="POST" action="<?php echo e(route('work-lists.cancel', $list)); ?>"><?php echo csrf_field(); ?><button class="btn btn-danger">Cancel list</button></form><?php endif; ?>
<?php endif; ?>
<?php echo $__env->make('audit._history', ['type' => 'MouldingWorkList', 'id' => $list->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/work-lists/show.blade.php ENDPATH**/ ?>