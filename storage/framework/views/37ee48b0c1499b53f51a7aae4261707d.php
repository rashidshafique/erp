<?php $__env->startSection('title', 'Edit '.$product->code); ?>
<?php $__env->startSection('breadcrumb'); ?>
<a href="<?php echo e(route('products.index')); ?>">Products</a> /
<a href="<?php echo e(route('products.show', $product)); ?>"><?php echo e($product->code); ?></a> / Edit
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div>
        <h1>Edit product</h1>
        <p>Update the product master fields. Existing revisions are retained.</p>
    </div>
    <a class="btn" href="<?php echo e(route('products.show', $product)); ?>">Cancel</a>
</div>

<div class="panel">
    <div class="panel-header"><h2><?php echo e($product->code); ?></h2></div>
    <div class="panel-body">
        <form method="POST" action="<?php echo e(route('products.update', $product)); ?>">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>

            <div class="form-grid">
                <div class="field">
                    <label for="code">Product code <span class="required">*</span></label>
                    <input id="code" name="code" maxlength="40" required
                           value="<?php echo e(old('code', $product->code)); ?>">
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
                    <input id="name" name="name" maxlength="255" required
                           value="<?php echo e(old('name', $product->name)); ?>">
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
                        <?php $__currentLoopData = ['CASTING', 'CORE', 'OTHER']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($type); ?>" <?php if(old('product_type', $product->product_type) === $type): echo 'selected'; endif; ?>>
                                <?php echo e($type); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="field">
                    <label for="base_uom">Base unit <span class="required">*</span></label>
                    <select id="base_uom" name="base_uom" required>
                        <?php $__currentLoopData = ['piece', 'kg', 'set']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $uom): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($uom); ?>" <?php if(old('base_uom', $product->base_uom) === $uom): echo 'selected'; endif; ?>>
                                <?php echo e($uom); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="field">
                    <label for="nominal_weight_kg">Nominal weight (kg)</label>
                    <input id="nominal_weight_kg" name="nominal_weight_kg" type="number"
                           step="0.0001" min="0"
                           value="<?php echo e(old('nominal_weight_kg', $product->nominal_weight_kg)); ?>">
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
                        <option value="1" <?php if((string) old('active', (int) $product->active) === '1'): echo 'selected'; endif; ?>>Active</option>
                        <option value="0" <?php if((string) old('active', (int) $product->active) === '0'): echo 'selected'; endif; ?>>Inactive</option>
                    </select>
                </div>

                <div class="field field-full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?php echo e(old('description', $product->description)); ?></textarea>
                </div>
            </div>

            <div class="actions" style="margin-top:20px">
                <button class="btn btn-primary" type="submit">Save changes</button>
                <a class="btn" href="<?php echo e(route('products.show', $product)); ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/products/edit.blade.php ENDPATH**/ ?>