<?php

use App\Domains\Capa\CapaServiceProvider;
use App\Domains\Cameras\CamerasServiceProvider;
use App\Domains\Compliance\ComplianceServiceProvider;
use App\Domains\Dispatch\DispatchServiceProvider;
use App\Domains\Documents\DocumentsServiceProvider;
use App\Domains\Engineering\EngineeringServiceProvider;
use App\Domains\Enquiry\EnquiryServiceProvider;
use App\Domains\Finance\FinanceServiceProvider;
use App\Domains\Hr\HrServiceProvider;
use App\Domains\Inventory\InventoryServiceProvider;
use App\Domains\Ledger\LedgerServiceProvider;
use App\Domains\Licensing\LicensingServiceProvider;
use App\Domains\Maintenance\MaintenanceServiceProvider;
use App\Domains\Parties\PartiesServiceProvider;
use App\Domains\QuickFlow\QuickFlowServiceProvider;
use App\Domains\Sales\SalesServiceProvider;
use App\Domains\Purchasing\PurchasingServiceProvider;
use App\Domains\Tax\TaxServiceProvider;
use App\Domains\Tooling\ToolingServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    LicensingServiceProvider::class,
    CapaServiceProvider::class,
    CamerasServiceProvider::class,
    ComplianceServiceProvider::class,
    SalesServiceProvider::class,
    DispatchServiceProvider::class,
    DocumentsServiceProvider::class,
    EngineeringServiceProvider::class,
    EnquiryServiceProvider::class,
    FinanceServiceProvider::class,
    LedgerServiceProvider::class,
    HrServiceProvider::class,
    MaintenanceServiceProvider::class,
    PartiesServiceProvider::class,
    QuickFlowServiceProvider::class,
    PurchasingServiceProvider::class,
    InventoryServiceProvider::class,
    ToolingServiceProvider::class,
    TaxServiceProvider::class,
];
