<?php

use App\Domains\Capa\CapaServiceProvider;
use App\Domains\Dispatch\DispatchServiceProvider;
use App\Domains\Enquiry\EnquiryServiceProvider;
use App\Domains\Hr\HrServiceProvider;
use App\Domains\Licensing\LicensingServiceProvider;
use App\Domains\Maintenance\MaintenanceServiceProvider;
use App\Domains\Tooling\ToolingServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    LicensingServiceProvider::class,
    CapaServiceProvider::class,
    DispatchServiceProvider::class,
    EnquiryServiceProvider::class,
    HrServiceProvider::class,
    MaintenanceServiceProvider::class,
    ToolingServiceProvider::class,
];
