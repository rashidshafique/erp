
<?php ($cover = \App\Models\Attachment::coverFor($type, (string) $id)); ?>
<div style="float:right;margin:0 0 8px 14px;width:<?php echo e($size ?? 150); ?>px;text-align:center">
    <?php if($cover): ?>
        <a href="<?php echo e(route('attachments.download', $cover)); ?>" target="_blank" title="<?php echo e($cover->title ?: $cover->original_name); ?>">
            <img src="<?php echo e(route('attachments.download', $cover)); ?>" alt="<?php echo e($cover->title ?: 'Photo'); ?>"
                 style="width:100%;height:<?php echo e($size ?? 150); ?>px;object-fit:contain;background:#fff;border:1px solid var(--border);border-radius:8px;padding:2px;min-height:0">
        </a>
    <?php else: ?>
        <div class="muted small" style="height:<?php echo e($size ?? 150); ?>px;display:flex;align-items:center;justify-content:center;border:1px dashed var(--border);border-radius:8px;padding:8px">
            No photo yet.<br>Add one under Files.
        </div>
    <?php endif; ?>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/attachments/_cover.blade.php ENDPATH**/ ?>