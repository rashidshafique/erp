<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $q = AuditLog::query()
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', $request->query('entity_type')))
            ->when($request->filled('entity_id'), fn ($q) => $q->where('entity_id', $request->query('entity_id')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->query('action')))
            ->when($request->filled('user'), fn ($q) => $q->where('user_name', 'ilike', '%'.$request->query('user').'%'))
            ->when($request->filled('q'), fn ($q) => $q->where('entity_label', 'ilike', '%'.$request->query('q').'%'))
            ->when($request->filled('from'), fn ($q) => $q->where('occurred_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('occurred_at', '<', \Carbon\Carbon::parse($request->query('to'))->addDay()));

        return view('audit.index', [
            'entries' => $q->orderByDesc('id')->paginate(50)->withQueryString(),
            'types' => AuditLog::query()->distinct()->orderBy('entity_type')->pluck('entity_type'),
        ]);
    }
}
