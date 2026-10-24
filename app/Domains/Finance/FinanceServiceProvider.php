<?php

namespace App\Domains\Finance;

use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;

/** Invoicing and receivables: sales invoices from a dispatch, a sales order or on their own, credit notes, customer receipts and ageing. */
class FinanceServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'finance';
    }

    protected function moduleLabel(): string
    {
        return 'Invoicing & receivables';
    }

    protected function moduleDescription(): string
    {
        return 'Sales invoices and credit notes, customer receipts, statements and ageing. Switching it off hides them; the records stay.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    protected function navGroups(): array
    {
        return [[
            'key' => 'finance', 'label' => 'Finance', 'icon' => 'wallet', 'active' => ['invoices.*', 'receipts.*', 'receivables.*', 'finance.*'],
            'items' => [
                ['label' => 'Invoices', 'route' => 'invoices.index', 'icon' => 'file-text', 'active' => ['invoices.*']],
                ['label' => 'Receipts', 'route' => 'receipts.index', 'icon' => 'receipt', 'active' => ['receipts.*']],
                ['label' => 'Receivables', 'route' => 'receivables.index', 'icon' => 'clock', 'active' => ['receivables.*']],
            ],
        ]];
    }

    protected function settingsLinks(): array
    {
        return [['section' => 'Foundry', 'label' => 'Invoicing & tax', 'route' => 'finance.settings', 'active' => ['finance.*']]];
    }

    protected function permissionCatalog(): array
    {
        return ['finance.manage' => 'Invoices, receipts and receivables'];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'invoices.*', 'permission' => 'finance.manage'],
            ['pattern' => 'receipts.*', 'permission' => 'finance.manage'],
            ['pattern' => 'receivables.*', 'permission' => 'finance.manage'],
            ['pattern' => 'finance.*', 'permission' => 'finance.manage'],
        ];
    }

    protected function roleDefaults(): array
    {
        return ['ACCOUNTS' => ['name' => 'Accounts', 'permissions' => ['finance.manage', 'attachments.manage', 'audit.view']]];
    }

    protected function attachables(): array
    {
        return [
            'invoice' => ['label' => 'Invoice', 'model' => Invoice::class, 'edit' => 'finance.manage'],
            'receipt' => ['label' => 'Receipt', 'model' => Receipt::class, 'edit' => 'finance.manage'],
        ];
    }
}
