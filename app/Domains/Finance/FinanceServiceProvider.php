<?php

namespace App\Domains\Finance;

use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierPayment;
use App\Domains\Finance\Models\WithholdingCertificate;

/** Invoicing and receivables, bills and payables: sales invoices and credit notes, customer receipts, supplier bills, supplier payments and ageing both ways. */
class FinanceServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'finance';
    }

    protected function moduleLabel(): string
    {
        return 'Invoicing, bills & payments';
    }

    protected function moduleDescription(): string
    {
        return 'Sales invoices and credit notes, customer receipts, supplier bills and payments, statements and ageing. Switching it off hides them; the records stay.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    protected function navGroups(): array
    {
        return [[
            'key' => 'finance', 'label' => 'Finance', 'icon' => 'banknote', 'active' => ['invoices.*', 'receipts.*', 'withholding-certificates.*', 'receivables.*', 'bills.*', 'supplier-payments.*', 'payables.*', 'finance.*'],
            'items' => [
                ['label' => 'Invoices', 'route' => 'invoices.index', 'icon' => 'file-text', 'active' => ['invoices.*'], 'count' => fn (): int => Invoice::where('status', 'DRAFT')->count()],
                ['label' => 'Receipts', 'route' => 'receipts.index', 'icon' => 'arrow-down-circle', 'active' => ['receipts.*']],
                ['label' => 'Tax certificates', 'route' => 'withholding-certificates.index', 'icon' => 'stamp', 'active' => ['withholding-certificates.*']],
                ['label' => 'Receivables', 'route' => 'receivables.index', 'icon' => 'inbox', 'active' => ['receivables.*']],
                ['label' => 'Supplier bills', 'route' => 'bills.index', 'icon' => 'receipt', 'active' => ['bills.*'], 'count' => fn (): int => SupplierBill::where('status', 'DRAFT')->count()],
                ['label' => 'Supplier payments', 'route' => 'supplier-payments.index', 'icon' => 'arrow-up-circle', 'active' => ['supplier-payments.*']],
                ['label' => 'Payables', 'route' => 'payables.index', 'icon' => 'hourglass', 'active' => ['payables.*']],
                ['label' => 'Tax register', 'route' => 'finance.tax-register', 'icon' => 'percent', 'active' => ['finance.tax-register']],
            ],
        ]];
    }

    protected function settingsLinks(): array
    {
        return [['section' => 'Foundry', 'label' => 'Invoicing & tax', 'route' => 'finance.settings', 'active' => ['finance.*']]];
    }

    protected function permissionCatalog(): array
    {
        return ['finance.manage' => 'Invoices, receipts, supplier bills, payments and ageing'];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'invoices.*', 'permission' => 'finance.manage'],
            ['pattern' => 'receipts.*', 'permission' => 'finance.manage'],
            ['pattern' => 'withholding-certificates.*', 'permission' => 'finance.manage'],
            ['pattern' => 'receivables.*', 'permission' => 'finance.manage'],
            ['pattern' => 'bills.*', 'permission' => 'finance.manage'],
            ['pattern' => 'supplier-payments.*', 'permission' => 'finance.manage'],
            ['pattern' => 'payables.*', 'permission' => 'finance.manage'],
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
            'supplier_bill' => ['label' => 'Supplier bill', 'model' => SupplierBill::class, 'edit' => 'finance.manage'],
            'supplier_payment' => ['label' => 'Supplier payment', 'model' => SupplierPayment::class, 'edit' => 'finance.manage'],
            'withholding_certificate' => ['label' => 'Tax certificate', 'model' => WithholdingCertificate::class, 'edit' => 'finance.manage'],
        ];
    }
}
