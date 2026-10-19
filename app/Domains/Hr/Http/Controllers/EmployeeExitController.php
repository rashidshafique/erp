<?php

namespace App\Domains\Hr\Http\Controllers;

use App\Domains\Hr\Models\EmployeeExit;
use App\Domains\Hr\Services\Lifecycle;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class EmployeeExitController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status', 'INITIATED');
        $exits = EmployeeExit::with('employee.department', 'employee.designation')
            ->when($status !== 'ALL', fn ($q) => $q->where('status', $status))
            ->orderBy('last_working_day')->paginate(25)->withQueryString();

        return view('hr::exits.index', compact('exits', 'status'));
    }

    public function show(EmployeeExit $exit, Lifecycle $life): View
    {
        $exit->load('employee.department', 'employee.designation', 'employee.tasks');

        return view('hr::exits.show', ['x' => $exit, 'e' => $exit->employee, 'blockers' => $life->exitBlockers($exit)]);
    }

    public function update(Request $request, EmployeeExit $exit): RedirectResponse
    {
        abort_if($exit->status !== 'INITIATED', 422, 'This exit is closed.');
        $d = $request->validate([
            'last_working_day' => ['required', 'date'], 'notice_given_on' => ['nullable', 'date'], 'reason' => ['nullable', 'string', 'max:500'],
            'exit_interview' => ['nullable', 'string', 'max:5000'], 'rehire_eligible' => ['nullable', 'in:0,1'],
        ]);
        $d['rehire_eligible'] = ($d['rehire_eligible'] ?? '') === '' ? null : (bool) $d['rehire_eligible'];
        $exit->update(array_map(fn ($v) => $v === '' ? null : $v, $d));

        return back()->with('success', 'Saved.');
    }

    public function complete(EmployeeExit $exit, Lifecycle $life): RedirectResponse
    {
        try {
            $ok = $life->completeExit($exit, $msg);
        } catch (RuntimeException $ex) {
            return back()->with('error', $ex->getMessage());
        }

        return $ok ? back()->with('success', 'Exit completed. The employee is marked as left.') : back()->with('error', $msg);
    }

    public function cancel(Request $request, EmployeeExit $exit, Lifecycle $life): RedirectResponse
    {
        abort_if($exit->status !== 'INITIATED', 422, 'This exit is closed.');
        $life->cancelExit($exit, $request->input('note'));

        return redirect()->route('hr.employees.show', $exit->employee_id)->with('success', 'Exit cancelled.');
    }
}
