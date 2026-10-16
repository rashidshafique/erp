
<tr class="aw-row">
    <td><select name="rows[<?php echo e($i); ?>][pattern_revision_id]" class="aw-pattern" style="width:280px" required>
        <?php $__currentLoopData = $patterns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($p->id); ?>" data-products="<?php echo e(implode(',', $patternProducts[$p->id] ?? [])); ?>" <?php if(($row['pattern_revision_id'] ?? null) === $p->id): echo 'selected'; endif; ?>><?php echo e($p->pattern->code); ?> rev <?php echo e($p->revision); ?> (<?php echo e($p->pattern->pattern_type); ?>)</option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <div class="aw-sandwarn" hidden style="color:var(--danger,#b42318);font-size:12px;margin-top:3px;width:280px;white-space:normal"><span class="aw-sandtext"></span>
        <label style="font-weight:600;display:flex;gap:6px;align-items:center"><input type="checkbox" class="aw-any" name="rows[<?php echo e($i); ?>][sand_override]" value="1" <?php if(! empty($row['sand_override'])): echo 'checked'; endif; ?>> Use anyway</label></div></td>
    <td><select name="rows[<?php echo e($i); ?>][production_order_id]" class="aw-order" style="width:280px">
        <option value="">Automatic: the open order for this plate</option>
        <?php $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($o->id); ?>" data-product="<?php echo e($o->product_id); ?>" <?php if(($row['production_order_id'] ?? null) === $o->id): echo 'selected'; endif; ?>><?php echo e($o->production_order_number); ?> · <?php echo e($o->customerLabel()); ?> · <?php echo e($o->product?->code); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select><div class="muted small aw-preview"></div></td>
    <td><input type="number" name="rows[<?php echo e($i); ?>][planned_moulds]" min="1" max="5000" value="<?php echo e($row['planned_moulds'] ?? 10); ?>" style="width:90px" required></td>
    <td><button type="button" class="btn btn-small aw-del" title="Remove this row" aria-label="Remove this row">✕</button></td>
</tr>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/work-lists/_row.blade.php ENDPATH**/ ?>