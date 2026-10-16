<?php
    $links = $product->revisions->flatMap->patternCavities;
?>
<div class="panel">
    <div class="panel-header"><h2>Made on these patterns</h2></div>
    <?php if($links->isEmpty()): ?>
        <div class="empty-state">No pattern cavity is linked to this product yet. Link cavities from the pattern page.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Pattern</th><th>Pattern rev</th><th>Cavity</th><th>Product rev</th><th>Makes</th><th>Casting kg</th></tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $links->sortBy(fn ($l) => $l->patternRevision->pattern->code.$l->patternRevision->revision.str_pad($l->cavity_number, 5, '0', STR_PAD_LEFT)); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><a href="<?php echo e(route('patterns.show', $l->patternRevision->pattern)); ?>"><?php echo e($l->patternRevision->pattern->code); ?></a></td>
                            <td><?php echo e($l->patternRevision->revision); ?></td>
                            <td><?php echo e($l->cavity_number); ?></td>
                            <td><?php echo e($l->productRevision->revision); ?></td>
                            <td><?php echo e($l->label()); ?></td>
                            <td><?php echo e($l->casting_weight_kg !== null ? number_format((float) $l->casting_weight_kg, 3) : '—'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/products/_made-on-patterns.blade.php ENDPATH**/ ?>