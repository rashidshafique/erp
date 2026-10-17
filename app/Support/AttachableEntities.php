<?php

namespace App\Support;

use App\Models\CastingLot;
use App\Models\CoreBox;
use App\Models\CoreDefinition;
use App\Models\Dispatch;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Enquiry;
use App\Models\Equipment;
use App\Models\Furnace;
use App\Models\Heat;
use App\Models\HrPolicy;
use App\Models\IronGrade;
use App\Models\LeaveRequest;
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
use Illuminate\Database\Eloquent\Model;

/** Records that can carry files. Key is stored with the attachment; class is used to check the record exists. */
class AttachableEntities
{
    public const MAP = [
        'material' => ['Material', Material::class], 'furnace' => ['Furnace', Furnace::class], 'equipment' => ['Equipment', Equipment::class],
        'heat' => ['Heat', Heat::class], 'pattern' => ['Pattern', Pattern::class], 'pattern_revision' => ['Pattern revision', PatternRevision::class], 'product' => ['Product', Product::class],
        'sales_order' => ['Sales order', SalesOrder::class], 'moulding_run' => ['Moulding run', MouldingRun::class], 'pour' => ['Pour', Pour::class],
        'casting_lot' => ['Casting lot', CastingLot::class], 'ncr' => ['Nonconformance report', NonconformanceReport::class],
        'inspection' => ['Inspection', QualityInspection::class], 'thermal_test' => ['Thermal test', ThermalAnalysisTest::class],
        'packing' => ['Packing record', PackingRecord::class], 'dispatch' => ['Dispatch', Dispatch::class], 'iron_grade' => ['Iron grade', IronGrade::class],
        'core_box' => ['Core box', CoreBox::class], 'core_definition' => ['Core', CoreDefinition::class],
        'mould_configuration' => ['Mould configuration', MouldConfiguration::class],
        'enquiry' => ['Enquiry', Enquiry::class],
        'employee' => ['Employee', Employee::class],
        'employee_document' => ['Employee document', EmployeeDocument::class],
        'leave_request' => ['Leave request', LeaveRequest::class],
        'hr_policy' => ['HR policy', HrPolicy::class],
    ];

    public const CATEGORIES = ['CERTIFICATE' => 'Certificate', 'DRAWING' => 'Drawing', 'SPECIFICATION' => 'Specification', 'REPORT' => 'Test / inspection report', 'PHOTO' => 'Photo', 'CUSTOMER_DOCUMENT' => 'Customer document', 'ID_DOCUMENT' => 'ID / personal document', 'CONTRACT' => 'Contract / letter', 'OTHER' => 'Other'];

    public static function find(string $key, string $id): ?Model
    {
        $class = self::MAP[$key][1] ?? null;

        return $class ? $class::find($id) : null;
    }
}
