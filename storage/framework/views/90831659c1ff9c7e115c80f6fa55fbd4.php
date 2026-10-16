<div class="panel">
    <div class="panel-header"><h2>Cooling before shakeout</h2></div>
    <div class="panel-body">
        <p class="muted small">Minimum minutes a casting must stay in the mould after the pour, set per revision (heavy sections need longer). Shakeout earlier than this needs a written reason. Leave empty for no rule.</p>
        <?php $__currentLoopData = $pattern->revisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $revision): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <form method="POST" action="<?php echo e(route('pattern-revisions.cooling', $revision->id)); ?>" style="display:flex;gap:10px;align-items:center;margin-bottom:8px"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                <span style="width:90px">Revision <?php echo e($revision->revision); ?></span>
                <input type="number" min="0" name="min_cooling_minutes" value="<?php echo e(\Illuminate\Support\Facades\DB::table('pattern_revisions')->where('id', $revision->id)->value('min_cooling_minutes')); ?>" style="width:110px"> minutes
                <button class="btn">Save</button>
            </form>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/patterns/_cooling.blade.php ENDPATH**/ ?>