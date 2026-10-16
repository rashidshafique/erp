<?php $ph = \App\Models\Attachment::coverFor('employee', $node->id); $kids = $depth < 12 ? $byManager->get($node->id) : null; ?>
<li>
    <div class="oc-card" id="n-<?php echo e($node->id); ?>" data-name="<?php echo e(mb_strtolower($node->full_name.' '.$node->designation?->name.' '.$node->department?->name)); ?>">
        <?php if($ph): ?><img class="oc-av" src="<?php echo e(route('attachments.download', $ph)); ?>" alt=""><?php else: ?><span class="oc-av oc-ini"><?php echo e(strtoupper(mb_substr($node->first_name, 0, 1).mb_substr((string) $node->last_name, 0, 1))); ?></span><?php endif; ?>
        <div class="oc-t"><a href="<?php echo e(route('hr.employees.show', $node)); ?>"><strong><?php echo e($node->full_name); ?></strong></a><small><?php echo e($node->designation?->name ?? 'No title yet'); ?></small><?php if($node->department): ?><small class="oc-dep"><?php echo e($node->department->name); ?></small><?php endif; ?></div>
        <div class="oc-acts">
            <?php if($node->phone): ?><a href="tel:<?php echo e($node->phone); ?>" title="<?php echo e($node->phone); ?>"><?php echo \App\Support\NavIcons::get('phone', 14); ?></a><?php endif; ?>
            <?php if($node->work_email): ?><a href="mailto:<?php echo e($node->work_email); ?>" title="<?php echo e($node->work_email); ?>"><?php echo \App\Support\NavIcons::get('mail', 14); ?></a><?php endif; ?>
            <span class="oc-count" title="Direct reports"><?php echo e($kids ? $kids->count() : 0); ?> report<?php echo e(($kids ? $kids->count() : 0) === 1 ? '' : 's'); ?></span>
        </div>
    </div>
    <button type="button" class="oc-add" data-add="<?php echo e($node->id); ?>" data-who="<?php echo e($node->full_name); ?>" title="Add a person under <?php echo e($node->full_name); ?>" aria-label="Add a person under <?php echo e($node->full_name); ?>">+</button>
    <?php if($kids): ?><ul><?php $__currentLoopData = $kids; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php echo $__env->make('hr._orgnode', ['node' => $k, 'byManager' => $byManager, 'depth' => $depth + 1], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul><?php endif; ?>
</li>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/hr/_orgnode.blade.php ENDPATH**/ ?>