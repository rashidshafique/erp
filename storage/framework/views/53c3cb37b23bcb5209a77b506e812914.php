<?php $__env->startSection('title', 'Heat '.$heat->heat_number); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div>
        <h1>Heat <?php echo e($heat->heat_number); ?></h1>
        <p>
            <?php echo e($heat->furnace?->name ?? 'Unknown furnace'); ?>

            · <?php echo e($heat->furnace?->furnace_type ?? $heat->process_type); ?>

            · <?php echo e($heat->status); ?>

        </p>
    </div>
    <div class="actions">
        <a class="btn" href="<?php echo e(route('heats.index')); ?>">Back to heats</a>
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
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div><?php echo e($error); ?></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
<?php endif; ?>

<div class="tab-marker" data-tab-start="Overview"></div>
<div class="panel">
    <div class="panel-header"><h2>Heat summary</h2></div>
    <div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px">
        <div>
            <div class="muted small">Target metal</div>
            <strong>
                <?php echo e($heat->target_metal_weight_kg !== null
                    ? number_format((float) $heat->target_metal_weight_kg, 1).' kg'
                    : '—'); ?>

            </strong>
        </div>
        <div>
            <div class="muted small">Rated furnace power</div>
            <strong>
                <?php echo e($heat->furnace?->rated_power_kw !== null
                    ? number_format((float) $heat->furnace->rated_power_kw, 1).' kW'
                    : '—'); ?>

            </strong>
        </div>
        <div>
            <div class="muted small">Furnace operating maximum</div>
            <strong>
                <?php echo e($heat->furnace?->max_operating_power_kw !== null
                    ? number_format((float) $heat->furnace->max_operating_power_kw, 1).' kW'
                    : 'Not set'); ?>

            </strong>
        </div>
        <div>
            <div class="muted small">Heat operating limit</div>
            <strong>
                <?php echo e($heat->operating_power_limit_kw !== null
                    ? number_format((float) $heat->operating_power_limit_kw, 1).' kW'
                    : 'Not set'); ?>

            </strong>
        </div>
    </div>
</div>

<div class="tab-marker" data-tab-start="Chemistry & tests"></div>
<?php echo $__env->make('heats._chemistry', ['heat' => $heat], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('heats._treatment', ['heat' => $heat], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('heats._tests', ['heat' => $heat], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="tab-marker" data-tab-start="Charge"></div>
<div class="panel">
    <div class="panel-header"><h2>Charge recipe and planned materials</h2></div>
    <div class="panel-body">
        <?php if($heat->chargeBatch): ?>
            <p>
                <strong><?php echo e($heat->chargeBatch->code); ?> — <?php echo e($heat->chargeBatch->name); ?></strong>
                · Revision <?php echo e($heat->chargeBatch->revision); ?>

            </p>
            <?php if($heat->chargeBatch->instructions): ?>
                <p><?php echo e($heat->chargeBatch->instructions); ?></p>
            <?php endif; ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Material</th><th>Role</th><th>Recipe %</th><th>Planned kg</th></tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $plannedChargeItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td><?php echo e($item->material?->code); ?> — <?php echo e($item->material?->name); ?></td>
                                <td><?php echo e($item->component_role); ?></td>
                                <td><?php echo e($item->target_percent !== null ? number_format((float) $item->target_percent, 4).'%' : '—'); ?></td>
                                <td>
                                    <?php echo e($item->planned_calculated_qty_kg !== null
                                        ? number_format((float) $item->planned_calculated_qty_kg, 3)
                                        : 'Basis required'); ?>

                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="4" class="muted">This recipe has no components.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
                $actualByMaterial = $heat->materialConsumptions
                    ->groupBy('material_id')
                    ->map(fn ($entries) => (float) $entries->sum('actual_qty_kg'));

                $reconciliationRows = $plannedChargeItems
                    ->groupBy('material_id')
                    ->map(function ($items, $materialId) use ($actualByMaterial) {
                        $first = $items->first();
                        $planned = $items->contains(fn ($item) => $item->planned_calculated_qty_kg === null)
                            ? null
                            : (float) $items->sum('planned_calculated_qty_kg');

                        return [
                            'material_id' => $materialId,
                            'material' => $first->material,
                            'planned' => $planned,
                            'actual' => (float) ($actualByMaterial[$materialId] ?? 0),
                        ];
                    });

                $unplannedMaterialIds = $heat->materialConsumptions
                    ->pluck('material_id')
                    ->unique()
                    ->diff($reconciliationRows->keys());

                foreach ($unplannedMaterialIds as $materialId) {
                    $entry = $heat->materialConsumptions->firstWhere('material_id', $materialId);
                    $reconciliationRows->push([
                        'material_id' => $materialId,
                        'material' => $entry?->material,
                        'planned' => null,
                        'actual' => (float) $actualByMaterial[$materialId],
                    ]);
                }
            ?>

            <h3 style="margin-top:24px">Planned vs actual charge</h3>
            <p class="muted small">
                Variance is actual minus planned. Positive means over plan; negative means under plan.
                Internal returns count as positive charge inputs. Materials not in the recipe are shown separately.
            </p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Planned kg</th>
                            <th>Actual kg</th>
                            <th>Variance kg</th>
                            <th>Variance %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $reconciliationRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <?php
                                $variance = $row['planned'] !== null
                                    ? $row['actual'] - $row['planned']
                                    : null;
                                $variancePercent = $row['planned'] !== null && $row['planned'] != 0
                                    ? ($variance / $row['planned']) * 100
                                    : null;
                            ?>
                            <tr>
                                <td>
                                    <?php echo e($row['material']?->code ?? 'Unknown'); ?>

                                    — <?php echo e($row['material']?->name ?? 'Material unavailable'); ?>

                                    <?php if($row['planned'] === null): ?>
                                        <span class="muted small">(No comparable plan)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($row['planned'] !== null ? number_format($row['planned'], 3) : '—'); ?></td>
                                <td><?php echo e(number_format($row['actual'], 3)); ?></td>
                                <td><?php echo e($variance !== null ? number_format($variance, 3) : '—'); ?></td>
                                <td><?php echo e($variancePercent !== null ? number_format($variancePercent, 2).'%' : '—'); ?></td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="5" class="muted">No recipe or actual charge entries available for comparison.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">No charge recipe is assigned to this heat.</p>
        <?php endif; ?>
    </div>
</div>


<div class="panel">
    <div class="panel-header">
        <h2>Actual charge and material costing</h2>
    </div>
    <div class="panel-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;margin-bottom:20px">
            <div>
                <div class="muted small">Actual charge weight</div>
                <strong><?php echo e(number_format($actualChargeQty, 3)); ?> kg</strong>
            </div>
            <div>
                <div class="muted small">Actual charge material cost</div>
                <strong>PKR <?php echo e(number_format($actualChargeCost, 2)); ?></strong>
            </div>
            <div>
    <div class="muted small">Average charge material cost</div>
    <strong>
        <?php echo e($actualChargeCostPerKg !== null
            ? 'PKR '.number_format($actualChargeCostPerKg, 2).'/kg'
            : '—'); ?>

    </strong>
</div>
<div>
    <div class="muted small">Charge posting status</div>
    <strong><?php echo e($chargeIsPosted ? 'Posted to inventory ledger' : 'Draft / not posted'); ?></strong>
</div>
        </div>

        <?php if(!in_array($heat->status, ['CLOSED', 'CANCELLED'], true)): ?>
            <h3>Record charge material</h3>
            <p class="muted">
                Purchased materials use the cost recorded against an accepted lot.
                Internal returns require a positive transfer cost. Saving a charge does not deduct inventory.
            </p>

            <form method="POST" action="<?php echo e(route('heats.charge-entries.store', $heat)); ?>">
                <?php echo csrf_field(); ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px">
                    <div>
                        <label for="charge_consumption_type">Charge type *</label>
                        <select id="charge_consumption_type" name="consumption_type" required
                                style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                            <option value="RAW_MATERIAL" <?php if(old('consumption_type', 'RAW_MATERIAL') === 'RAW_MATERIAL'): echo 'selected'; endif; ?>>Purchased / raw material</option>
                            <option value="INTERNAL_RETURN" <?php if(old('consumption_type') === 'INTERNAL_RETURN'): echo 'selected'; endif; ?>>Internal return</option>
                        </select>
                    </div>
                    <div>
                        <label for="charge_material_id">Material *</label>
                        <select id="charge_material_id" name="material_id" required
                                style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                            <option value="">Select material</option>
                            <?php $__currentLoopData = $activeMaterials; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $material): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($material->id); ?>" <?php if(old('material_id') === $material->id): echo 'selected'; endif; ?>>
                                    <?php echo e($material->code); ?> — <?php echo e($material->name); ?>

                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div id="charge_lot_field">
                        <label for="charge_material_lot_id">Accepted material lot *</label>
                        <select id="charge_material_lot_id" name="material_lot_id"
                                style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                            <option value="">Select lot</option>
                            <?php $__currentLoopData = $acceptedLots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($lot->id); ?>"
                                        data-material-id="<?php echo e($lot->material_id); ?>"
                                        <?php if(old('material_lot_id') === $lot->id): echo 'selected'; endif; ?>>
                                    <?php echo e($lot->lot_number); ?> — <?php echo e($lot->material?->name); ?> (PKR <?php echo e(number_format((float) $lot->unit_cost, 4)); ?>/kg)
                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div>
                        <label for="charge_actual_qty">Actual quantity (kg) *</label>
                        <input id="charge_actual_qty" name="actual_qty_kg" type="number"
                               min="0.001" step="0.001" required value="<?php echo e(old('actual_qty_kg')); ?>"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div id="charge_transfer_cost_field" style="display:none">
                        <label for="charge_transfer_cost">Internal return transfer cost (PKR/kg) *</label>
                        <input id="charge_transfer_cost" name="transfer_cost_per_kg" type="number"
                               min="0.0001" step="0.0001" value="<?php echo e(old('transfer_cost_per_kg')); ?>"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="charge_consumed_at">Charge date and time *</label>
                        <input id="charge_consumed_at" name="consumed_at" type="datetime-local" required
                               value="<?php echo e(old('consumed_at', now()->format('Y-m-d\TH:i'))); ?>"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="charge_recorded_by">Recorded by *</label>
                        <input id="charge_recorded_by" name="recorded_by" required maxlength="100"
                               value="<?php echo e(old('recorded_by', $heat->operator_name)); ?>"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="charge_source_reference">Source reference</label>
                        <input id="charge_source_reference" name="source_reference" maxlength="100"
                               value="<?php echo e(old('source_reference')); ?>"
                               placeholder="Return batch, supplier reference..."
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                </div>
                <div style="margin-top:16px">
                    <label for="charge_notes">Notes</label>
                    <textarea id="charge_notes" name="notes" rows="2" maxlength="5000"
                              style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit"><?php echo e(old('notes')); ?></textarea>
                </div>
                <div class="actions" style="margin-top:16px">
                    <button class="btn btn-primary" type="submit">Record charge</button>
                </div>
            </form>
        <?php else: ?>
            <p class="muted">This heat is closed or cancelled; new charge entries are disabled.</p>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>Actual charge history</h2>
        <span class="muted small"><?php echo e($heat->materialConsumptions->count()); ?> entries</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Type</th><th>Material</th><th>Lot / reference</th>
                    <th>Quantity kg</th><th>Cost PKR/kg</th><th>Total cost PKR</th>
                    <th>Recorded at</th><th>Operator</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $heat->materialConsumptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $entryUnitCost = $entry->actual_qty_kg > 0
                            ? (float) $entry->actual_total_cost / (float) $entry->actual_qty_kg
                            : null;
                    ?>
                    <tr>
                        <td><?php echo e($entry->sequence); ?></td>
                        <td><?php echo e(str_replace('_', ' ', $entry->consumption_type)); ?></td>
                        <td><?php echo e($entry->material?->code); ?> — <?php echo e($entry->material?->name); ?></td>
                        <td><?php echo e($entry->materialLot?->lot_number ?? $entry->source_reference ?? '—'); ?></td>
                        <td><?php echo e(number_format((float) $entry->actual_qty_kg, 3)); ?></td>
                        <td><?php echo e($entryUnitCost !== null ? number_format($entryUnitCost, 4) : '—'); ?></td>
                        <td><?php echo e(number_format((float) $entry->actual_total_cost, 2)); ?></td>
                        <td><?php echo e($entry->consumed_at?->format('d M Y, H:i') ?? '—'); ?></td>
                        <td><?php echo e($entry->recorded_by); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="9" class="muted">No actual charge entries recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if(!in_array($heat->status, ['CLOSED', 'CANCELLED'], true)): ?>
    <div class="panel">
        <div class="panel-header">
            <h2>Post charge to inventory</h2>
        </div>
        <div class="panel-body">
            <?php if($chargeIsPosted): ?>
                <p style="color:var(--success)">
                    This heat's charge has already been posted.
                    Inventory deductions must not be repeated.
                </p>
                <p>
                    <strong>Posted charge cost:</strong>
                    PKR <?php echo e(number_format($actualChargeCost, 2)); ?>

                </p>
            <?php elseif($heat->materialConsumptions->isEmpty()): ?>
                <p class="muted">
                    Record the actual charge materials before posting.
                </p>
            <?php else: ?>
                <p>
                    Posting will deduct purchased materials from their selected
                    lots using FIFO cost layers and create inventory movements.
                    Internal returns will be costed but will not be deducted
                    from purchased-material inventory.
                </p>
                <p class="muted">
                    Verify the quantities, lots and transfer costs in the
                    charge history before proceeding. Posting should be
                    treated as an inventory transaction, not a draft save.
                </p>

                <form method="POST"
                      action="<?php echo e(route('heats.charge-postings.store', $heat)); ?>"
                      onsubmit="return confirm('Post this heat charge to inventory? This action deducts purchased-material stock and must not be repeated.');">
                    <?php echo csrf_field(); ?>

                    <div style="margin-bottom:16px">
                        <label for="charge_posting_notes">Posting notes</label>
                        <textarea
                            id="charge_posting_notes"
                            name="notes"
                            rows="2"
                            maxlength="5000"
                            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit"
                        ><?php echo e(old('notes')); ?></textarea>
                    </div>

                    <button class="btn btn-primary" type="submit">
                        Post charge to inventory
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const type = document.getElementById('charge_consumption_type');
    const material = document.getElementById('charge_material_id');
    const lot = document.getElementById('charge_material_lot_id');
    const lotField = document.getElementById('charge_lot_field');
    const transferField = document.getElementById('charge_transfer_cost_field');
    const transferCost = document.getElementById('charge_transfer_cost');

    function updateChargeFields() {
        const isReturn = type.value === 'INTERNAL_RETURN';
        lotField.style.display = isReturn ? 'none' : '';
        lot.disabled = isReturn;
        lot.required = !isReturn;
        transferField.style.display = isReturn ? '' : 'none';
        transferCost.required = isReturn;
        transferCost.disabled = !isReturn;

        Array.from(lot.options).forEach(function (option) {
            if (!option.value) return;
            option.hidden = material.value !== '' && option.dataset.materialId !== material.value;
        });

        const selected = lot.options[lot.selectedIndex];
        if (selected && selected.value && selected.hidden) lot.value = '';
    }

    type.addEventListener('change', updateChargeFields);
    material.addEventListener('change', updateChargeFields);
    updateChargeFields();
});
</script>

<div class="tab-marker" data-tab-start="Furnace readings"></div>
<?php if($heat->furnace?->furnace_type === 'INDUCTION'): ?>
    <div class="panel">
        <div class="panel-header"><h2>Record induction meter reading</h2></div>
        <div class="panel-body">
            <p class="muted">
                Enter the values shown on the meter. At least one meter value is required.
                Cumulative kWh is the meter total, not the energy used in this interval.
            </p>
            <form method="POST" action="<?php echo e(route('heats.power-readings.store', $heat)); ?>">
                <?php echo csrf_field(); ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:18px">
                    <div>
                        <label for="power_recorded_at">Reading date and time *</label>
                        <input id="power_recorded_at" name="recorded_at" type="datetime-local"
                               required value="<?php echo e(old('recorded_at', now()->format('Y-m-d\TH:i'))); ?>"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="actual_power_kw">Actual power (kW)</label>
                        <input id="actual_power_kw" name="actual_power_kw" type="number"
                               min="0" step="0.001" value="<?php echo e(old('actual_power_kw')); ?>"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="cumulative_energy_kwh">Cumulative energy (kWh)</label>
                        <input id="cumulative_energy_kwh" name="cumulative_energy_kwh" type="number"
                               min="0" step="0.001" value="<?php echo e(old('cumulative_energy_kwh')); ?>"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="power_recorded_by">Operator name *</label>
                        <input id="power_recorded_by" name="recorded_by" required maxlength="100"
                               value="<?php echo e(old('recorded_by', $heat->operator_name)); ?>"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px;margin-bottom:16px">
    <div>
        <label for="charge_posted_by">Posted by *</label>
        <input
            id="charge_posted_by"
            name="posted_by"
            required
            maxlength="100"
            value="<?php echo e(old('posted_by', $heat->operator_name)); ?>"
            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit"
        >
    </div>

    <div>
        <label for="charge_posting_notes">Posting notes</label>
        <textarea
            id="charge_posting_notes"
            name="notes"
            rows="2"
            maxlength="2000"
            style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit"
        ><?php echo e(old('notes')); ?></textarea>
    </div>
</div>
                <div class="actions" style="margin-top:18px">
                    <button class="btn btn-primary" type="submit">Save power reading</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if($heat->furnace?->furnace_type === 'CUPOLA'): ?>
    <div class="panel">
        <div class="panel-header"><h2>Record cupola process reading</h2></div>
        <div class="panel-body">
            <p class="muted">
                Use a consistent parameter code and unit for repeat readings.
                Examples might include blast pressure or temperature, but enter only parameters
                you actually measure at your furnace.
            </p>
            <form method="POST" action="<?php echo e(route('heats.process-readings.store', $heat)); ?>">
                <?php echo csrf_field(); ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:18px">
                    <div>
                        <label for="process_recorded_at">Reading date and time *</label>
                        <input id="process_recorded_at" name="recorded_at" type="datetime-local"
                               required value="<?php echo e(old('recorded_at', now()->format('Y-m-d\TH:i'))); ?>"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="parameter_code">Parameter code *</label>
                        <input id="parameter_code" name="parameter_code" required maxlength="60"
                               pattern="[A-Za-z0-9_.-]+"
                               placeholder="e.g. BLAST_PRESSURE"
                               value="<?php echo e(old('parameter_code')); ?>"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="numeric_value">Value *</label>
                        <input id="numeric_value" name="numeric_value" type="number" required
                               step="any" value="<?php echo e(old('numeric_value')); ?>"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="unit">Unit *</label>
                        <input id="unit" name="unit" required maxlength="30"
                               placeholder="e.g. mbar, °C, Hz"
                               value="<?php echo e(old('unit')); ?>"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                    <div>
                        <label for="process_recorded_by">Operator name *</label>
                        <input id="process_recorded_by" name="recorded_by" required maxlength="100"
                               value="<?php echo e(old('recorded_by', $heat->operator_name)); ?>"
                               style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit">
                    </div>
                </div>
                <div style="margin-top:18px">
                    <label for="process_notes">Notes</label>
                    <textarea id="process_notes" name="notes" rows="2" maxlength="5000"
                              style="display:block;width:100%;padding:11px;border:1px solid var(--border);border-radius:7px;font:inherit"><?php echo e(old('notes')); ?></textarea>
                </div>
                <div class="actions" style="margin-top:18px">
                    <button class="btn btn-primary" type="submit">Save process reading</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if($heat->furnace?->furnace_type === 'INDUCTION'): ?>
    <div class="panel">
        <div class="panel-header">
            <h2>Power reading history</h2>
            <span class="muted small"><?php echo e($heat->powerReadings->count()); ?> readings</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Recorded at</th>
                        <th>Actual power</th>
                        <th>Cumulative energy</th>
                        <th>Operator</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $heat->powerReadings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reading): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($reading->reading_number); ?></td>
                            <td><?php echo e($reading->recorded_at?->format('d M Y, H:i')); ?></td>
                            <td>
                                <?php echo e($reading->actual_power_kw !== null
                                    ? number_format((float) $reading->actual_power_kw, 3).' kW'
                                    : '—'); ?>

                            </td>
                            <td>
                                <?php echo e($reading->cumulative_energy_kwh !== null
                                    ? number_format((float) $reading->cumulative_energy_kwh, 3).' kWh'
                                    : '—'); ?>

                            </td>
                            <td><?php echo e($reading->recorded_by); ?></td>
                            <td><?php echo e($reading->notes ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="6" class="muted">No power readings recorded.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if($heat->furnace?->furnace_type === 'CUPOLA'): ?>
    <div class="panel">
        <div class="panel-header">
            <h2>Process reading history</h2>
            <span class="muted small"><?php echo e($heat->processReadings->count()); ?> readings</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Recorded at</th>
                        <th>Parameter</th>
                        <th>Value</th>
                        <th>Operator</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $heat->processReadings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reading): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr>
                            <td><?php echo e($reading->reading_number); ?></td>
                            <td><?php echo e($reading->recorded_at?->format('d M Y, H:i')); ?></td>
                            <td><?php echo e($reading->parameter_code); ?></td>
                            <td><?php echo e($reading->numeric_value); ?> <?php echo e($reading->unit); ?></td>
                            <td><?php echo e($reading->recorded_by); ?></td>
                            <td><?php echo e($reading->notes ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr><td colspan="6" class="muted">No process readings recorded.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<div class="tab-marker" data-tab-start="Files & history"></div>
<?php echo $__env->make('custom-fields._panel', ['entity' => 'heat', 'id' => $heat->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('attachments._panel', ['type' => 'heat', 'id' => $heat->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('audit._history', ['type' => 'Heat', 'id' => $heat->id], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/heats/show.blade.php ENDPATH**/ ?>