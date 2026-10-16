<?php $__env->startSection('title', 'Display'); ?>
<?php $__env->startSection('content'); ?>
<div class="page-heading"><div><h1>Display</h1><p>How lists and pages behave. These apply to everyone.</p></div></div>
<form method="POST" action="<?php echo e(route('settings.display.update')); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
<div class="panel"><div class="panel-body">
<label style="display:flex;gap:10px;align-items:flex-start;margin-bottom:12px"><input type="checkbox" name="table_toolbar" value="1" <?php if($ui['toolbar']): echo 'checked'; endif; ?>>
<span><strong>Search and filters in the table header</strong><br><span class="muted small">Puts the search box and filters on the same line as the table title, so lists use less height. Turn off to keep them in a separate bar above the table.</span></span></label>
<label style="display:flex;gap:10px;align-items:flex-start;margin-bottom:16px"><input type="checkbox" name="tidy_forms" value="1" <?php if($ui['tidy_forms']): echo 'checked'; endif; ?>>
<span><strong>Keep long forms short</strong><br><span class="muted small">On long forms, optional fields that are still blank are tucked behind "Show more optional fields", so a form opens with only what is needed. Turn off to always show every field.</span></span></label>
<div class="field" style="max-width:260px"><label>Spacing of lists and tables</label><select name="density"><option value="COMPACT" <?php if($ui['compact']): echo 'selected'; endif; ?>>Compact</option><option value="COMFORTABLE" <?php if(! $ui['compact']): echo 'selected'; endif; ?>>Comfortable (roomier)</option></select></div>
<div class="field" style="max-width:260px"><label>Dashboard layout</label><select name="dashboard"><?php $__currentLoopData = ['classic' => 'Classic', 'command' => 'Command (one column plus side panel)', 'columns' => 'Columns (three columns)']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(\App\Support\UiSettings::dashboardLayout() === $k): echo 'selected'; endif; ?>><?php echo e($n); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><span class="muted small">The default for everyone. Anyone can switch on the dashboard itself; that choice is kept in their own browser.</span></div>
<p class="muted small">Clicking a table row to open it is switched on or off under <a href="<?php echo e(route('settings.edit')); ?>">Foundry settings → Screen behaviour</a>.</p>
</div></div>
<p><button class="btn btn-primary">Save</button></p></form>

<?php ($fontNow = \App\Support\UiSettings::font()); ?>
<?php if(\App\Support\UiSettings::canEditFont()): ?>
<form method="POST" action="<?php echo e(route('settings.display.font')); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
<div class="panel"><div class="panel-header"><h2>Font</h2></div><div class="panel-body">
<div class="form-grid">
<div class="field"><label>Font for the whole interface</label>
  <select name="font" id="ui-font" style="max-width:300px"><?php $__currentLoopData = \App\Support\UiSettings::FONTS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($k); ?>" <?php if(old('font', $fontNow['key']) === $k): echo 'selected'; endif; ?>><?php echo e($f['label']); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><option value="custom" <?php if(old('font', $fontNow['key']) === 'custom'): echo 'selected'; endif; ?>>Other: type a font name</option></select>
  <div class="help">Applies to every screen and every person. Web fonts load from Google Fonts, so the computers need internet; without it they show the system font instead.</div>
</div>
<div class="field" id="ui-font-custom" <?php if(old('font', $fontNow['key']) !== 'custom'): ?> hidden <?php endif; ?>><label>Font name</label><input name="custom_font" maxlength="60" value="<?php echo e(old('custom_font', $fontNow['name'])); ?>" placeholder="e.g. Calibri" style="max-width:260px"><div class="help">The font must be installed on every computer that uses the system.</div></div>
</div>
<div class="muted small" style="margin-top:6px">Only the administrator sees this setting.</div>
</div></div>
<p><button class="btn btn-primary">Save font</button></p></form>
<script>(function(){var s=document.getElementById('ui-font'),c=document.getElementById('ui-font-custom');if(s&&c)s.addEventListener('change',function(){c.hidden=s.value!=='custom';});})();</script>
<?php else: ?>
<p class="muted small">The font used across the interface (<?php echo e($fontNow['label']); ?>) is set by the administrator.</p>
<?php endif; ?>

<?php ($brand = \App\Support\UiSettings::brand()); ?>
<?php ($theme = \App\Support\UiSettings::theme()); ?>
<form method="POST" action="<?php echo e(route('settings.branding.update')); ?>" enctype="multipart/form-data"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
<div class="panel"><div class="panel-header"><h2>Colour and logo</h2></div><div class="panel-body">
<div class="form-grid">
<div class="field"><label>Accent colour</label>
  <div style="display:flex;gap:8px;align-items:center">
    <input type="color" id="accent-picker" value="<?php echo e($theme['accent']); ?>" style="width:48px;padding:1px;min-height:30px">
    <input name="accent" id="accent-hex" value="<?php echo e(old('accent', $theme['accent'])); ?>" maxlength="7" style="width:110px;font-family:monospace">
    <button type="button" class="btn btn-small" id="accent-reset">Reset to green</button>
  </div>
  <div class="help">Used for buttons, links, the active menu item and highlights on every screen. Light colours are refused because button text is white.</div>
  <div style="display:flex;gap:6px;margin-top:6px"><?php $__currentLoopData = ['#24945a' => 'Green', '#1f6feb' => 'Blue', '#a84a22' => 'Rust', '#7c3aed' => 'Violet', '#0f766e' => 'Teal', '#b45309' => 'Amber', '#be123c' => 'Crimson', '#334155' => 'Slate']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $hex => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><button type="button" class="swatch" data-hex="<?php echo e($hex); ?>" title="<?php echo e($name); ?>" style="width:24px;height:24px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 0 1px var(--border);background:<?php echo e($hex); ?>;cursor:pointer;padding:0;min-height:0"></button><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
  <div style="margin-top:8px"><button type="button" class="btn btn-primary btn-small">Sample button</button> <a href="#" onclick="return false">Sample link</a></div>
  <input type="hidden" name="reset_accent" value="0" id="reset-accent">
</div>
<div class="field"><label>Name shown top left</label><input name="brand_name" maxlength="40" value="<?php echo e(old('brand_name', $brand['name'])); ?>" required><div class="help">Shown in the sidebar only while no logo image is uploaded. With a logo, the image replaces the name (the name still appears on the sign-in page and printed quotations).</div></div>
<div class="field"><label>Logo</label><input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg">
  <div class="help">PNG, JPG, WEBP or SVG, up to 1 MB. A wide logo with a transparent background looks best.</div>
  <?php if($brand['logo']): ?><div style="margin-top:6px;display:flex;gap:10px;align-items:center"><img src="<?php echo e($brand['logo']); ?>" alt="Current logo" style="height:36px;max-width:160px;object-fit:contain;border:1px solid var(--border);padding:3px;border-radius:5px;min-height:0;width:auto"><label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="remove_logo" value="1"> Remove logo</label></div><?php endif; ?></div>
<div class="field"><label>Logo position</label><select name="logo_position"><option value="TOP_LEFT" <?php if($brand['position']==='TOP_LEFT'): echo 'selected'; endif; ?>>Top left (above the name)</option><option value="BOTTOM_LEFT" <?php if($brand['position']==='BOTTOM_LEFT'): echo 'selected'; endif; ?>>Bottom left</option></select></div>
<div class="field"><label>Logo height (px)</label><input type="number" name="logo_height" min="20" max="120" value="<?php echo e(old('logo_height', $brand['height'])); ?>"></div>
</div>
</div></div>
<p><button class="btn btn-primary">Save colour and logo</button></p></form>
<script>
(function () {
    var pick = document.getElementById('accent-picker'), hex = document.getElementById('accent-hex');
    function set(v) { hex.value = v; if (/^#[0-9a-f]{6}$/i.test(v)) { pick.value = v; document.documentElement.style.setProperty('--accent', v, 'important'); } }   // live preview of buttons and links
    pick.addEventListener('input', function () { set(pick.value); });
    hex.addEventListener('input', function () { if (/^#[0-9a-f]{6}$/i.test(hex.value)) set(hex.value); });
    document.querySelectorAll('.swatch').forEach(function (b) { b.addEventListener('click', function () { set(b.dataset.hex); }); });
    document.getElementById('accent-reset').addEventListener('click', function () { set('#24945a'); document.getElementById('reset-accent').value = '1'; });
})();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/settings/display.blade.php ENDPATH**/ ?>