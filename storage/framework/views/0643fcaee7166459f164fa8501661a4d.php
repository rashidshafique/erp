<?php $__env->startSection('title', 'New product'); ?>
<?php $__env->startSection('breadcrumb'); ?>
<a href="<?php echo e(route('products.index')); ?>">Products</a> / New product
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div>
        <h1>New product</h1>
        <p>Create a product and its first engineering revision together.</p>
    </div>
    <a class="btn" href="<?php echo e(route('products.index')); ?>">Cancel</a>
</div>

<form method="POST" action="<?php echo e(route('products.store')); ?>">
    <?php echo csrf_field(); ?>

    <div class="panel">
        <div class="panel-header"><h2>Product information</h2></div>
        <div class="panel-body">
            <div class="form-grid" data-progressive="3">
                <div class="field">
                    <label for="code">Product code</label>
                    <input id="code" name="code" maxlength="40"
                           value="<?php echo e(old('code')); ?>" placeholder="Leave blank to generate: <?php echo e(app(\App\Services\Codes\CodeGenerator::class)->preview('product')); ?>">
                    <div class="help">Use your established part-number convention.</div>
                    <?php $__errorArgs = ['code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="error-text"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div class="field">
                    <label for="name">Product name <span class="required">*</span></label>
                    <input id="name" name="name" required maxlength="255"
                           value="<?php echo e(old('name')); ?>" placeholder="e.g. Gas valve body">
                    <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="error-text"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div class="field">
                    <label for="product_type">Product type <span class="required">*</span></label>
                    <select id="product_type" name="product_type" required>
                        <?php $__currentLoopData = ['CASTING' => 'Casting', 'CORE' => 'Core', 'OTHER' => 'Other']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($value); ?>" <?php if(old('product_type', 'CASTING') === $value): echo 'selected'; endif; ?>>
                                <?php echo e($label); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="field">
                    <label for="base_uom">Base unit of measure <span class="required">*</span></label>
                    <select id="base_uom" name="base_uom" required>
                        <?php $__currentLoopData = ['piece' => 'Piece', 'kg' => 'Kilogram', 'set' => 'Set']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($value); ?>" <?php if(old('base_uom', 'piece') === $value): echo 'selected'; endif; ?>>
                                <?php echo e($label); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="field">
                    <label for="nominal_weight_kg">Nominal casting weight (kg)</label>
                    <input id="nominal_weight_kg" type="number" step="0.0001" min="0"
                           name="nominal_weight_kg" value="<?php echo e(old('nominal_weight_kg')); ?>">
                    <div class="help">Nominal finished-product weight, not total metal poured.</div>
                    <?php $__errorArgs = ['nominal_weight_kg'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="error-text"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div class="field">
                    <label for="active">Product status <span class="required">*</span></label>
                    <select id="active" name="active" required>
                        <option value="1" <?php if(old('active', '1') === '1'): echo 'selected'; endif; ?>>Active</option>
                        <option value="0" <?php if(old('active') === '0'): echo 'selected'; endif; ?>>Inactive</option>
                    </select>
                </div>

                <div class="field field-full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"
                              placeholder="Application, customer, or other identifying details"><?php echo e(old('description')); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Initial product revision</h2></div>
        <div class="panel-body">
            <p class="muted small">
                Each product needs a revision before it can be used in a production order.
                This creates the initial revision with the product.
            </p>
            <div class="form-grid" data-progressive="3">
                <div class="field">
                    <label for="revision">Revision identifier <span class="required">*</span></label>
                    <input id="revision" name="revision" required maxlength="20"
                           value="<?php echo e(old('revision', 'A')); ?>" placeholder="A">
                    <?php $__errorArgs = ['revision'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="error-text"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div class="field">
                    <label for="revision_status">Revision status <span class="required">*</span></label>
                    <select id="revision_status" name="revision_status" required>
                        <?php $__currentLoopData = $revisionStatuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($status); ?>" <?php if(old('revision_status', 'DRAFT') === $status): echo 'selected'; endif; ?>>
                                <?php echo e($status); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <div class="help">Use DRAFT until engineering has reviewed the specification.</div>
                </div>

                <div class="field">
                    <label for="net_weight_kg">Net weight for this revision (kg)</label>
                    <input id="net_weight_kg" type="number" step="0.0001" min="0"
                           name="net_weight_kg" value="<?php echo e(old('net_weight_kg')); ?>">
                </div>

                <div class="field">
                    <label for="effective_from">Effective from</label>
                    <input id="effective_from" type="date" name="effective_from"
                           value="<?php echo e(old('effective_from')); ?>">
                </div>

                <div class="field">
                    <label for="effective_to">Effective to</label>
                    <input id="effective_to" type="date" name="effective_to"
                           value="<?php echo e(old('effective_to')); ?>">
                </div>

                <div class="field field-full">
                    <label for="engineering_notes">Engineering notes</label>
                    <textarea id="engineering_notes" name="engineering_notes"
                              placeholder="Drawing reference, dimensional revision, material specification, or change notes"><?php echo e(old('engineering_notes')); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit">Save product and revision</button>
        <a class="btn" href="<?php echo e(route('products.index')); ?>">Cancel</a>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/products/create.blade.php ENDPATH**/ ?>