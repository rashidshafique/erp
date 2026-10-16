<?php $__env->startSection('title', 'Foundry Settings'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div>
        <h1>Foundry Settings</h1>
        <p>Configure operational behaviour for your foundry.</p>
    </div>
</div>

<?php if(session('success')): ?>
    <div class="panel">
        <div class="panel-body" style="color:var(--success)">
            <?php echo e(session('success')); ?>

        </div>
    </div>
<?php endif; ?>

<?php if($errors->any()): ?>
    <div class="panel">
        <div class="panel-body" style="color:var(--danger)">
            <strong>Please correct the following:</strong>
            <ul>
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <h2>Inventory and charge control</h2>
    </div>

    <div class="panel-body">
        <form method="POST" action="<?php echo e(route('settings.update')); ?>">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>

            <div style="display:flex;gap:18px;align-items:flex-start;margin-bottom:20px">
                <div style="flex:1">
                    <label for="auto_post_internal_returns_to_stock"
                           style="display:block;font-weight:650;margin-bottom:6px">
                        Automatically post internal returns to stock
                    </label>

                    <p class="muted" style="margin-top:0">
                        When enabled, eligible internal-return charge entries create
                        traceable stock receipts and cost layers when a heat charge
                        is posted. When disabled, return quantities and transfer costs
                        are included in the heat charge without automatically updating
                        return stock.
                    </p>

                    <label style="display:flex;gap:10px;align-items:center">
                        <input
                            type="hidden"
                            name="auto_post_internal_returns_to_stock"
                            value="0"
                        >
                        <input
                            type="checkbox"
                            id="auto_post_internal_returns_to_stock"
                            name="auto_post_internal_returns_to_stock"
                            value="1"
                            <?php echo e(old(
                                'auto_post_internal_returns_to_stock',
                                $autoPostReturns ? '1' : '0'
                            ) === '1' ? 'checked' : ''); ?>

                        >
                        Enable automatic internal-return stock posting
                    </label>
                </div>
            </div>

            <p class="muted small">
                Default: Off. Changing this setting affects future charge postings;
                it does not modify previously posted heats.
            </p>

            <button class="btn btn-primary" type="submit">Save settings</button>
        </form>
    </div>
</div>
<div class="panel">
    <div class="panel-header"><h2>Process controls</h2></div>
    <div class="panel-body">
        <form method="POST" action="<?php echo e(route('process-controls.update')); ?>" class="form-grid">
            <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
            <label style="display:flex;gap:10px;align-items:center"><input type="checkbox" name="enforce_treatment_records" value="1" <?php if(\App\Http\Controllers\ProcessControlController::enforceTreatment()): echo 'checked'; endif; ?>>
                Block pouring unless magnesium treatment and inoculation are recorded (when the grade needs them)</label>
            <label>Maximum minutes from magnesium treatment to pour (fade)<input type="number" min="1" max="120" name="max_fade_minutes" value="<?php echo e(\App\Http\Controllers\ProcessControlController::maxFadeMinutes()); ?>"></label>
            <p class="muted small">When the box is not ticked the pour gate still shows missing or late treatment as a warning, but does not stop the pour.</p>
            <div><button class="btn btn-primary">Save process controls</button></div>
        </form>
    </div>
</div>
<div class="panel">
    <div class="panel-header"><h2>Screen behaviour</h2></div>
    <div class="panel-body">
        <form method="POST" action="<?php echo e(route('settings.interface.update')); ?>">
            <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
            <label style="display:flex;gap:10px;align-items:center">
                <input type="checkbox" name="row_click" value="1" <?php if(\App\Models\AppSetting::getValue('ui.row_click', '1') === '1'): echo 'checked'; endif; ?>>
                Click anywhere on a table row to open its detail page
            </label>
            <p class="muted small">Applies to every list that has a detail page: products, patterns, orders, heats, lots, NCRs and so on. Links and buttons inside a row still work on their own. Ctrl/Cmd-click opens the page in a new tab.</p>
            <button class="btn btn-primary">Save</button>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/settings/edit.blade.php ENDPATH**/ ?>