<?php

namespace App\Domains\Maintenance;

use App\Domains\Core\ModuleServiceProvider;
use App\Domains\Maintenance\Models\Gauge;
use App\Domains\Maintenance\Models\GaugeCalibration;
use App\Domains\Maintenance\Services\CalibrationStatus;
use App\Domains\Maintenance\Services\GaugeUsage;
use App\Domains\Maintenance\Support\CalibrationRules;

/** Gauges and calibration: the instrument register, its calibration history and the rules for using an instrument that is out of date. */
class MaintenanceServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'maintenance';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    protected function classAliases(): array
    {
        return [
            'App\Models\Gauge' => Gauge::class,
            'App\Models\GaugeCalibration' => GaugeCalibration::class,
            'App\Services\Quality\CalibrationStatus' => CalibrationStatus::class,
            'App\Services\Quality\GaugeUsage' => GaugeUsage::class,
            'App\Support\CalibrationRules' => CalibrationRules::class,
        ];
    }
}
