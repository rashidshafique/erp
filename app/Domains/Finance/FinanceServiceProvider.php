<?php

namespace App\Domains\Finance;

use App\Domains\Core\Contracts\CreditControl;
use App\Domains\Core\ModuleRegistry;
use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Finance\Models\Cheque;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Receipt;
use App\Domains\Finance\Models\SupplierBill;
use App\Domains\Finance\Models\SupplierPayment;
use App\Domains\Finance\Models\WithholdingCertificate;
use App\Domains\Finance\Services\CreditLimits;

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
            'key' => 'finance', 'label' => 'Finance', 'icon' => 'banknote', 'active' => ['invoice-templates.*', 'withholding-certificates.*', 'receivables.*', 'bills.*', 'supplier-payments.*', 'payables.*', 'finance.*'],
            'items' => [
                ['label' => 'Invoice templates', 'route' => 'invoice-templates.index', 'icon' => 'file-text', 'active' => ['invoice-templates.*']],
                ['label' => 'Tax certificates', 'route' => 'withholding-certificates.index', 'icon' => 'stamp', 'active' => ['withholding-certificates.*']],
                ['label' => 'Receivables', 'route' => 'receivables.index', 'icon' => 'inbox', 'active' => ['receivables.*']],
                ['label' => 'Supplier bills', 'route' => 'bills.index', 'icon' => 'receipt', 'active' => ['bills.*'], 'count' => fn (): int => SupplierBill::where('status', 'DRAFT')->count()],
                ['label' => 'Supplier payments', 'route' => 'supplier-payments.index', 'icon' => 'arrow-up-circle', 'active' => ['supplier-payments.*']],
                ['label' => 'Payables', 'route' => 'payables.index', 'icon' => 'hourglass', 'active' => ['payables.*']],
                ['label' => 'Tax register', 'route' => 'finance.tax-register', 'icon' => 'percent', 'active' => ['finance.tax-register']],
                ['label' => 'Tax return', 'route' => 'finance.tax-return', 'icon' => 'scale', 'active' => ['finance.tax-return']],
            ],
        ]];
    }

    /** Invoices and receipts sit in the Sales & Planning menu. */
    protected function navItems(): array
    {
        return [
            ['group' => 'sales', 'label' => 'Invoices', 'route' => 'invoices.index', 'icon' => 'file-text', 'active' => ['invoices.*'], 'count' => fn (): int => Invoice::where('status', 'DRAFT')->count()],
            ['group' => 'sales', 'label' => 'Receipts', 'route' => 'receipts.index', 'icon' => 'arrow-down-circle', 'active' => ['receipts.*']],
            ['group' => 'sales', 'label' => 'Cheques received', 'route' => 'cheques.received', 'icon' => 'wallet', 'active' => ['cheques.received'],
                'count' => fn (): int => Cheque::open()->where('direction', 'RECEIVED')->where('cheque_date', '<=', today())->count()],
            ['group' => 'purchases', 'label' => 'Cheques issued', 'route' => 'cheques.issued', 'icon' => 'wallet', 'active' => ['cheques.issued'],
                'count' => fn (): int => Cheque::open()->where('direction', 'ISSUED')->where('cheque_date', '<=', today())->count()],
        ];
    }

    public function boot(): void
    {
        parent::boot();
        $this->app->singleton(\App\Domains\Finance\Services\InvoiceTemplates::class);
        if ($this->app->make(ModuleRegistry::class)->isEnabled('finance')) {
            $this->app->singleton(CreditControl::class, CreditLimits::class);
        }
    }

    protected function searchTypes(): array
    {
        return [
            'invoice' => ['label' => 'Invoice', 'table' => 'invoices', 'code' => 'invoice_number', 'name' => null, 'route' => 'invoices.show', 'permission' => null, 'words' => ['inv', 'invoice', 'invoices']],
            'supplier_bill' => ['label' => 'Supplier bill', 'table' => 'supplier_bills', 'code' => 'bill_number', 'name' => 'supplier_bill_number', 'route' => 'bills.show', 'permission' => null, 'words' => ['bill', 'bills']],
            'receipt' => ['label' => 'Receipt', 'table' => 'receipts', 'code' => 'receipt_number', 'name' => 'reference', 'route' => 'receipts.show', 'permission' => null, 'words' => ['receipt', 'receipts']],
            'supplier_payment' => ['label' => 'Supplier payment', 'table' => 'supplier_payments', 'code' => 'payment_number', 'name' => 'reference', 'route' => 'supplier-payments.show', 'permission' => null, 'words' => ['payment', 'payments']],
        ];
    }

    protected function consoleCommands(): array
    {
        return [\App\Domains\Finance\Console\NoticeDueCheques::class];
    }

    protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
    {
        $schedule->command('finance:due-cheques')->dailyAt('08:00');
    }

    protected function panels(): array
    {
        return ['party.show' => 'finance::credit-panel'];
    }

    protected function settingsLinks(): array
    {
        return [
            ['section' => 'Accounts', 'label' => 'Invoicing', 'route' => 'finance.settings', 'active' => ['finance.settings*']],
            ['section' => 'Accounts', 'label' => 'Tax & year end', 'route' => 'finance.tax-year', 'active' => ['finance.tax-year*', 'finance.tax-rates.*', 'tax.*', 'ledger.settings.closing']],
        ];
    }

    protected function permissionCatalog(): array
    {
        return ['finance.manage' => 'Invoices, receipts, supplier bills, payments and ageing'];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'invoices.*', 'permission' => 'finance.manage'],
            ['pattern' => 'invoice-templates.*', 'permission' => 'finance.manage'],
            ['pattern' => 'receipts.*', 'permission' => 'finance.manage'],
            ['pattern' => 'cheques.*', 'permission' => 'finance.manage'],
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
            'cheque' => ['label' => 'Cheque', 'model' => Cheque::class, 'edit' => 'finance.manage'],
            'supplier_bill' => ['label' => 'Supplier bill', 'model' => SupplierBill::class, 'edit' => 'finance.manage'],
            'supplier_payment' => ['label' => 'Supplier payment', 'model' => SupplierPayment::class, 'edit' => 'finance.manage'],
            'withholding_certificate' => ['label' => 'Tax certificate', 'model' => WithholdingCertificate::class, 'edit' => 'finance.manage'],
        ];
    }
}
