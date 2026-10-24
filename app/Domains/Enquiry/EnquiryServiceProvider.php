<?php

namespace App\Domains\Enquiry;

use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Enquiry\Models\Enquiry;
use App\Domains\Enquiry\Models\EnquiryItem;
use App\Domains\Enquiry\Models\Quotation;
use App\Domains\Enquiry\Models\QuotationLine;
use App\Domains\Enquiry\Services\EnquiryGuide;
use App\Domains\Enquiry\Services\QuotationService;
use App\Domains\Enquiry\Services\QuoteCalculator;
use App\Domains\Enquiry\Services\Rates;

/** Customer enquiries, the guided request wizard, feasibility, and the costed quotations that follow. */
class EnquiryServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'enquiry';
    }

    protected function moduleLabel(): string
    {
        return 'Enquiries & quotations';
    }

    protected function moduleDescription(): string
    {
        return 'Enquiries, the guided request wizard, costed quotations and the quotation rates. Patterns stop offering a quotation as the basis of ownership, and the dashboard drops the enquiry figures.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    public function register(): void
    {
        parent::register();
        $this->mergeConfigFrom(__DIR__.'/config/quotation.php', 'quotation');
        $this->mergeConfigFrom(__DIR__.'/config/enquiry_scenarios.php', 'enquiry_scenarios');
    }

    protected function classAliases(): array
    {
        return [
            'App\Models\Enquiry' => Enquiry::class,
            'App\Models\EnquiryItem' => EnquiryItem::class,
            'App\Models\Quotation' => Quotation::class,
            'App\Models\QuotationLine' => QuotationLine::class,
            'App\Services\Quotation\EnquiryGuide' => EnquiryGuide::class,
            'App\Services\Quotation\QuotationService' => QuotationService::class,
            'App\Services\Quotation\QuoteCalculator' => QuoteCalculator::class,
            'App\Services\Quotation\Rates' => Rates::class,
        ];
    }

    protected function navItems(): array
    {
        return [
            ['group' => 'sales', 'label' => 'Enquiries & quotes', 'route' => 'enquiries.index', 'icon' => 'file-text', 'active' => ['enquiries.index', 'enquiries.create', 'enquiries.show', 'enquiries.items.*', 'quotations.*'],
                'count' => fn (): int => Enquiry::whereIn('status', ['NEW', 'FEASIBILITY'])->count() + Quotation::where('status', 'DRAFT')->count()],
        ];
    }

    protected function settingsLinks(): array
    {
        return [
            ['section' => 'Foundry', 'label' => 'Quotation rates', 'route' => 'settings.quotation', 'active' => ['settings.quotation*']],
        ];
    }

    protected function attachables(): array
    {
        return [
            'enquiry' => ['label' => 'Enquiry', 'model' => Enquiry::class, 'edit' => 'sales.manage'],
        ];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'enquiries.*', 'permission' => 'sales.manage'],
            ['pattern' => 'quotations.*', 'permission' => 'sales.manage'],
        ];
    }

    protected function searchTypes(): array
    {
        return [
            'enquiry' => ['label' => 'Enquiry', 'table' => 'enquiries', 'code' => 'code', 'name' => 'title', 'route' => 'enquiries.show', 'permission' => null, 'words' => ['enq', 'enquiry', 'enquiries', 'rfq']],
            'quotation' => ['label' => 'Quotation', 'table' => 'quotations', 'code' => 'code', 'name' => null, 'route' => 'quotations.show', 'permission' => null, 'words' => ['quo', 'quote', 'quotation']],
        ];
    }

    protected function codeKinds(): array
    {
        return [
            'enquiry' => ['enquiries', 'ENQ', 7, 'Enquiry'],
            'quotation' => ['quotations', 'QUO', 8, 'Quotation'],
        ];
    }
}
