<?php

use App\Domains\Capa\CapaServiceProvider;
use App\Domains\Dispatch\DispatchServiceProvider;
use App\Domains\Documents\DocumentsServiceProvider;
use App\Domains\Enquiry\EnquiryServiceProvider;
use App\Domains\Hr\HrServiceProvider;
use App\Domains\Licensing\LicensingServiceProvider;
use App\Domains\Maintenance\MaintenanceServiceProvider;
use App\Domains\Purchasing\PurchasingServiceProvider;
use App\Domains\Tooling\ToolingServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    LicensingServiceProvider::class,
    CapaServiceProvider::class,
    DispatchServiceProvider::class,
    DocumentsServiceProvider::class,
    EnquiryServiceProvider::class,
    HrServiceProvider::class,
    MaintenanceServiceProvider::class,
    PurchasingServiceProvider::class,
    ToolingServiceProvider::class,
];
