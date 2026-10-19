<?php

namespace App\Support;

use App\Domains\Core\ModuleRegistry;
use App\Models\CastingLot;
use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\Dispatch;
use App\Models\Enquiry;
use App\Models\Equipment;
use App\Models\Flask;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\IronGrade;
use App\Models\Material;
use App\Models\MouldConfiguration;
use App\Models\MouldingRun;
use App\Models\NonconformanceReport;
use App\Models\PackingRecord;
use App\Models\Pattern;
use App\Models\PatternRevision;
use App\Models\Pour;
use App\Models\Product;
use App\Models\QualityInspection;
use App\Models\SalesOrder;
use App\Models\ThermalAnalysisTest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Records that can carry files. Key is stored with the attachment; class is used to check the record exists. */
class AttachableEntities
{
    private const CORE_MAP = [
        'material' => ['Material', Material::class], 'furnace' => ['Furnace', Furnace::class], 'flask' => ['Flask', Flask::class], 'equipment' => ['Equipment', Equipment::class], 'capa' => ['Corrective action', CapaAction::class],
        'heat' => ['Heat', Heat::class], 'pattern' => ['Pattern', Pattern::class], 'pattern_revision' => ['Pattern revision', PatternRevision::class], 'product' => ['Product', Product::class],
        'sales_order' => ['Sales order', SalesOrder::class], 'moulding_run' => ['Moulding run', MouldingRun::class], 'pour' => ['Pour', Pour::class],
        'casting_lot' => ['Casting lot', CastingLot::class], 'ncr' => ['Nonconformance report', NonconformanceReport::class],
        'inspection' => ['Inspection', QualityInspection::class], 'thermal_test' => ['Thermal test', ThermalAnalysisTest::class],
        'packing' => ['Packing record', PackingRecord::class], 'dispatch' => ['Dispatch', Dispatch::class], 'iron_grade' => ['Iron grade', IronGrade::class],
        'core_box' => ['Core box', CoreBox::class], 'core_definition' => ['Core', CoreDefinition::class],
        'mould_configuration' => ['Mould configuration', MouldConfiguration::class],
        'enquiry' => ['Enquiry', Enquiry::class],
    ];

    public const CATEGORIES = ['CERTIFICATE' => 'Certificate', 'DRAWING' => 'Drawing', 'SPECIFICATION' => 'Specification', 'REPORT' => 'Test / inspection report', 'PHOTO' => 'Photo', 'CUSTOMER_DOCUMENT' => 'Customer document', 'ID_DOCUMENT' => 'ID / personal document', 'CONTRACT' => 'Contract / letter', 'OTHER' => 'Other'];

    /**
     * Who may touch each record's files: [permission to add/remove files, permission to read them or null when any
     * signed-in user may]. Photos stay readable wherever the record is shown (staff directory, org chart).
     *
     * @var array<string, array{0: string, 1: string|null}>
     */
    private const CORE_ACCESS = [
        'material' => ['stores.manage', null], 'furnace' => ['melting.operate', null], 'flask' => ['tooling.manage', null], 'equipment' => ['tooling.manage', null],
        'heat' => ['melting.operate', null], 'pattern' => ['master.manage', null], 'pattern_revision' => ['master.manage', null], 'product' => ['master.manage', null],
        'sales_order' => ['sales.manage', null], 'moulding_run' => ['moulding.operate', null], 'pour' => ['pouring.operate', null],
        'casting_lot' => ['shopfloor.record', null], 'ncr' => ['quality.manage', null],
        'inspection' => ['quality.inspect', null], 'thermal_test' => ['melting.operate', null],
        'packing' => ['packing.operate', null], 'dispatch' => ['dispatch.operate', null], 'iron_grade' => ['quality.manage', null],
        'core_box' => ['tooling.manage', null], 'core_definition' => ['tooling.manage', null],
        'mould_configuration' => ['tooling.manage', null],
        'enquiry' => ['sales.manage', null],
    ];

    /** Categories that need documents.sensitive on top of the record's own permission. */
    public const SENSITIVE_CATEGORIES = ['ID_DOCUMENT', 'CONTRACT'];

    /**
     * Every record that can carry files: the core ones plus those contributed by modules.
     *
     * @return array<string, array{0: string, 1: class-string}>
     */
    public static function map(): array
    {
        $fromModules = array_map(fn (array $e) => [$e[0], $e[1]], app(ModuleRegistry::class)->attachables());

        return self::CORE_MAP + $fromModules;
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function access(): array
    {
        $fromModules = array_map(fn (array $e) => [$e[2], $e[3]], app(ModuleRegistry::class)->attachables());

        return self::CORE_ACCESS + $fromModules;
    }

    public static function isSensitive(string $category): bool
    {
        return in_array($category, self::SENSITIVE_CATEGORIES, true);
    }

    /** Everything is open while login is off, as with the rest of the app. */
    public static function canView(?User $user, string $key, string $category): bool
    {
        if (! config('foundry.require_login')) {
            return true;
        }
        if (! $user) {
            return false;
        }
        if (self::isSensitive($category)) {
            return $user->hasPermission('documents.sensitive');
        }
        $permission = self::access()[$key][1] ?? null;

        return $permission === null || $category === 'PHOTO' || $user->hasPermission($permission);
    }

    /** Adding, removing or choosing the main photo. Needs attachments.manage and the record's own permission. Pass a category to check it too. */
    public static function canEdit(?User $user, string $key, ?string $category = null): bool
    {
        if (! config('foundry.require_login')) {
            return true;
        }
        if (! $user || ! $user->hasPermission('attachments.manage')) {
            return false;
        }
        $permission = self::access()[$key][0] ?? null;
        if ($permission === null ? ! $user->isAdmin() : ! $user->hasPermission($permission)) {
            return false;
        }

        return $category === null || ! self::isSensitive($category) || $user->hasPermission('documents.sensitive');
    }

    /** A record's name for lists: its code, or its name. */
    public static function describe(string $key, string $id): string
    {
        $label = self::map()[$key][0] ?? $key;
        $record = self::find($key, $id);
        $name = $record ? collect(['code', 'lot_number', 'heat_number', 'pour_number', 'order_number', 'name'])->map(fn ($c) => $record->getAttribute($c))->filter()->first() : null;

        return $name ? $label.' '.$name : $label;
    }

    public static function find(string $key, string $id): ?Model
    {
        $class = self::map()[$key][1] ?? null;

        return $class ? $class::find($id) : null;
    }
}
