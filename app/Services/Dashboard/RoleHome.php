<?php

namespace App\Services\Dashboard;

use App\Domains\Dispatch\Models\Dispatch;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Purchasing\Models\PurchaseOrder;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\ApprovalRequest;
use App\Models\CastingLot;
use App\Models\Heat;
use App\Models\MouldingRun;
use App\Models\Pour;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * A home built from what the person's role may do: a block per permission they hold, each with a few counts that link to the list
 * behind them. Nobody sees the plant-wide dashboard unless they are an administrator; a role with no permissions gets a read-only look.
 */
class RoleHome
{
    /** @return list<array{title:string, tiles:list<array{label:string, value:int, tone:string, url:?string}>}> */
    public function sections(User $user): array
    {
        $can = fn (string $p): bool => $user->hasPermission($p);
        $sections = [];
        $add = function (string $title, array $tiles) use (&$sections): void {
            $tiles = array_values(array_filter($tiles));
            if ($tiles) {
                $sections[] = ['title' => $title, 'tiles' => $tiles];
            }
        };

        if ($can('approvals.decide')) {
            $add('Waiting for your decision', [$this->tile('Approval requests', ApprovalRequest::where('status', 'PENDING')->where('requested_by_id', '!=', $user->id)->count(), 'warn', 'approvals.index')]);
        }
        if ($can('sales.manage')) {
            $add('Sales', [
                $this->tile('Open orders', SalesOrder::whereIn('status', SalesOrder::OPEN)->count(), 'plain', 'sales-orders.index'),
                $this->tile('Under review', SalesOrder::where('status', 'UNDER_REVIEW')->count(), 'warn', 'sales-orders.index'),
                $this->tile('On hold', SalesOrder::where('status', 'ON_HOLD')->count(), 'bad', 'sales-orders.index'),
            ]);
        }
        if ($can('planning.manage')) {
            $add('Planning', [
                $this->tile('Open production orders', ProductionOrder::open()->count(), 'plain', 'production-orders.index'),
                $this->tile('Late (past due date)', ProductionOrder::open()->whereDate('required_date', '<', today())->count(), 'bad', 'planner.index'),
            ]);
        }
        if ($can('melting.operate')) {
            $add('Melt shop', [
                $this->tile('Heats running', Heat::where('status', 'IN_PROGRESS')->count(), 'plain', 'heats.index'),
                $this->tile('Planned today', Heat::where('status', 'PLANNED')->whereDate('planned_start_at', today())->count(), 'plain', 'heats.index'),
            ]);
        }
        if ($can('moulding.operate') || $can('pouring.operate')) {
            $add('Moulding and pouring', [
                $can('moulding.operate') ? $this->tile('Moulding runs today', MouldingRun::whereDate('run_date', today())->count(), 'plain', 'moulding-runs.index') : null,
                $can('pouring.operate') ? $this->tile('Pours today', Pour::whereDate('created_at', today())->count(), 'plain', 'pours.index') : null,
            ]);
        }
        if ($can('shopfloor.record')) {
            $add('Shop floor', [$this->tile('Lots in production', CastingLot::where('status', 'IN_PRODUCTION')->count(), 'plain', 'lots.queue'), $this->tile('Lots on hold', CastingLot::where('status', 'ON_HOLD')->count(), 'bad', 'lots.queue')]);
        }
        if ($can('dispatch.operate') || $can('packing.operate')) {
            $add('Packing and dispatch', [$this->tile('Dispatches not yet sent', Dispatch::whereNull('dispatched_at')->count(), 'plain', 'dispatches.index')]);
        }
        if ($can('stores.manage')) {
            $add('Stores', [$this->tile('Stock balances', 0, 'link', 'stock-balances.index'), $this->tile('Materials to reorder', 0, 'link', 'stock-reorder.index')]);
        }
        if ($can('purchasing.manage')) {
            $add('Purchasing', [$this->tile('Orders waiting for goods', PurchaseOrder::whereIn('status', PurchaseOrder::RECEIVING)->count(), 'plain', 'purchase-orders.index'), $this->tile('Draft orders', PurchaseOrder::where('status', 'DRAFT')->count(), 'warn', 'purchase-orders.index')]);
        }
        if ($can('finance.manage')) {
            $add('Money in and out', [
                $this->tile('Overdue invoices', Invoice::where('status', 'ISSUED')->whereDate('due_date', '<', today())->get()->filter->isOverdue()->count(), 'bad', 'receivables.index'),
                $this->tile('Overdue supplier bills', SupplierBill::where('status', 'POSTED')->whereDate('due_date', '<', today())->get()->filter->isOverdue()->count(), 'bad', 'payables.index'),
                $this->tile('Draft invoices', Invoice::where('status', 'DRAFT')->count(), 'warn', 'invoices.index'),
            ]);
        }
        if (! $sections) {
            $add('The plant today', [
                $this->tile('Open sales orders', SalesOrder::whereIn('status', SalesOrder::OPEN)->count(), 'plain', 'sales-orders.index'),
                $this->tile('Lots in production', CastingLot::where('status', 'IN_PRODUCTION')->count(), 'plain', 'lots.index'),
                $this->tile('Heats running', Heat::where('status', 'IN_PROGRESS')->count(), 'plain', 'heats.index'),
            ]);
        }

        return $sections;
    }

    /** @return array{label:string, value:int, tone:string, url:?string}|null */
    private function tile(string $label, int $value, string $tone, string $route): ?array
    {
        return Route::has($route) ? ['label' => $label, 'value' => $value, 'tone' => $value === 0 && $tone !== 'link' ? 'plain' : $tone, 'url' => route($route)] : null;
    }
}
