<?php
    $u = auth()->user();
    $waiting = \App\Models\ApprovalRequest::where('status', 'PENDING')->when($u && ! $u->isAdmin(), fn ($q) => $q->where('requested_by_id', '!=', $u->id)->whereIn('permission', $u->permissions()))->count();
    $ready = $u ? \App\Models\ApprovalRequest::where('status', 'APPROVED')->where('requested_by_id', $u->id)->count() : \App\Models\ApprovalRequest::where('status', 'APPROVED')->count();
?>
<?php if($waiting || $ready): ?>
    <div class="alert alert-success" style="border-color:#e6c36a;background:#fff8e1;color:#7a5b00;border-radius:12px;margin-bottom:16px">
        <?php if($waiting): ?><strong><?php echo e($waiting); ?></strong> approval<?php echo e($waiting === 1 ? '' : 's'); ?> waiting for a decision.<?php endif; ?>
        <?php if($ready): ?><strong><?php echo e($ready); ?></strong> approved — repeat the action to go ahead.<?php endif; ?>
        <a href="<?php echo e(route('approvals.index')); ?>">Open approvals →</a>
    </div>
<?php endif; ?>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/dashboard/_approvals.blade.php ENDPATH**/ ?>