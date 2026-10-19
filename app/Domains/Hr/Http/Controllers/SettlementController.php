<?php

namespace App\Domains\Hr\Http\Controllers;

use App\Domains\Hr\Models\EmployeeExit;
use App\Domains\Hr\Models\EmployeeSettlement;
use App\Domains\Hr\Services\SettlementService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Full and final settlement for people who are leaving. */
class SettlementController extends Controller
{
    public function index(): View
    {
        return view('hr::payroll.settlements.index', [
            'rows' => EmployeeSettlement::with('employee.department', 'exit')->orderByDesc('created_at')->get(),
            'waiting' => EmployeeExit::with('employee')->whereDoesntHave('settlement')->orderBy('last_working_day')->get(),
        ]);
    }

    public function open(EmployeeExit $exit, SettlementService $svc): RedirectResponse
    {
        $s = $svc->open($exit);

        return redirect()->route('hr.payroll.settlements.show', $s)->with('success', 'Settlement calculated. Check each line, add anything the formulas do not know about, then approve.');
    }

    public function show(EmployeeSettlement $settlement): View
    {
        return view('hr::payroll.settlements.show', ['s' => $settlement->load('employee.department', 'exit', 'lines')]);
    }

    public function calculate(EmployeeSettlement $settlement, SettlementService $svc): RedirectResponse
    {
        try {
            $svc->calculate($settlement);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Recalculated. Manual lines were kept.');
    }

    public function addLine(Request $request, EmployeeSettlement $settlement, SettlementService $svc): RedirectResponse
    {
        $d = $request->validate(['kind' => ['required', 'in:DUE,RECOVERY'], 'name' => ['required', 'string', 'max:160'], 'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'note' => ['nullable', 'string', 'max:300']]);
        try {
            $svc->addLine($settlement, $d['kind'], $d['name'], (float) $d['amount'], $d['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Line added.');
    }

    public function removeLine(EmployeeSettlement $settlement, string $line, SettlementService $svc): RedirectResponse
    {
        try {
            $svc->removeLine($settlement, $line);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Line removed.');
    }

    public function approve(EmployeeSettlement $settlement, SettlementService $svc): RedirectResponse
    {
        try {
            $ok = $svc->approve($settlement, $message);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $ok ? back()->with('success', 'Settlement approved.') : back()->with('error', $message);
    }

    public function pay(Request $request, EmployeeSettlement $settlement, SettlementService $svc): RedirectResponse
    {
        $d = $request->validate(['paid_on' => ['required', 'date'], 'payment_method' => ['required', 'in:BANK,CASH,CHEQUE'], 'payment_ref' => ['nullable', 'string', 'max:100']]);
        try {
            $svc->pay($settlement, $d['paid_on'], $d['payment_method'], $d['payment_ref'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment recorded. Any loan balance was cleared.');
    }
}
