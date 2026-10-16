<?php

namespace App\Http\Controllers;

use App\Models\ApprovalPolicy;
use Illuminate\Http\Request;

class ApprovalPolicyController extends Controller
{
    public function index()
    {
        $policies = ApprovalPolicy::all()->keyBy('action_key');

        return view('approvals.policies', ['actions' => config('approvals.actions'), 'policies' => $policies, 'permissions' => config('permissions.catalog')]);
    }

    public function update(Request $request)
    {
        // Action keys contain dots, so read the array directly instead of using dotted validation paths.
        $rows = (array) $request->input('p', []);
        $perms = array_keys(config('permissions.catalog'));
        foreach (array_keys(config('approvals.actions')) as $key) {
            $row = (array) ($rows[$key] ?? []);
            $permission = in_array($row['permission'] ?? '', $perms, true) ? $row['permission'] : 'approvals.decide';
            ApprovalPolicy::updateOrCreate(['action_key' => $key], [
                'is_active' => ! empty($row['is_active']),
                'required_approvals' => max(1, min(5, (int) ($row['required_approvals'] ?? 1))),
                'permission' => $permission,
                'allow_self_approval' => ! empty($row['allow_self_approval']),
            ]);
        }

        return back()->with('success', 'Approval rules saved.');
    }
}
