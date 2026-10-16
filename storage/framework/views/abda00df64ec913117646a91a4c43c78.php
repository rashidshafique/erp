<?php $__env->startSection('title', 'Match columns'); ?>
<?php $__env->startSection('breadcrumb'); ?><a href="<?php echo e(route('imports.index')); ?>">Import data</a> / <?php echo e($def['label']); ?> / Match columns <?php $__env->stopSection(); ?>
<?php $__env->startSection('content'); ?>
<style>.mp td { vertical-align: top; } .mp select, .mp input { max-width: 100%; } .req { color: var(--danger); font-weight: 700; } .auto { font-size: 11px; color: var(--accent); } .dup { background: #fff3f3; } .sample td, .sample th { white-space: nowrap; font-size: 12.5px; } input[type=checkbox] { accent-color: var(--accent); }</style>
<?php
    $letter = function ($i) { $s = ''; $i++; while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m).$s; $i = intdiv($i - $m - 1, 26); } return $s; };
    $first = $sample[0][1] ?? [];
    $sampleBy = fn ($i) => collect($sample)->map(fn ($r) => $r[1][$i] ?? '')->filter(fn ($v) => $v !== '')->first() ?? '';
?>
<div class="page-heading"><div><h1>Match columns to fields</h1><p><?php echo e($b->source_label); ?> · <?php echo e($rowCount); ?> data rows. Choose which column feeds each field; anything you leave out is simply not imported.</p></div>
<div class="actions"><form method="POST" action="<?php echo e(route('imports.destroy', $b)); ?>" onsubmit="return confirm('Discard this import?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-danger" type="submit">Discard</button></form></div></div>
<?php if($errors->any()): ?><div class="alert alert-error"><?php echo e($errors->first()); ?></div><?php endif; ?>

<div class="panel"><div class="panel-body">
<form method="GET" action="<?php echo e(route('imports.map', $b)); ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <?php if(count($sheets) > 1): ?><div class="field"><label>Sheet</label><select name="sheet" onchange="this.form.submit()"><?php $__currentLoopData = $sheets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $nm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($i); ?>" <?php if($b->sheet_index === $i): echo 'selected'; endif; ?>><?php echo e($nm); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div><?php endif; ?>
    <div class="field"><label>Headings are on row</label><input type="number" min="1" name="header" value="<?php echo e($b->header_row); ?>" style="width:90px"></div>
    <button class="btn" type="submit">Reload columns</button>
    <?php if($templates->isNotEmpty()): ?><div class="field"><label>Use a saved mapping</label><select name="template" onchange="this.form.submit()"><option value="">— suggest from the headings —</option><?php $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($t->id); ?>" <?php if($usedTemplate?->id === $t->id): echo 'selected'; endif; ?>><?php echo e($t->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div><?php endif; ?>
</form></div></div>

<form method="POST" action="<?php echo e(route('imports.map.save', $b)); ?>"><?php echo csrf_field(); ?>
<input type="hidden" name="sheet_index" value="<?php echo e($b->sheet_index); ?>"><input type="hidden" name="header_row" value="<?php echo e($b->header_row); ?>">
<div class="panel"><div class="panel-header"><h2>Fields</h2><span class="muted small"><?php echo e(count($headers)); ?> columns found</span></div>
<div class="table-wrap"><table class="mp"><thead><tr><th>Field</th><th>Take it from column</th><th>Example from your data</th><th>Fixed value</th></tr></thead><tbody>
<?php $__currentLoopData = $def['fields']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php $m = $mapping[$k]; ?>
<tr data-field="<?php echo e($k); ?>"><td><strong><?php echo e($f['label']); ?></strong><?php if(! empty($f['required'])): ?><span class="req" title="Needed for new records"> *</span><?php endif; ?><div class="muted small"><?php echo e($f['help'] ?? ''); ?></div></td>
<td><select name="mapping[<?php echo e($k); ?>][col]" class="colsel"><option value="">— not imported —</option><?php $__currentLoopData = $headers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($i); ?>" data-ex="<?php echo e($sampleBy($i)); ?>" <?php if(($m['col'] ?? null) === $i): echo 'selected'; endif; ?>><?php echo e($letter($i)); ?> · <?php echo e($h !== '' ? $h : '(no heading)'); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><?php if($m['auto']): ?><div class="auto">matched by heading</div><?php endif; ?></td>
<td class="small ex"><?php echo e($m['col'] !== null ? $sampleBy($m['col']) : ''); ?></td>
<td><?php if($f['type'] === 'enum'): ?><select name="mapping[<?php echo e($k); ?>][default]"><option value="">—</option><?php $__currentLoopData = $f['enum']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ev): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($ev); ?>" <?php if(($m['default'] ?? null) === $ev): echo 'selected'; endif; ?>><?php echo e($ev); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
<?php elseif($f['type'] === 'bool'): ?><select name="mapping[<?php echo e($k); ?>][default]"><option value="">—</option><option value="1" <?php if(($m['default'] ?? null) === '1'): echo 'selected'; endif; ?>>Yes</option><option value="0" <?php if(($m['default'] ?? null) === '0'): echo 'selected'; endif; ?>>No</option></select>
<?php else: ?><input name="mapping[<?php echo e($k); ?>][default]" value="<?php echo e($m['default'] ?? ''); ?>" placeholder="<?php echo e(isset($f['default']) ? 'new records: '.$f['default'] : ''); ?>"><?php endif; ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div>
<div class="panel-body muted small">A fixed value is used when the cell is blank or no column is chosen. For new records, blank fields get the default shown in grey. Blank cells never erase existing data.</div></div>

<div class="panel"><div class="panel-header"><h2>How to import</h2></div><div class="panel-body"><div class="form-grid">
    <div class="field"><label>Existing records</label><select name="mode"><option value="UPSERT" <?php if($opt['mode'] === 'UPSERT'): echo 'selected'; endif; ?>>Add new and update existing</option><option value="CREATE" <?php if($opt['mode'] === 'CREATE'): echo 'selected'; endif; ?>>Only add new (skip ones that exist)</option><option value="UPDATE" <?php if($opt['mode'] === 'UPDATE'): echo 'selected'; endif; ?>>Only update existing (skip new)</option></select></div>
    <div class="field"><label>A row is the same record when its … matches</label><select name="match_by"><?php $__currentLoopData = $def['match']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mk => $ml): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($mk); ?>" <?php if($opt['match_by'] === $mk): echo 'selected'; endif; ?>><?php echo e($ml); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><?php if(count($def['match']) > 1): ?><div class="help">Code is safest. Name is allowed when your sheet has no codes.</div><?php endif; ?></div>
    <div class="field"><label>Dates are written</label><select name="date_order"><option value="DMY" <?php if($opt['date_order'] === 'DMY'): echo 'selected'; endif; ?>>Day first (31/01/2024)</option><option value="MDY" <?php if($opt['date_order'] === 'MDY'): echo 'selected'; endif; ?>>Month first (01/31/2024)</option></select></div>
    <div class="field"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="decimal_comma" value="1" <?php if(! empty($opt['decimal_comma'])): echo 'checked'; endif; ?>> Numbers use a decimal comma (1.234,5)</label>
        <label style="display:flex;gap:8px;align-items:center;margin-top:6px"><input type="checkbox" name="stop_on_error" value="1" <?php if(! empty($opt['stop_on_error'])): echo 'checked'; endif; ?>> Import nothing if any row has an error</label></div>
    <div class="field field-full"><label>Save this mapping as (optional)</label><input name="save_as" placeholder="e.g. Pattern register from stores" value="<?php echo e(old('save_as')); ?>"><div class="help">Reuse it next time for a file with the same headings.</div></div>
</div></div></div>

<div class="panel"><div class="panel-header"><h2>First rows of your data</h2></div><div class="table-wrap"><table class="sample"><thead><tr><th>Row</th><?php $__currentLoopData = $headers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><th><?php echo e($letter($i)); ?> · <?php echo e($h); ?></th><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tr></thead><tbody>
<?php $__currentLoopData = $sample; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$n, $cells]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td class="muted"><?php echo e($n); ?></td><?php $__currentLoopData = $headers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><td><?php echo e(\Illuminate\Support\Str::limit($cells[$i] ?? '', 40)); ?></td><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</tbody></table></div></div>
<p><button class="btn btn-primary" type="submit">Check the data (nothing is saved yet)</button> <a class="btn" href="<?php echo e(route('imports.index')); ?>">Back</a></p>
</form>
<script>
(function () {
    var rows = document.querySelectorAll('.mp tbody tr');
    function refresh() {
        var seen = {};
        rows.forEach(function (tr) { var s = tr.querySelector('.colsel'); var o = s.options[s.selectedIndex]; tr.querySelector('.ex').textContent = o && o.value !== '' ? (o.getAttribute('data-ex') || '') : ''; if (s.value !== '') seen[s.value] = (seen[s.value] || 0) + 1; });
        rows.forEach(function (tr) { var s = tr.querySelector('.colsel'); tr.classList.toggle('dup', s.value !== '' && seen[s.value] > 1); });
    }
    rows.forEach(function (tr) { tr.querySelector('.colsel').addEventListener('change', refresh); }); refresh();
})();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/imports/map.blade.php ENDPATH**/ ?>