<?php

namespace App\Domains\Tooling\Http\Controllers;

use App\Domains\Tooling\Models\ToolingMaintenanceRecord;
use App\Http\Controllers\Controller;
use App\Services\Codes\CodeGenerator;
use App\Support\AttachableEntities;
use App\Support\CodedRecords;
use Illuminate\Http\Request;

class ToolController extends Controller
{
    private function tool(string $type, string $id)
    {
        abort_unless(in_array($type, CodedRecords::TOOLS, true), 404);
        $class = AttachableEntities::map()[$type][1];

        return $class::findOrFail($id);
    }

    public function show(string $type, string $id)
    {
        if ($type === 'pattern') {
            return redirect()->route('patterns.show', $id);
        }
        $tool = $this->tool($type, $id);
        $tool->ensureMark();
        $fk = ['core_box' => 'core_box_id', 'core_definition' => 'core_definition_id', 'mould_configuration' => 'mould_configuration_id'][$type];
        $records = ToolingMaintenanceRecord::where($fk, $id)->orderByDesc('reported_at')->limit(20)->get();

        return view('tooling::tools.show', ['tool' => $tool, 'type' => $type, 'label' => CodedRecords::TYPES[$type][2], 'records' => $records]);
    }

    /** Record a mark that is already engraved on an old tool, or fix a typing mistake. Marks stay unique across all tooling. */
    public function updateMark(Request $request, string $type, string $id)
    {
        $tool = $this->tool($type, $id);
        $d = $request->validate(['mark_code' => CodeGenerator::MARK_RULES]);
        app(CodeGenerator::class)->changeMark($type, $tool, $d['mark_code']);

        return back()->with('success', 'Mark saved.');
    }
}
