<?php

use App\Domains\Hr\HrServiceProvider;
use App\Domains\Licensing\LicensingServiceProvider;
use App\Domains\Maintenance\MaintenanceServiceProvider;
use App\Domains\Tooling\ToolingServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    LicensingServiceProvider::class,
    HrServiceProvider::class,
    MaintenanceServiceProvider::class,
    ToolingServiceProvider::class,
];
