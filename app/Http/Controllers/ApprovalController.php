<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Services\Approvals\ApprovalGate;
use Illuminate\Http\Request;
use RuntimeException;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'OPEN');
        $q = ApprovalRequest::with('decisions')->latest();
        $status === 'OPEN' ? $q->whereIn('status', ['PENDING', 'APPROVED']) : ($status === 'ALL' ? null : $q->where('status', $status));

        return view('approvals.index', ['requests' => $q->paginate(30)->withQueryString(), 'status' => $status]);
    }

    public function decide(Request $request, ApprovalRequest $approval, ApprovalGate $gate)
    {
        $d = $request->validate(['decision' => 'required|in:APPROVE,REJECT', 'comment' => 'nullable|string|max:1000']);
        try {
            $gate->decide($approval, $d['decision'], $d['comment'] ?? null, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('success', $d['decision'] === 'APPROVE' ? 'Decision recorded.' : 'Request rejected.');
    }

    public function cancel(Request $request, ApprovalRequest $approval, ApprovalGate $gate)
    {
        try {
            $gate->cancel($approval, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }

        return back()->with('success', 'Request cancelled.');
    }
}
