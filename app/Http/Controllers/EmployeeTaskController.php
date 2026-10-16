<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EmployeeTaskController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $d = $request->validate(['kind' => ['required', 'in:ONBOARDING,EXIT'], 'title' => ['required', 'string', 'max:200'], 'owner' => ['required', Rule::in(array_keys(EmployeeTask::OWNERS))], 'due_on' => ['nullable', 'date']]);
        EmployeeTask::create($d + ['employee_id' => $employee->id, 'sort' => ((int) $employee->tasks()->max('sort')) + 10]);

        return back()->with('success', 'Task added.');
    }

    public function toggle(Request $request, EmployeeTask $task): RedirectResponse
    {
        $request->validate(['note' => ['nullable', 'string', 'max:300']]);
        if ($task->done_at) {
            $task->update(['done_at' => null, 'done_by_name' => null]);
        } else {
            $task->update(['done_at' => now(), 'done_by_name' => Auth::user()?->name, 'note' => $request->input('note') ?: $task->note]);
        }

        return back();
    }

    public function destroy(EmployeeTask $task): RedirectResponse
    {
        $task->delete();

        return back()->with('success', 'Task removed.');
    }
}
