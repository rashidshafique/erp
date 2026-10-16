<?php

namespace App\Http\Controllers;

use App\Support\AttachableEntities;
use App\Support\CodedRecords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ToolController extends Controller
{
    private function tool(string $type, string $id)
    {
        abort_unless(in_array($type, CodedRecords::TOOLS, true), 404);
        $class = AttachableEntities::MAP[$type][1];

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
        $records = \App\Models\ToolingMaintenanceRecord::where($fk, $id)->orderByDesc('reported_at')->limit(20)->get();

        return view('tools.show', ['tool' => $tool, 'type' => $type, 'label' => CodedRecords::TYPES[$type][2], 'records' => $records]);
    }

    /** Record a mark that is already engraved on an old tool, or fix a typing mistake. Marks stay unique across all tooling. */
    public function updateMark(Request $request, string $type, string $id)
    {
        $tool = $this->tool($type, $id);
        $d = $request->validate(['mark_code' => ['required', 'string', 'min:3', 'max:20', 'regex:/^[A-Za-z0-9]+$/']]);
        $mark = strtoupper($d['mark_code']);
        $clash = DB::table('tool_marks')->where('mark_code', $mark)->where(fn ($q) => $q->where('tool_type', '!=', $type)->orWhere('tool_id', '!=', $id))->exists();
        if ($clash) {
            throw ValidationException::withMessages(['mark_code' => "Mark $mark is already on another tool."]);
        }
        DB::transaction(function () use ($tool, $type, $id, $mark) {
            $tool->update(['mark_code' => $mark]);
            app(\App\Services\Codes\CodeGenerator::class)->register($type, $id, $mark);
        });

        return back()->with('success', 'Mark saved.');
    }
}
