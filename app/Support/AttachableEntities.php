<?php

namespace App\Support;

/** Records that can carry files. Key is stored with the attachment; class is used to check the record exists. */
class AttachableEntities
{
    public const MAP = [
        'heat' => ['Heat', \App\Models\Heat::class], 'pattern' => ['Pattern', \App\Models\Pattern::class], 'product' => ['Product', \App\Models\Product::class],
        'sales_order' => ['Sales order', \App\Models\SalesOrder::class], 'moulding_run' => ['Moulding run', \App\Models\MouldingRun::class], 'pour' => ['Pour', \App\Models\Pour::class],
        'casting_lot' => ['Casting lot', \App\Models\CastingLot::class], 'ncr' => ['Nonconformance report', \App\Models\NonconformanceReport::class],
        'inspection' => ['Inspection', \App\Models\QualityInspection::class], 'thermal_test' => ['Thermal test', \App\Models\ThermalAnalysisTest::class],
        'packing' => ['Packing record', \App\Models\PackingRecord::class], 'dispatch' => ['Dispatch', \App\Models\Dispatch::class], 'iron_grade' => ['Iron grade', \App\Models\IronGrade::class],
        'core_box' => ['Core box', \App\Models\CoreBox::class], 'core_definition' => ['Core', \App\Models\CoreDefinition::class],
        'mould_configuration' => ['Mould configuration', \App\Models\MouldConfiguration::class],
        'enquiry' => ['Enquiry', \App\Models\Enquiry::class],
        'employee' => ['Employee', \App\Models\Employee::class],
        'employee_document' => ['Employee document', \App\Models\EmployeeDocument::class],
        'leave_request' => ['Leave request', \App\Models\LeaveRequest::class],
        'hr_policy' => ['HR policy', \App\Models\HrPolicy::class],
    ];

    public const CATEGORIES = ['CERTIFICATE' => 'Certificate', 'DRAWING' => 'Drawing', 'SPECIFICATION' => 'Specification', 'REPORT' => 'Test / inspection report', 'PHOTO' => 'Photo', 'CUSTOMER_DOCUMENT' => 'Customer document', 'ID_DOCUMENT' => 'ID / personal document', 'CONTRACT' => 'Contract / letter', 'OTHER' => 'Other'];

    public static function find(string $key, string $id): ?\Illuminate\Database\Eloquent\Model
    {
        $class = self::MAP[$key][1] ?? null;

        return $class ? $class::find($id) : null;
    }
}
