<?php
    $atts = \App\Models\Attachment::where('attachable_type', $type)->where('attachable_id', (string) $id)->whereNull('removed_at')->orderByDesc('created_at')->get();
    $cats = \App\Support\AttachableEntities::CATEGORIES;
    $fmt = fn ($b) => $b >= 1048576 ? number_format($b / 1048576, 1).' MB' : number_format(max(1, $b / 1024), 0).' KB';
?>
<div class="panel">
    <div class="panel-header"><h2>Files</h2><span class="muted small"><?php echo e($atts->count()); ?> attached</span></div>
    <div class="panel-body">
        <?php if($errors->has('files') || $errors->has('files.*') || $errors->has('removal_reason')): ?>
            <div class="alert alert-error"><?php echo e($errors->first('files') ?: ($errors->first('files.0') ?: $errors->first('removal_reason'))); ?></div>
        <?php endif; ?>
    </div>
    <?php if($atts->isNotEmpty()): ?>
        <div class="table-wrap"><table><thead><tr><th>File</th><th>Category</th><th>Size</th><th>Added</th><th></th></tr></thead><tbody>
            <?php $__currentLoopData = $atts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr><td style="display:flex;gap:10px;align-items:center"><?php if($a->isImage()): ?><a href="<?php echo e(route('attachments.download', $a)); ?>" target="_blank"><img src="<?php echo e(route('attachments.download', $a)); ?>" alt="" loading="lazy" style="width:44px;height:44px;object-fit:cover;border-radius:5px;border:1px solid var(--border);padding:0;min-height:0"></a><?php endif; ?><div><a href="<?php echo e(route('attachments.download', $a)); ?>" <?php if($a->isInlineViewable()): ?> target="_blank" <?php endif; ?>><?php echo e($a->title ?: $a->original_name); ?></a>
                        <?php if($a->title): ?><div class="muted small"><?php echo e($a->original_name); ?></div><?php endif; ?><div class="muted small" title="SHA-256 <?php echo e($a->sha256); ?>"><?php echo e(substr($a->sha256, 0, 12)); ?>…</div></div></td>
                    <td><?php echo e($cats[$a->category] ?? $a->category); ?></td><td><?php echo e($fmt($a->size_bytes)); ?></td>
                    <td><?php echo e($a->created_at->format('d M Y H:i')); ?><div class="muted small"><?php echo e($a->uploaded_by_name); ?></div></td>
                    <td><?php if($a->isImage()): ?><?php if($a->is_cover): ?><span class="status status-completed">Main photo</span><?php else: ?><form method="POST" action="<?php echo e(route('attachments.cover', $a)); ?>" style="display:inline"><?php echo csrf_field(); ?><button class="btn btn-small">Use as main photo</button></form><?php endif; ?> <?php endif; ?><form method="POST" action="<?php echo e(route('attachments.remove', $a)); ?>" style="display:flex;gap:6px;margin-top:4px"><?php echo csrf_field(); ?><input name="removal_reason" placeholder="Reason" required style="width:130px"><button class="btn btn-danger">Remove</button></form></td></tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody></table></div>
    <?php endif; ?>
    <div class="panel-body" style="border-top:1px solid var(--border,#ddd)">
        <form method="POST" action="<?php echo e(route('attachments.store', [$type, $id])); ?>" enctype="multipart/form-data" class="form-grid"><?php echo csrf_field(); ?>
            <label>Files<input type="file" name="files[]" multiple required></label>
            <label>Category<select name="category"><?php $__currentLoopData = $cats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>"><?php echo e($l); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
            <label>Title (optional)<input name="title" maxlength="200"></label>
            <div><button class="btn btn-primary">Attach</button></div>
        </form>
        <p class="muted small">PDF, images, Excel/Word, CSV, text and CAD files up to <?php echo e(round(config('foundry.attachments.max_kb') / 1024, 0)); ?> MB each. Files are stored privately.</p>
    </div>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/attachments/_panel.blade.php ENDPATH**/ ?>