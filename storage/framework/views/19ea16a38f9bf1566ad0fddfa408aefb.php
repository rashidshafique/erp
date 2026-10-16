<?php $__env->startSection('title', 'Dashboard'); ?>
<?php $__env->startSection('breadcrumb', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>
    <div class="page-heading">
        <div>
            <h1>Foundry overview</h1>
            <p>Production status and quick access to shop-floor operations.</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <span class="muted small">Layout</span>
            <?php $__currentLoopData = ['classic' => 'Classic', 'command' => 'Command', 'columns' => 'Columns']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e(route('dashboard', ['layout' => $k])); ?>" class="btn btn-small <?php echo e($k === 'classic' ? 'btn-primary' : ''); ?>"><?php echo e($name); ?></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route('production-orders.create')); ?>" class="btn btn-primary">+ New production order</a>
        </div>
    </div>

    <?php
        $u = auth()->user();
        $waiting = \App\Models\ApprovalRequest::where('status', 'PENDING')->when($u && ! $u->isAdmin(), fn ($q) => $q->where('requested_by_id', '!=', $u->id)->whereIn('permission', $u->permissions()))->count();
        $ready = $u ? \App\Models\ApprovalRequest::where('status', 'APPROVED')->where('requested_by_id', $u->id)->count() : \App\Models\ApprovalRequest::where('status', 'APPROVED')->count();
    ?>
    <?php if($waiting || $ready): ?>
        <div class="alert alert-success" style="border-color:#e6c36a;background:#fff8e1;color:#7a5b00">
            <?php if($waiting): ?><strong><?php echo e($waiting); ?></strong> approval<?php echo e($waiting === 1 ? '' : 's'); ?> waiting for a decision.<?php endif; ?>
            <?php if($ready): ?><strong><?php echo e($ready); ?></strong> approved — repeat the action to go ahead.<?php endif; ?>
            <a href="<?php echo e(route('approvals.index')); ?>">Open approvals →</a>
        </div>
    <?php endif; ?>

    <div class="metrics">
        <div class="metric">
            <div class="metric-label">Open orders</div>
            <div class="metric-value"><?php echo e(number_format($openOrders)); ?></div>
            <div class="metric-note">Not completed or cancelled</div>
        </div>

        <div class="metric">
            <div class="metric-label">Released orders</div>
            <div class="metric-value"><?php echo e(number_format($releasedOrders)); ?></div>
            <div class="metric-note">Released for production</div>
        </div>

        <div class="metric">
            <div class="metric-label">Orders in progress</div>
            <div class="metric-value"><?php echo e(number_format($inProgressOrders)); ?></div>
            <div class="metric-note">Currently marked in progress</div>
        </div>

        <div class="metric">
            <div class="metric-label">Completed today</div>
            <div class="metric-value"><?php echo e(number_format($completedToday)); ?></div>
            <div class="metric-note">Orders completed today</div>
        </div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h2>Production pipeline</h2>
            <span class="muted small">Order-to-dispatch workflow</span>
        </div>

        <div class="panel-body">
            <div class="detail-grid">
                <?php $__currentLoopData = [
                    ['Production orders', 'Plan and release orders', route('production-orders.index')],
                    ['Moulding runs', 'Record mould production', route('moulding-runs.index')],
                    ['Pouring', 'Track furnace and pour activity', null],
                    ['Shot blasting', 'Track castings through cleaning', null],
                    ['Quality control', 'Record inspection outcomes', null],
                    ['Dispatch', 'Track finished order dispatch', null],
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $description, $url]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div style="padding:14px;border:1px solid var(--border);border-radius:8px">
                        <div class="detail-value">
                            <?php if($url): ?>
                                <a href="<?php echo e($url); ?>"><?php echo e($label); ?></a>
                            <?php else: ?>
                                <?php echo e($label); ?>

                            <?php endif; ?>
                        </div>
                        <div class="muted small" style="margin-top:5px"><?php echo e($description); ?></div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Recent production orders</h2>
            <a href="<?php echo e(route('production-orders.index')); ?>">View all</a>
        </div>

        <?php if($recentOrders->isNotEmpty()): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Order</th>
                        <th>Product</th>
                        <th>Planned quantity</th>
                        <th>Required date</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $__currentLoopData = $recentOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><strong><?php echo e($order->production_order_number); ?></strong></td>
                            <td><?php echo e($order->product?->name ?? '—'); ?></td>
                            <td>
                                <?php echo e(number_format((float) $order->planned_qty, 3)); ?>

                                <?php echo e($order->uom); ?>

                            </td>
                            <td><?php echo e($order->required_date?->format('d M Y') ?? '—'); ?></td>
                            <td>
                                <span class="status status-<?php echo e(strtolower(str_replace('_', '-', $order->status))); ?>">
                                    <?php echo e(str_replace('_', ' ', $order->status)); ?>

                                </span>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <strong>No production orders yet.</strong>
                <p>Create a production order to begin recording work.</p>
                <a href="<?php echo e(route('production-orders.create')); ?>" class="btn btn-primary">
                    Create production order
                </a>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Quick access</h2>
        </div>

        <div class="panel-body">
            <div class="detail-grid">
                <div>
                    <div class="detail-value">
                        <a href="<?php echo e(route('production-orders.index')); ?>">Production orders</a>
                    </div>
                    <div class="muted small">Plan and monitor manufacturing orders.</div>
                </div>

                <div>
                    <div class="detail-value">
                        <a href="<?php echo e(route('moulding-runs.index')); ?>">Moulding records</a>
                    </div>
                    <div class="muted small">Record moulds made, damage, operators and downtime.</div>
                </div>

                <div>
                    <div class="detail-value">
                        <a href="<?php echo e(route('tooling-maintenance.dashboard')); ?>">Maintenance</a>
                    </div>
                    <div class="muted small">Review tooling maintenance activity.</div>
                </div>

                <div>
                    <div class="detail-value">
                        <a href="<?php echo e(route('tooling-life.dashboard')); ?>">Tooling life</a>
                    </div>
                    <div class="muted small">Review tooling life limits.</div>
                </div>
            </div>
        </div>
    </section>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/rashid/sites/c-foundry-erp4/resources/views/dashboard/classic.blade.php ENDPATH**/ ?>