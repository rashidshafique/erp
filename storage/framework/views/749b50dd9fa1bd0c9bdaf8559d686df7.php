<?php $__env->startSection('title', $e->code); ?>
<?php $__env->startSection('breadcrumb'); ?><a href="<?php echo e(route('enquiries.index')); ?>">Enquiries</a> / <?php echo e($e->code); ?> <?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<style>.ca-wrap td, .ca-wrap th { white-space: normal; vertical-align: top; } input[type=checkbox] { accent-color: var(--accent); } .item-card { margin-bottom: 12px; } .item-card details { margin-top: 10px; }</style>
<?php
    $cls = ['NEW' => 'status-on-hold', 'FEASIBILITY' => 'status-on-hold', 'QUOTED' => 'status-in-progress', 'WON' => 'status-completed', 'LOST' => 'status-cancelled', 'CANCELLED' => 'status-cancelled'];
    $qcls = ['DRAFT' => 'status-on-hold', 'ISSUED' => 'status-in-progress', 'ACCEPTED' => 'status-completed', 'REJECTED' => 'status-cancelled', 'EXPIRED' => 'status-cancelled', 'SUPERSEDED' => 'status-cancelled'];
    $fcls = ['PENDING' => 'status-on-hold', 'OK' => 'status-completed', 'CONDITIONAL' => 'status-in-progress', 'NOT_FEASIBLE' => 'status-cancelled'];
    $open = $e->isOpen();
?>
<div class="page-heading">
    <div><h1><?php echo e($e->title); ?></h1><p><?php echo e($e->code); ?> · <?php echo e($e->customer?->name); ?> · <?php echo e(\App\Models\Enquiry::SOURCES[$e->source_type]); ?> · <span class="status <?php echo e($cls[$e->status] ?? ''); ?>"><?php echo e($e->status); ?></span></p></div>
    <div class="actions"><a class="btn" href="<?php echo e(route('enquiries.index')); ?>">All enquiries</a></div>
</div>
<?php if(session('error')): ?><div class="alert alert-error"><?php echo e(session('error')); ?></div><?php endif; ?>

<div class="tab-marker" data-tab-start="Summary"></div>
<?php $guide = \App\Services\Quotation\EnquiryGuide::steps($e); ?>
<div class="panel"><div class="panel-header"><h2>What happens next</h2><span class="muted small"><?php echo e(config('enquiry_scenarios.'.$e->source_type.'.label')); ?></span></div><div class="panel-body">
<ul style="list-style:none;margin:0;padding:0">
<?php $__currentLoopData = $guide; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li style="padding:3px 0"><?php if($g['done'] === true): ?><span style="color:var(--accent)">&#10003;</span> <?php echo e($g['label']); ?><?php elseif($g['done'] === false): ?><span class="muted">&#9675;</span> <?php echo e($g['label']); ?><?php else: ?><span class="muted">&rarr;</span> <span class="muted"><?php echo e($g['label']); ?></span><?php endif; ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</ul></div></div>
<div class="panel"><div class="panel-header"><h2>Request</h2></div><div class="panel-body">
<?php if($open): ?>
<form method="POST" action="<?php echo e(route('enquiries.update', $e)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
<div class="form-grid">
    <div class="field"><label>Title</label><input name="title" value="<?php echo e(old('title', $e->title)); ?>" required></div>
    <div class="field"><label>Kind</label><select name="source_type"><?php $__currentLoopData = \App\Models\Enquiry::SOURCES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(old('source_type', $e->source_type) === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <div class="field"><label>Quote needed by</label><input type="date" name="due_on" value="<?php echo e(old('due_on', $e->due_on?->toDateString())); ?>"></div>
    <div class="field"><label>Their reference</label><input name="customer_reference" value="<?php echo e(old('customer_reference', $e->customer_reference)); ?>"></div>
    <div class="field"><label>Contact</label><input name="contact_name" value="<?php echo e(old('contact_name', $e->contact_name)); ?>"></div>
    <div class="field field-full"><label>What they said</label><textarea name="summary" rows="3"><?php echo e(old('summary', $e->summary)); ?></textarea></div>
</div>
<p><button class="btn btn-primary" type="submit">Save</button></p>
</form>
<?php else: ?>
<p><?php echo e($e->summary ?: 'No summary.'); ?></p>
<p class="muted">Closed <?php echo e($e->closed_at?->format('d M Y')); ?><?php if($e->lost_reason): ?> — <?php echo e($e->lost_reason); ?><?php endif; ?></p>
<?php endif; ?>
</div></div>
<?php if($open): ?>
<div class="panel"><div class="panel-header"><h2>Close this enquiry</h2></div><div class="panel-body">
<form method="POST" action="<?php echo e(route('enquiries.lost', $e)); ?>" onsubmit="return confirm('Close this enquiry? Open quotations will be marked rejected.')"><?php echo csrf_field(); ?>
<div class="form-grid"><div class="field"><label>Outcome</label><select name="outcome"><option value="LOST">Lost to another supplier or price</option><option value="CANCELLED">Customer cancelled</option></select></div>
<div class="field"><label>Reason</label><input name="lost_reason" required placeholder="e.g. 12% above competitor"></div></div>
<p><button class="btn btn-danger" type="submit">Close enquiry</button></p></form>
</div></div>
<?php endif; ?>

<div class="tab-marker" data-tab-start="Parts & feasibility"></div>
<div class="ca-wrap">
<?php $__empty_1 = true; $__currentLoopData = $e->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $it): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<?php $a = $it->analysis; $severe = $a && collect($a->result['hotspots'] ?? [])->contains('role', 'ISOLATED_SEVERE'); ?>
<div class="panel item-card"><div class="panel-header"><h2><?php echo e($it->line_no); ?>. <?php echo e($it->description); ?></h2><span class="status <?php echo e($fcls[$it->feasibility_status] ?? ''); ?>"><?php echo e(\App\Models\EnquiryItem::FEASIBILITY[$it->feasibility_status]); ?></span></div>
<div class="panel-body">
    <p class="small muted">
        <?php if($it->product): ?>Product <a href="<?php echo e(route('products.show', $it->product)); ?>"><?php echo e($it->product->code); ?></a> · <?php endif; ?>
        <?php if($it->drawing_ref): ?>Drawing <?php echo e($it->drawing_ref); ?><?php echo e($it->drawing_rev ? ' rev '.$it->drawing_rev : ''); ?> · <?php endif; ?>
        <?php echo e($it->material_text ?: 'Material not stated'); ?> · <?php echo e($it->weight_kg ? number_format($it->weight_kg, 2).' kg' : 'weight unknown'); ?> ·
        first batch <?php echo e($it->first_batch_qty ?? '—'); ?>, yearly <?php echo e($it->annual_qty ?? '—'); ?><?php if($it->target_price): ?> · target <?php echo e(number_format($it->target_price, 2)); ?><?php endif; ?>
    </p>
    <p>
        <?php if($a): ?>
            <strong>Analysis <a href="<?php echo e(route('casting-analyses.show', $a)); ?>"><?php echo e($a->code); ?></a></strong>: score <strong><?php echo e($a->score); ?></strong> (<?php echo e($a->score_letter); ?>)<?php echo $severe ? ' · <span class="status status-cancelled">cut-off hotspot</span>' : ''; ?>

        <?php else: ?>
            <span class="muted">No casting analysis yet.</span>
        <?php endif; ?>
        <?php if($open): ?>
            · <a href="<?php echo e(route('casting-analyses.create', ['enquiry_item' => $it->id])); ?>">Analyse a model</a>
        <?php endif; ?>
    </p>
    <?php if($open): ?>
    <form method="POST" action="<?php echo e(route('enquiries.items.analysis', [$e, $it])); ?>" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap"><?php echo csrf_field(); ?>
        <select name="casting_analysis_id" style="max-width:320px"><option value="">— no analysis —</option><?php $__currentLoopData = $analyses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $an): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($an->id); ?>" <?php if($it->casting_analysis_id === $an->id): echo 'selected'; endif; ?>><?php echo e($an->code); ?> — <?php echo e($an->name); ?> (score <?php echo e($an->score); ?>)</option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
        <button class="btn" type="submit">Link existing analysis</button>
    </form>
    <hr style="border:0;border-top:1px solid var(--border);margin:12px 0">
    <form method="POST" action="<?php echo e(route('enquiries.items.feasibility', [$e, $it])); ?>"><?php echo csrf_field(); ?>
        <div class="form-grid">
            <div class="field"><label>Feasibility decision</label><select name="feasibility_status"><?php $__currentLoopData = ['OK', 'CONDITIONAL', 'NOT_FEASIBLE']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s); ?>" <?php if($it->feasibility_status === $s): echo 'selected'; endif; ?>><?php echo e(\App\Models\EnquiryItem::FEASIBILITY[$s]); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
            <div class="field field-full"><label>Conditions or reason</label><textarea name="feasibility_notes" rows="2" placeholder="Required unless plainly feasible. e.g. chill the boss, customer to relax wall to 6 mm"><?php echo e($it->feasibility_notes); ?></textarea></div>
        </div>
        <p><button class="btn btn-primary" type="submit">Record decision</button></p>
    </form>
    <details><summary>Edit part details</summary>
        <form method="POST" action="<?php echo e(route('enquiries.items.update', [$e, $it])); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
            <?php echo $__env->make('enquiries._item_fields', ['item' => $it, 'products' => $products], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <p><button class="btn btn-primary" type="submit">Save part</button></p>
        </form>
        <form method="POST" action="<?php echo e(route('enquiries.items.destroy', [$e, $it])); ?>" onsubmit="return confirm('Remove this part?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-danger" type="submit">Remove part</button></form>
    </details>
    <?php elseif($it->feasibility_notes): ?>
        <p><?php echo e($it->feasibility_notes); ?></p>
    <?php endif; ?>
</div></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<div class="panel"><div class="empty-state">No parts yet. Add what the customer wants made.</div></div>
<?php endif; ?>
<?php if($open): ?>
<div class="panel"><div class="panel-header"><h2>Add a part</h2></div><div class="panel-body"><details <?php if($e->items->isEmpty()): ?> open <?php endif; ?>><summary><?php echo e($e->items->isEmpty() ? 'Part details' : 'Add another part'); ?></summary>
    <form method="POST" action="<?php echo e(route('enquiries.items.store', $e)); ?>"><?php echo csrf_field(); ?>
        <?php echo $__env->make('enquiries._item_fields', ['item' => null, 'products' => $products], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <p><button class="btn btn-primary" type="submit">Add part</button></p>
    </form></details>
</div></div>
<?php endif; ?>
</div>

<div class="tab-marker" data-tab-start="Quotation"></div>
<div class="panel"><div class="panel-header"><h2>Quotations</h2>
    <?php if($open): ?><form method="POST" action="<?php echo e(route('enquiries.quotations.store', $e)); ?>"><?php echo csrf_field(); ?><button class="btn btn-primary" type="submit"><?php echo e($e->quotations->firstWhere('status', 'DRAFT') ? 'Open the draft' : ($e->quotations->isEmpty() ? 'Create quotation' : 'New version')); ?></button></form><?php endif; ?></div>
<?php if($e->quotations->isEmpty()): ?>
    <div class="empty-state">No quotation yet. Assess each part first, then create the quotation to price them.</div>
<?php else: ?>
    <div class="table-wrap"><table><thead><tr><th>Quotation</th><th>Version</th><th>Status</th><th>Lowest margin</th><th>Valid until</th><th></th></tr></thead><tbody>
    <?php $__currentLoopData = $e->quotations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $q): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr><td><strong><?php echo e($q->code); ?></strong></td><td><?php echo e($q->version); ?></td><td><span class="status <?php echo e($qcls[$q->status] ?? ''); ?>"><?php echo e($q->status); ?></span></td>
        <td><?php echo e($q->lines->isEmpty() ? '—' : number_format($q->lines->min('effective_margin_pct'), 1).'%'); ?></td><td class="small"><?php echo e($q->valid_until?->format('d M Y') ?? '—'); ?></td><td><a href="<?php echo e(route('quotations.show', $q)); ?>">Open →</a></td></tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody></table></div>
<?php endif; ?>
</div>

<div class="tab-marker" data-tab-start="Files & history"></div>
<?php echo $__env->make('attachments._panel', ['type' => 'enquiry', 'id' => $e->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('audit._history', ['type' => 'Enquiry', 'id' => $e->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/enquiries/show.blade.php ENDPATH**/ ?>