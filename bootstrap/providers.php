<?php

use App\Domains\Maintenance\MaintenanceServiceProvider;
use App\Domains\Tooling\ToolingServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    MaintenanceServiceProvider::class,
    ToolingServiceProvider::class,
];
