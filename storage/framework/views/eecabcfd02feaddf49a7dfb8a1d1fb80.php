<?php
    $vals = array_values($vals ?? []);
    $w = 84; $h = 26; $n = count($vals); $max = max(array_merge($vals, [0.0001])); $min = min(array_merge($vals, [$max]));
    $span = ($max - $min) ?: 1; $pts = [];
    foreach ($vals as $i => $v) { $pts[] = round($n > 1 ? $i * $w / ($n - 1) : 0, 1).','.round($h - 3 - ($v - $min) / $span * ($h - 6), 1); }
?>
<?php if($n > 1): ?><svg width="<?php echo e($w); ?>" height="<?php echo e($h); ?>" viewBox="0 0 <?php echo e($w); ?> <?php echo e($h); ?>" aria-hidden="true"><polyline points="<?php echo e(implode(' ', $pts)); ?>" fill="none" stroke="<?php echo e($color ?? 'var(--accent)'); ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><?php endif; ?>
<?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/dashboard/_spark.blade.php ENDPATH**/ ?>