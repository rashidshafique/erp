<?php $__env->startSection('title', 'New casting analysis'); ?>
<?php $__env->startSection('breadcrumb'); ?>
<a href="<?php echo e(route('casting-analyses.index')); ?>">Casting analysis</a> / New
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="page-heading">
    <div>
        <h1>New casting analysis</h1>
        <p>Upload the part (the casting itself, not the mould). Takes a few seconds to a minute.</p>
    </div>
</div>

<?php if($errors->any()): ?>
    <div class="alert alert-error"><?php echo e($errors->first()); ?></div>
<?php endif; ?>

<form method="POST" action="<?php echo e(route('casting-analyses.store')); ?>" enctype="multipart/form-data" onsubmit="this.querySelector('button[type=submit]').disabled=true;this.querySelector('button[type=submit]').textContent='Analysing…'">
    <?php echo csrf_field(); ?>
    <?php if(! empty($item)): ?>
        <input type="hidden" name="enquiry_item_id" value="<?php echo e($item->id); ?>">
        <div class="alert alert-success">For enquiry <a href="<?php echo e(route('enquiries.show', $item->enquiry)); ?>"><?php echo e($item->enquiry->code); ?></a>, part <?php echo e($item->line_no); ?>: <?php echo e($item->description); ?>. The result is linked to the part when it finishes.</div>
    <?php endif; ?>
    <div class="panel">
        <div class="panel-header"><h2>Model</h2></div>
        <div class="panel-body">
            <div class="form-grid" data-progressive="3">
                <div class="field field-full">
                    <label for="cad">CAD file (.stl, .stp, .step)</label>
                    <input id="cad" type="file" name="cad" accept=".stl,.stp,.step">
                    <div class="help">STL is read directly. STEP needs the converter in <code>tools/step_to_stl.py</code> (pip install cadquery); if it is missing, export STL instead. Maximum <?php echo e(round(config('casting.max_upload_kb') / 1024)); ?> MB.</div>
                    <?php $__errorArgs = ['cad'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="error-text"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <?php if($cad->isNotEmpty()): ?>
                    <div class="field field-full">
                        <label for="attachment_id">…or use a file already attached to <?php echo e($product->code); ?></label>
                        <select id="attachment_id" name="attachment_id">
                            <option value="">— none —</option>
                            <?php $__currentLoopData = $cad; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($c->id); ?>" <?php if(old('attachment_id') === $c->id): echo 'selected'; endif; ?>><?php echo e($c->original_name); ?><?php echo e($c->title ? ' — '.$c->title : ''); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <?php $__errorArgs = ['attachment_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="error-text"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                <?php endif; ?>
                <div class="field">
                    <label for="name">Name</label>
                    <input id="name" name="name" value="<?php echo e(old('name')); ?>" placeholder="Defaults to the file name">
                </div>
                <div class="field">
                    <label for="product_id">Product (optional)</label>
                    <select id="product_id" name="product_id">
                        <option value="">— not linked —</option>
                        <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($p->id); ?>" <?php if(old('product_id', $product?->id) === $p->id): echo 'selected'; endif; ?>><?php echo e($p->code); ?> — <?php echo e($p->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <div class="help">Linking lets the weight be checked against the product's nominal weight, which catches wrong units.</div>
                </div>
                <div class="field">
                    <label for="units">Units in the file</label>
                    <select id="units" name="units">
                        <?php $__currentLoopData = ['mm' => 'Millimetres', 'cm' => 'Centimetres', 'm' => 'Metres', 'in' => 'Inches']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($k); ?>" <?php if(old('units', 'mm') === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <div class="help">STL files carry no units. If the weight in the result looks wrong, this is the usual cause.</div>
                </div>
                <div class="field">
                    <label for="quality">Quality</label>
                    <select id="quality" name="quality">
                        <?php $__currentLoopData = ['DRAFT' => 'Draft (fastest)', 'STANDARD' => 'Standard', 'FINE' => 'Fine (thin walls, slower)']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($k); ?>" <?php if(old('quality', 'STANDARD') === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Material and requirements</h2></div>
        <div class="panel-body">
            <div class="form-grid" data-progressive="3">
                <div class="field">
                    <label for="family">Iron family</label>
                    <select id="family" name="family">
                        <?php $__currentLoopData = ['AUTO' => 'Let the analyser choose', 'GREY' => 'Grey iron', 'DUCTILE' => 'Ductile (SG) iron']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($k); ?>" <?php if(old('family', 'AUTO') === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="field">
                    <label for="iron_grade_id">Iron grade (optional)</label>
                    <select id="iron_grade_id" name="iron_grade_id">
                        <option value="">— none —</option>
                        <?php $__currentLoopData = $grades; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($g->id); ?>" <?php if(old('iron_grade_id') === $g->id): echo 'selected'; endif; ?>><?php echo e($g->code); ?> — <?php echo e($g->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <div class="help">A grade fixes the family and limits the recommended chemistry to its range.</div>
                </div>
                <div class="field">
                    <label for="min_tensile">Minimum tensile strength (MPa)</label>
                    <input id="min_tensile" type="number" step="1" min="0" name="min_tensile" value="<?php echo e(old('min_tensile')); ?>">
                </div>
                <div class="field">
                    <label for="min_elongation">Minimum elongation (%)</label>
                    <input id="min_elongation" type="number" step="0.5" min="0" name="min_elongation" value="<?php echo e(old('min_elongation')); ?>">
                </div>
            </div>
            <details style="margin-top:14px">
                <summary class="small"><strong>Advanced</strong></summary>
                <div class="form-grid" style="margin-top:12px">
                    <div class="field">
                        <label for="feeder">Feeder type</label>
                        <select id="feeder" name="feeder">
                            <?php $__currentLoopData = ['AUTO' => 'Automatic', 'CONVENTIONAL' => 'Sand riser', 'INSULATED' => 'Insulating sleeve', 'EXOTHERMIC' => 'Exothermic sleeve']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $l): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($k); ?>" <?php if(old('feeder', 'AUTO') === $k): echo 'selected'; endif; ?>><?php echo e($l); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="pour_window_min">Minutes from Mg treatment to last mould</label>
                        <input id="pour_window_min" type="number" step="0.5" min="1" max="60" name="pour_window_min" value="<?php echo e(old('pour_window_min', 10)); ?>">
                        <div class="help">Used for Mg fade (0.001 % per minute in a pouring ladle).</div>
                    </div>
                    <div class="field">
                        <label for="b_override">Chvorinov constant B (min/cm²)</label>
                        <input id="b_override" type="number" step="0.05" min="0.3" max="6" name="b_override" value="<?php echo e(old('b_override')); ?>" placeholder="Default for the iron family">
                        <div class="help">Calibrate from your own pours to turn the freezing times into real minutes.</div>
                    </div>
                </div>
            </details>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit">Analyse</button>
        <a class="btn" href="<?php echo e(route('casting-analyses.index')); ?>">Cancel</a>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/casting-analyses/create.blade.php ENDPATH**/ ?>