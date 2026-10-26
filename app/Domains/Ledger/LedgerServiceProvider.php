<?php

namespace App\Domains\Ledger;

use App\Domains\Core\ModuleRegistry;
use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Finance\Events\DocumentPosted;
use App\Domains\Finance\Events\DocumentVoided;
use App\Domains\Ledger\Console\LedgerPostMissing;
use App\Domains\Ledger\Listeners\PostDocument;
use App\Domains\Ledger\Listeners\ReverseDocument;
use Illuminate\Support\Facades\Event;

/**
 * Double-entry books: chart of accounts, journals posted by every Finance document as it counts (and reversed when it is voided),
 * manual journals, trial balance and account ledgers. Finance does not know it is here; it only fires events.
 */
class LedgerServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'ledger';
    }

    protected function moduleLabel(): string
    {
        return 'General ledger';
    }

    protected function moduleDescription(): string
    {
        return 'Double-entry books posted from invoices, bills, receipts and payments, with manual journals and a trial balance. Switch it off if the books are kept in another package; journals already posted stay.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function boot(): void
    {
        parent::boot();
        if ($this->app->make(ModuleRegistry::class)->isEnabled($this->moduleName())) {
            Event::listen(DocumentPosted::class, PostDocument::class);
            Event::listen(DocumentVoided::class, ReverseDocument::class);
        }
    }

    protected function navGroups(): array
    {
        return [[
            'key' => 'ledger', 'label' => 'Accounts', 'icon' => 'book', 'active' => ['ledger.*'],
            'items' => [
                ['label' => 'Journals', 'route' => 'ledger.journals.index', 'icon' => 'file-text', 'active' => ['ledger.journals.*']],
                ['label' => 'Chart of accounts', 'route' => 'ledger.accounts.index', 'icon' => 'list', 'active' => ['ledger.accounts.*']],
                ['label' => 'Bank accounts', 'route' => 'ledger.banks.index', 'icon' => 'bank', 'active' => ['ledger.banks.*', 'ledger.reconciliations.*']],
                ['label' => 'Trial balance', 'route' => 'ledger.trial-balance', 'icon' => 'list-check', 'active' => ['ledger.trial-balance']],
                ['label' => 'Profit and loss', 'route' => 'ledger.profit-and-loss', 'icon' => 'trending-up', 'active' => ['ledger.profit-and-loss']],
                ['label' => 'Balance sheet', 'route' => 'ledger.balance-sheet', 'icon' => 'scale', 'active' => ['ledger.balance-sheet']],
                ['label' => 'General ledger', 'route' => 'ledger.general-ledger', 'icon' => 'book-open', 'active' => ['ledger.general-ledger']],
            ],
        ]];
    }

    protected function settingsLinks(): array
    {
        return [['section' => 'Foundry', 'label' => 'Ledger accounts', 'route' => 'ledger.settings', 'active' => ['ledger.settings*']]];
    }

    protected function permissionCatalog(): array
    {
        return ['ledger.manage' => 'Chart of accounts, journals, trial balance and ledger settings'];
    }

    protected function permissionRoutes(): array
    {
        return [['pattern' => 'ledger.*', 'permission' => 'ledger.manage']];
    }

    protected function roleDefaults(): array
    {
        return ['ACCOUNTS' => ['name' => 'Accounts', 'permissions' => ['finance.manage', 'ledger.manage', 'attachments.manage', 'audit.view']]];
    }

    protected function consoleCommands(): array
    {
        return [LedgerPostMissing::class];
    }

    protected function panels(): array
    {
        return ['finance.document' => 'ledger::_document-panel'];
    }
}
