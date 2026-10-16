<?php $__env->startSection('title', 'Create Heat'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div>
        <h1>Create melting heat</h1>
        <p>Plan a melt and set its target metal weight and operating parameters.</p>
    </div>
    <div class="actions">
        <a class="btn" href="<?php echo e(route('heats.index')); ?>">Back to heats</a>
    </div>
</div>

<?php if($errors->any()): ?>
    <div class="panel">
        <div class="panel-body" style="color:var(--danger)">
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div><?php echo e($error); ?></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header"><h2>Heat details</h2></div>
    <div class="panel-body">
        <?php if($furnaces->isEmpty()): ?>
            <p>No active furnaces are available. Register or activate a furnace first.</p>
        <?php else: ?>
        <form method="POST" action="<?php echo e(route('heats.store')); ?>">
            <?php echo csrf_field(); ?>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:18px">
                <div>
                    <label for="furnace_id">Furnace *</label>
                    <select id="furnace_id" name="furnace_id" required
                            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                        <option value="">Select furnace</option>
                        <?php $__currentLoopData = $furnaces; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $furnace): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option
                                value="<?php echo e($furnace->id); ?>"
                                data-type="<?php echo e($furnace->furnace_type); ?>"
                                data-max-power="<?php echo e($furnace->max_operating_power_kw); ?>"
                                <?php if(old('furnace_id') === $furnace->id): echo 'selected'; endif; ?>
                            >
                                <?php echo e($furnace->code); ?> — <?php echo e($furnace->name); ?> (<?php echo e($furnace->furnace_type); ?>)
                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div>
                    <label for="iron_grade_id">Iron grade</label>
                    <select id="iron_grade_id" name="iron_grade_id"
                            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                        <option value="">No grade (no quality gates)</option>
                        <?php $__currentLoopData = $grades; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $grade): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($grade->id); ?>" <?php if(old('iron_grade_id') === $grade->id): echo 'selected'; endif; ?>><?php echo e($grade->code); ?> (<?php echo e($grade->family); ?>)</option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <?php $__errorArgs = ['iron_grade_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="error-text"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div>
                    <label for="charge_batch_id">Charge recipe</label>
                    <select id="charge_batch_id" name="charge_batch_id"
                            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                        <option value="">No recipe selected</option>
                        <?php $__currentLoopData = $chargeBatches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $batch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($batch->id); ?>"
                                    data-process="<?php echo e($batch->process_type); ?>"
                                    data-charge-weight="<?php echo e($batch->target_total_charge_weight_kg); ?>"
                                    <?php if(old('charge_batch_id') === $batch->id): echo 'selected'; endif; ?>>
                                <?php echo e($batch->code); ?> — <?php echo e($batch->name); ?> (<?php echo e($batch->process_type); ?>, Rev <?php echo e($batch->revision); ?>)
                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <small class="muted">Only active, currently effective recipes matching the furnace are selectable.</small>
                </div>

                <div>
                    <label for="planned_start_at">Planned start</label>
                    <input id="planned_start_at" name="planned_start_at" type="datetime-local"
                           value="<?php echo e(old('planned_start_at')); ?>"
                           style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                </div>

                <div>
                    <label for="target_metal_weight_kg">Target metal weight (kg) *</label>
                    <input id="target_metal_weight_kg" name="target_metal_weight_kg"
                           type="number" min="0.001" step="0.001" required
                           value="<?php echo e(old('target_metal_weight_kg')); ?>"
                           style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                </div>

                <div id="power-limit-field">
                    <label for="operating_power_limit_kw">Heat operating power limit (kW)</label>
                    <input id="operating_power_limit_kw" name="operating_power_limit_kw"
                           type="number" min="0.001" step="0.001"
                           value="<?php echo e(old('operating_power_limit_kw')); ?>"
                           style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    <small id="power-limit-help" class="muted">
                        Applies to induction heats only.
                    </small>
                </div>

                <div>
                    <label for="target_temperature_c">Target temperature (°C)</label>
                    <input id="target_temperature_c" name="target_temperature_c"
                           type="number" min="0.001" step="0.01"
                           value="<?php echo e(old('target_temperature_c')); ?>"
                           style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                </div>

                <div>
                    <label for="operator_name">Responsible operator *</label>
                    <input id="operator_name" name="operator_name" maxlength="255" required
                           value="<?php echo e(old('operator_name')); ?>"
                           style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                </div>
            </div>

            <div id="recipe-preview" class="panel" style="margin-top:18px" hidden>
                <div class="panel-header"><h2>Planned charge components</h2></div>
                <div class="panel-body">
                    <p id="recipe-preview-basis" class="muted"></p>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr><th>Material</th><th>Role</th><th>Calculation</th><th>Planned kg</th></tr>
                            </thead>
                            <tbody id="recipe-preview-body"></tbody>
                        </table>
                    </div>
                    <p class="muted">These are planned quantities only. Actual charge entries and costs will be recorded separately.</p>
                </div>
            </div>

            <div style="margin-top:18px">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3" maxlength="10000"
                          style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit"><?php echo e(old('notes')); ?></textarea>
            </div>

            <div class="actions" style="margin-top:22px">
                <button type="submit" class="btn btn-primary">Create heat</button>
                <a class="btn" href="<?php echo e(route('heats.index')); ?>">Cancel</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const furnace = document.getElementById('furnace_id');
    const field = document.getElementById('power-limit-field');
    const input = document.getElementById('operating_power_limit_kw');
    const help = document.getElementById('power-limit-help');
    const recipe = document.getElementById('charge_batch_id');
    const recipePreview = document.getElementById('recipe-preview');
    const recipePreviewBody = document.getElementById('recipe-preview-body');
    const recipePreviewBasis = document.getElementById('recipe-preview-basis');

    const recipeData = <?php echo json_encode($recipeData, 15, 512) ?>;

    function updateRecipeOptions() {
        const option = furnace.options[furnace.selectedIndex];
        const process = option ? option.dataset.type : '';
        [...recipe.options].forEach(item => {
            if (!item.value) {
                item.hidden = false;
                return;
            }
            item.hidden = item.dataset.process !== process;
        });

        const selected = recipe.options[recipe.selectedIndex];
        if (selected && selected.value && selected.dataset.process !== process) {
            recipe.value = '';
        }
        updateRecipePreview();
    }

    function updateRecipePreview() {
        const selected = recipeData.find(item => item.id === recipe.value);
        recipePreviewBody.replaceChildren();

        if (!selected) {
            recipePreview.hidden = true;
            return;
        }

        recipePreview.hidden = false;
        recipePreviewBasis.textContent = selected.weight
            ? 'Percentage quantities use target total charge weight: ' + Number(selected.weight).toLocaleString() + ' kg.'
            : 'This recipe has no target total charge weight. Percentage components cannot be calculated until a basis is configured.';

        selected.items.forEach(item => {
            let calculation = '—';
            let quantity = null;

            if (item.qty !== null && item.qty !== '') {
                quantity = Number(item.qty);
                calculation = 'Fixed quantity';
            } else if (item.percent !== null && item.percent !== '' && selected.weight) {
                quantity = Number(selected.weight) * Number(item.percent) / 100;
                calculation = Number(item.percent).toFixed(4) + '% of charge';
            } else if (item.percent !== null && item.percent !== '') {
                calculation = Number(item.percent).toFixed(4) + '%; basis missing';
            }

            const tr = document.createElement('tr');
            [item.material || 'Unknown material', item.role || '—', calculation,
             quantity === null ? '—' : quantity.toLocaleString(undefined, {maximumFractionDigits: 3}) + ' kg'
            ].forEach(value => {
                const td = document.createElement('td');
                td.textContent = value;
                tr.appendChild(td);
            });
            recipePreviewBody.appendChild(tr);
        });
    }

    function updatePowerLimit() {
        const option = furnace.options[furnace.selectedIndex];
        const isInduction = option && option.dataset.type === 'INDUCTION';
        field.hidden = !isInduction;
        input.disabled = !isInduction;

        if (!isInduction) {
            input.value = '';
            help.textContent = 'Applies to induction heats only.';
            return;
        }

        const maximum = option.dataset.maxPower;
        help.textContent = maximum
            ? 'Furnace maximum configured: ' + maximum + ' kW.'
            : 'No furnace maximum configured. Enter a limit only if known.';
    }

    furnace.addEventListener('change', function () {
        updatePowerLimit();
        updateRecipeOptions();
    });
    recipe.addEventListener('change', updateRecipePreview);
    updatePowerLimit();
    updateRecipeOptions();
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/heats/create.blade.php ENDPATH**/ ?>