<?php

namespace App\Domains\Sales;

use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Sales\Console\SalesRepeatDeliveries;
use App\Domains\Sales\Http\Controllers\SalesOrderController;
use App\Domains\Sales\Models\ContractReview;
use App\Domains\Sales\Models\ContractReviewItem;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderLine;
use App\Domains\Sales\Services\ContractReviewer;
use App\Domains\Sales\Services\SalesOrderStatus;
use Illuminate\Console\Scheduling\Schedule;

/** Sales orders and their contract review, from the customer's order to the production orders it releases. Customers stay core. */
class SalesServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'sales';
    }

    protected function moduleLabel(): string
    {
        return 'Sales orders';
    }

    protected function moduleDescription(): string
    {
        return 'Sales orders, contract review and the order trace. Customers, production orders and the melting plan stay.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    protected function classAliases(): array
    {
        return [
            'App\Models\SalesOrder' => SalesOrder::class,
            'App\Models\SalesOrderLine' => SalesOrderLine::class,
            'App\Models\ContractReview' => ContractReview::class,
            'App\Models\ContractReviewItem' => ContractReviewItem::class,
            'App\Services\Orders\SalesOrderStatus' => SalesOrderStatus::class,
            'App\Services\Orders\ContractReviewer' => ContractReviewer::class,
            'App\Http\Controllers\SalesOrderController' => SalesOrderController::class,
        ];
    }

    protected function navItems(): array
    {
        return [
            ['group' => 'sales', 'label' => 'Sales orders', 'route' => 'sales-orders.index', 'icon' => 'shopping-bag', 'active' => ['sales-orders.index', 'sales-orders.show', 'sales-orders.trace'],
                'count' => fn (): int => SalesOrder::whereIn('status', ['DRAFT', 'UNDER_REVIEW'])->count()],
            ['group' => 'sales', 'label' => 'Price lists', 'route' => 'price-lists.index', 'icon' => 'list', 'active' => ['price-lists.*']],
        ];
    }

    protected function panels(): array
    {
        return ['party.show' => 'sales::price-panel'];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'sales-orders.*', 'permission' => 'sales.manage'],
            ['pattern' => 'price-lists.*', 'permission' => 'sales.manage'],
        ];
    }

    protected function consoleCommands(): array
    {
        return [SalesRepeatDeliveries::class];
    }

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('sales:repeat-deliveries')->dailyAt('00:30');
    }

    protected function searchTypes(): array
    {
        return [
            'sales_order' => ['label' => 'Sales order', 'table' => 'sales_orders', 'code' => 'order_number', 'name' => 'customer_reference', 'route' => 'sales-orders.show', 'permission' => null, 'words' => ['so', 'order', 'orders', 'sales']],
        ];
    }
}
