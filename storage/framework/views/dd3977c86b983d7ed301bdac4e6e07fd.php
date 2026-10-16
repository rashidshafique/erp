<div class="panel" id="cavity-map">
    <div class="panel-header"><h2>Cavity map: which product each cavity makes</h2></div>
    <div class="panel-body">
        <p class="muted small">
            Each cavity of a pattern revision makes one product revision, either the whole casting or one
            component of it. Once a map exists, moulds and moulding runs using this revision are checked against it.
        </p>

        <?php if($errors->has('cavity') || $errors->has('cavity_number') || $errors->has('product_revision_id') || $errors->has('component_label') || $errors->has('poured_weight_kg')): ?>
            <div class="alert alert-error">
                <ul>
                    <?php $__currentLoopData = ['cavity', 'cavity_number', 'product_revision_id', 'component_label', 'poured_weight_kg']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php $__errorArgs = [$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><li><?php echo e($message); ?></li><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php $__currentLoopData = $pattern->revisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $revision): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <h3 style="margin-top:22px">Revision <?php echo e($revision->revision); ?>

                <span class="muted small">
                    (<?php echo e($revision->cavityMap->count()); ?> of <?php echo e($revision->cavities_per_mould ?? '?'); ?> cavities mapped)
                </span>
            </h3>

            <?php if($revision->cavityMap->isEmpty()): ?>
                <div class="empty-state">No cavities mapped for this revision.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Cavity</th><th>Product</th><th>Rev</th><th>Makes</th>
                                <th>Pieces / product</th><th>Casting kg</th><th>Poured kg</th><th>Yield</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $revision->cavityMap; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cav): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td><strong><?php echo e($cav->cavity_number); ?></strong></td>
                                    <td>
                                        <a href="<?php echo e(route('products.show', $cav->productRevision->product)); ?>">
                                            <?php echo e($cav->productRevision->product->code); ?>

                                        </a>
                                    </td>
                                    <td><?php echo e($cav->productRevision->revision); ?></td>
                                    <td><?php echo e($cav->label()); ?></td>
                                    <td><?php echo e($cav->pieces_per_product); ?></td>
                                    <td><?php echo e($cav->casting_weight_kg !== null ? number_format((float) $cav->casting_weight_kg, 3) : '—'); ?></td>
                                    <td><?php echo e($cav->poured_weight_kg !== null ? number_format((float) $cav->poured_weight_kg, 3) : '—'); ?></td>
                                    <td><?php echo e($cav->yieldPercent() !== null ? $cav->yieldPercent().' %' : '—'); ?></td>
                                    <td>
                                        <form method="POST" action="<?php echo e(route('patterns.cavities.destroy', [$pattern, $revision, $cav])); ?>"
                                              onsubmit="return confirm('Remove this cavity link?')">
                                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                            <button class="btn" type="submit">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo e(route('patterns.cavities.store', [$pattern, $revision])); ?>" style="margin-top:12px">
                <?php echo csrf_field(); ?>
                <div class="form-grid">
                    <div class="field">
                        <label for="cn_<?php echo e($revision->id); ?>">Cavity number *</label>
                        <input id="cn_<?php echo e($revision->id); ?>" name="cavity_number" type="number" min="1" required>
                    </div>
                    <div class="field">
                        <label for="pr_<?php echo e($revision->id); ?>">Product revision *</label>
                        <select id="pr_<?php echo e($revision->id); ?>" name="product_revision_id" required>
                            <option value="">Select product</option>
                            <?php $__currentLoopData = $productRevisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($pr->id); ?>"><?php echo e($pr->product?->code); ?> rev <?php echo e($pr->revision); ?> — <?php echo e($pr->product?->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="role_<?php echo e($revision->id); ?>">Cavity makes *</label>
                        <select id="role_<?php echo e($revision->id); ?>" name="cavity_role">
                            <option value="WHOLE">The whole casting</option>
                            <option value="PART">One component of the product</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="cl_<?php echo e($revision->id); ?>">Component name (if a part)</label>
                        <input id="cl_<?php echo e($revision->id); ?>" name="component_label" maxlength="100" placeholder="e.g. cover half">
                    </div>
                    <div class="field">
                        <label for="pp_<?php echo e($revision->id); ?>">Pieces per product</label>
                        <input id="pp_<?php echo e($revision->id); ?>" name="pieces_per_product" type="number" min="1" value="1">
                    </div>
                    <div class="field">
                        <label for="cw_<?php echo e($revision->id); ?>">Casting weight (kg)</label>
                        <input id="cw_<?php echo e($revision->id); ?>" name="casting_weight_kg" type="number" step="0.0001" min="0.0001">
                    </div>
                    <div class="field">
                        <label for="pw_<?php echo e($revision->id); ?>">Poured weight incl. gating (kg)</label>
                        <input id="pw_<?php echo e($revision->id); ?>" name="poured_weight_kg" type="number" step="0.0001" min="0.0001">
                    </div>
                    <div class="field field-full">
                        <label for="nt_<?php echo e($revision->id); ?>">Notes</label>
                        <input id="nt_<?php echo e($revision->id); ?>" name="notes" maxlength="2000">
                    </div>
                </div>
                <div class="actions" style="margin-top:10px">
                    <button class="btn btn-primary" type="submit">Link cavity to product</button>
                </div>
            </form>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</div>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/patterns/_cavity-map.blade.php ENDPATH**/ ?>