<?php

namespace App\Domains\Compliance;

use App\Domains\Compliance\Models\RequirementProfile;
use App\Domains\Core\ModuleServiceProvider;

/** Requirement profiles: what each customer or standard demands, so stricter rules switch on per customer. */
class ComplianceServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'compliance';
    }

    protected function moduleLabel(): string
    {
        return 'Customer requirements';
    }

    protected function moduleDescription(): string
    {
        return 'Requirement profiles (IATF, Japanese, Korean, Chinese and Indian OEM) assigned to customers.';
    }

    protected function modulePath(): string
    {
        return __DIR__;
    }

    protected function settingsLinks(): array
    {
        return [
            ['section' => 'Foundry', 'label' => 'Requirement profiles', 'route' => 'requirements.index', 'active' => ['requirements.*']],
        ];
    }

    protected function attachables(): array
    {
        return [
            'requirement_profile' => ['label' => 'Requirement profile', 'model' => RequirementProfile::class, 'edit' => 'quality.manage'],
        ];
    }

    protected function permissionRoutes(): array
    {
        return [
            ['pattern' => 'requirements.*', 'permission' => 'quality.manage'],
            ['pattern' => 'customer-requirements.*', 'permission' => 'quality.manage'],
        ];
    }
}
