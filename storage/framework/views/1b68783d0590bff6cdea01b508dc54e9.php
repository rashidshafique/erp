<?php $__env->startSection('title', "Maintenance Dashboard"); ?>
<?php $__env->startSection('breadcrumb', "Maintenance Dashboard"); ?>

<?php $__env->startPush('styles'); ?>
<style>
.legacy-page .links { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
.legacy-page a { color: var(--accent); }
.legacy-page .btn { display: inline-block; padding: 10px 14px; background: var(--accent); color: white; text-decoration: none; border-radius: 6px; }
.legacy-page .cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.legacy-page .card,
.legacy-page .panel { background: white; border: 1px solid #dfe5eb; border-radius: 10px; padding: 20px; margin-bottom: 18px; }
.legacy-page .label { color: #687586; font-size: 13px; }
.legacy-page .number { font-size: 30px; font-weight: 750; margin-top: 7px; overflow-wrap: anywhere; }
.legacy-page .table-wrap { overflow-x: auto; }
.legacy-page table { width: 100%; border-collapse: collapse; min-width: 750px; }
.legacy-page th,
.legacy-page td { text-align: left; padding: 11px; border-bottom: 1px solid #e7ebf0; font-size: 13px; }
.legacy-page th { background: #f8fafc; }
@media(max-width: 700px) {
.legacy-page .cards { grid-template-columns: repeat(2, minmax(0,1fr)); }
.legacy-page .number { font-size: 24px; }
}
@media(max-width: 420px) {
.legacy-page .cards { grid-template-columns: 1fr; }
}
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="legacy-page">
<div class="page-heading"><div class="legacy-header">
<h1>Tooling Maintenance Dashboard</h1>
    <div>Foundry ERP · Maintenance control</div>
</div></div>
<div class="legacy-main">
    <nav class="links">
        <a class="btn" href="<?php echo e(route('tooling-maintenance.index')); ?>">Maintenance register</a>
        <a class="btn" href="<?php echo e(route('tooling-maintenance.create')); ?>">+ New record</a>
    </nav>

    <section class="cards">
        <?php $__currentLoopData = [
            ['Active jobs', $openCount],
            ['Urgent active jobs', $urgentCount],
            ['Active breakdowns', $breakdownCount],
            ['Overdue inspections', $overdueCount],
            ['Recorded downtime (hours)', number_format($downtimeHours, 2)],
            ['All-time maintenance spend (PKR)', number_format($maintenanceSpend, 2)],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="card">
                <div class="label"><?php echo e($label); ?></div>
                <div class="number"><?php echo e($value); ?></div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </section>

    <section class="panel">
        <h2>Active maintenance jobs</h2>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Record</th>
                    <th>Tooling</th>
                    <th>Activity</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Reported</th>
                    <th>Downtime (h)</th>
                </tr>
                </thead>
                <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $recentActive; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $record): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $item = $record->pattern?->name
                            ?? $record->patternRevision?->revision
                            ?? $record->coreDefinition?->name
                            ?? $record->coreBox?->name
                            ?? $record->mouldConfiguration?->name
                            ?? $record->mouldAssembly?->assembly_number
                            ?? 'Tooling item unavailable';
                    ?>
                    <tr>
                        <td><a href="<?php echo e(route('tooling-maintenance.show', $record)); ?>"><?php echo e($record->record_number); ?></a></td>
                        <td><?php echo e($item); ?></td>
                        <td><?php echo e($record->activity_type); ?></td>
                        <td><?php echo e($record->priority); ?></td>
                        <td><?php echo e($record->status); ?></td>
                        <td><?php echo e($record->reported_at?->format('d M Y H:i') ?? '—'); ?></td>
                        <td><?php echo e(number_format((float) $record->downtime_hours, 2)); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="7">No active maintenance jobs.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/tooling-maintenance/dashboard.blade.php ENDPATH**/ ?>