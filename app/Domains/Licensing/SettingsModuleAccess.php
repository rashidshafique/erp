<?php

namespace App\Domains\Licensing;

use App\Domains\Core\Contracts\ModuleAccess;
use App\Models\AppSetting;

/** Reads the licensed list from config/modules.php and the switched-off list from the app settings. */
class SettingsModuleAccess implements ModuleAccess
{
    public const DISABLED_SETTING = 'modules.disabled';

    /** @var list<string>|null */
    private ?array $disabled = null;

    public function isEnabled(string $name): bool
    {
        return $this->isLicensed($name) && ! in_array($name, $this->disabledModules(), true);
    }

    public function isLicensed(string $name): bool
    {
        $licensed = config('modules.licensed');

        return $licensed === null || in_array($name, $licensed, true);
    }

    /** @return list<string> */
    public function disabledModules(): array
    {
        if ($this->disabled === null) {
            try {
                $stored = json_decode((string) AppSetting::getValue(self::DISABLED_SETTING, '[]'), true);
            } catch (\Throwable) {
                return [];
            }
            $this->disabled = is_array($stored) ? array_values(array_filter($stored, 'is_string')) : [];
        }

        return $this->disabled;
    }

    /** @param list<string> $names */
    public function saveDisabled(array $names): void
    {
        $this->disabled = array_values(array_unique($names));
        AppSetting::setValue(self::DISABLED_SETTING, json_encode($this->disabled), 'Modules switched off');
    }
}
