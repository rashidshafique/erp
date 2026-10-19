<?php

use App\Domains\Capa\CapaServiceProvider;
use App\Domains\Compliance\ComplianceServiceProvider;
use App\Domains\Dispatch\DispatchServiceProvider;
use App\Domains\Documents\DocumentsServiceProvider;
use App\Domains\Enquiry\EnquiryServiceProvider;
use App\Domains\Hr\HrServiceProvider;
use App\Domains\Licensing\LicensingServiceProvider;
use App\Domains\Maintenance\MaintenanceServiceProvider;
use App\Domains\Parties\PartiesServiceProvider;
use App\Domains\Purchasing\PurchasingServiceProvider;
use App\Domains\Tooling\ToolingServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    LicensingServiceProvider::class,
    CapaServiceProvider::class,
    ComplianceServiceProvider::class,
    DispatchServiceProvider::class,
    DocumentsServiceProvider::class,
    EnquiryServiceProvider::class,
    HrServiceProvider::class,
    MaintenanceServiceProvider::class,
    PartiesServiceProvider::class,
    PurchasingServiceProvider::class,
    ToolingServiceProvider::class,
];
