<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Label <?php echo e($code); ?></title>
<style>
body{font-family:system-ui,sans-serif;margin:20px;color:#111}
.bar{margin-bottom:14px;display:flex;gap:10px;align-items:center}
.label{width:90mm;border:1px solid #000;padding:4mm;border-radius:2mm}
.kind{font-size:9pt;text-transform:uppercase;letter-spacing:1px;color:#444}
.name{font-size:12pt;font-weight:700;margin:1mm 0}
.mark{margin-top:2mm;padding-top:2mm;border-top:1px dashed #000;font-size:9pt}.mark b{font-size:20pt;letter-spacing:3px;font-family:monospace}
svg{max-width:100%;height:auto}
@media print{.bar{display:none}body{margin:0}.label{border:1px solid #000}}
</style></head><body>
<div class="bar"><button onclick="window.print()">Print</button><a href="javascript:history.back()">Back</a>
<span style="color:#666;font-size:12px">Prints at 90 mm wide. Pick your label printer in the print dialog.</span></div>
<div class="label">
  <div class="kind"><?php echo e($kind); ?></div>
  <?php if($name): ?><div class="name"><?php echo e($name); ?></div><?php endif; ?>
  <?php if($sub): ?><div style="font-size:10pt"><?php echo e($sub); ?></div><?php endif; ?>
  <?php echo \App\Support\Code128::svg($code, 52, 2); ?>

  <?php if($mark): ?><div class="mark">Engraved mark: <b><?php echo e($mark); ?></b></div><?php endif; ?>
</div>
</body></html>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/labels/show.blade.php ENDPATH**/ ?>