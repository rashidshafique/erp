@extends('layouts.app')
@section('title', 'Payroll '.$p->month->format('F Y'))
@section('breadcrumb', 'Payroll')
@section('content')
@php
    $cls = ['DRAFT' => 'status-on-hold', 'CALCULATED' => 'status-in-progress', 'APPROVED' => 'status-released', 'PAID' => 'status-completed'];
    $sc = ['UNPAID' => 'status-released', 'HELD' => 'status-on-hold', 'PAID' => 'status-completed'];
    $m = fn ($v) => \App\Support\Formats::number((float) $v);
    $editable = in_array($p->status, ['DRAFT', 'CALCULATED'], true);
@endphp
<div class="page-heading"><div><h1>{{ $p->month->format('F Y') }} <span class="status {{ $cls[$p->status] }}" style="font-size:12px;vertical-align:middle">{{ ucfirst(strtolower($p->status)) }}</span></h1><p>{{ $p->starts_on->displayShortDate() }} – {{ $p->ends_on->displayDate() }}@if ($p->calculated_at) · calculated {{ $p->calculated_at->diffForHumans() }}@endif @if ($p->approved_by_name) · approved by {{ $p->approved_by_name }}@endif</p></div>
<div class="actions"><a class="btn" href="{{ route('hr.payroll.index') }}">All payrolls</a>
@if ($p->payslips->isNotEmpty() || $slips->isNotEmpty())<a class="btn" href="{{ route('hr.payroll.register', $p) }}">Register (CSV)</a>@if (in_array($p->status, ['APPROVED','PAID'], true))<a class="btn" href="{{ route('hr.payroll.bank', $p) }}">Bank file (CSV)</a>@endif @endif</div></div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
@include('hr::payroll._tabs_note')

<div class="metrics">
<div class="metric"><div class="metric-label">People</div><div class="metric-value">{{ $sum['people'] ?? 0 }}</div><div class="metric-note">on this payroll</div></div>
<div class="metric"><div class="metric-label">Gross pay</div><div class="metric-value">{{ $m($totals['gross']) }}</div><div class="metric-note">PKR</div></div>
<div class="metric"><div class="metric-label">Net pay</div><div class="metric-value">{{ $m($totals['net']) }}</div><div class="metric-note">{{ $m($totals['paid']) }} paid so far</div></div>
<div class="metric"><div class="metric-label">Tax &amp; EOBI deducted</div><div class="metric-value">{{ $m(($sum['tax'] ?? 0) + ($sum['eobi_employee'] ?? 0)) }}</div><div class="metric-note">employer cost extra: {{ $m(($sum['eobi_employer'] ?? 0) + ($sum['ss_employer'] ?? 0)) }}</div></div>
</div>

<div class="panel"><div class="panel-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start">
@if ($editable)<form method="POST" action="{{ route('hr.payroll.calculate', $p) }}">@csrf<button class="btn btn-primary">{{ $p->status === 'DRAFT' ? 'Calculate payroll' : 'Recalculate' }}</button></form>@endif
@if ($p->status === 'CALCULATED')<form method="POST" action="{{ route('hr.payroll.approve', $p) }}" onsubmit="return confirm('Approve this payroll? Advance instalments are taken off the ledger.')">@csrf<button class="btn btn-primary" @disabled($checks['blockers'])>Approve payroll</button></form>@endif
@if ($p->status === 'APPROVED')<form method="POST" action="{{ route('hr.payroll.revert', $p) }}" onsubmit="return confirm('Send this payroll back? Instalments taken off advances are put back.')">@csrf<button class="btn">Send back for changes</button></form>@endif
@if (in_array($p->status, ['APPROVED'], true))
<form method="POST" action="{{ route('hr.payroll.pay', $p) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">@csrf
<div class="field" style="margin:0"><label>Paid on</label><input type="date" name="paid_on" value="{{ now()->toDateString() }}" required style="width:150px"></div>
<div class="field" style="margin:0"><label>Reference</label><input name="payment_ref" placeholder="Batch no." style="width:140px"></div>
<button class="btn btn-primary">Mark all unpaid as paid</button></form>@endif
</div></div>
@if ($checks['blockers'] && $p->status === 'CALCULATED')<div class="alert alert-error"><strong>Fix before approving:</strong><ul style="margin:6px 0 0 18px">@foreach ($checks['blockers'] as $b)<li>{{ $b }}</li>@endforeach</ul></div>@endif

<div class="tab-marker" data-tab-start="Payslips"></div>
<div class="panel"><div class="panel-body"><form method="GET" class="filters">
<div class="field"><label for="q">Find</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Name or code"></div>
<div class="field"><label for="st">Payment</label><select id="st" name="status"><option value="">All</option>@foreach (['UNPAID' => 'Unpaid', 'HELD' => 'Held', 'PAID' => 'Paid'] as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div><button class="btn btn-primary">Filter</button></form></div></div>
<div class="panel">@if ($slips->isEmpty())<div class="empty-state">{{ $p->status === 'DRAFT' ? 'Calculate the payroll to see payslips.' : 'No payslips match.' }}</div>@else
<div class="table-wrap"><table><thead><tr><th>Employee</th><th>Days</th><th>Gross</th><th>Tax</th><th>Other deductions</th><th>Net</th><th>Payment</th><th></th></tr></thead><tbody>
@foreach ($slips as $s)
<tr><td><a href="{{ route('hr.payroll.payslip', $s) }}"><strong>{{ $s->employee_code }}</strong> {{ $s->employee_name }}</a><div class="muted small">{{ $s->department_name }}@foreach ((array) $s->flags as $f) <span class="status status-on-hold" title="{{ $f }}">{{ str_starts_with($f, 'UNCLOSED') ? 'Days not closed' : ucfirst(strtolower(str_replace('_', ' ', $f))) }}</span>@endforeach</div></td>
<td>{{ rtrim(rtrim(\App\Support\Formats::number($s->payable_days, 2), '0'), '.') }} / {{ rtrim(rtrim(\App\Support\Formats::number($s->period_days, 2), '0'), '.') }}</td><td>{{ $m($s->gross) }}</td><td>{{ $m($s->income_tax) }}</td><td>{{ $m($s->total_deductions - $s->income_tax) }}</td><td @if ($s->net < 0) style="color:var(--danger)" @endif><strong>{{ $m($s->net) }}</strong></td>
<td><span class="status {{ $sc[$s->status] }}">{{ ucfirst(strtolower($s->status)) }}</span>@if ($s->hold_reason)<div class="muted small">{{ $s->hold_reason }}</div>@endif @if ($s->paid_on)<div class="muted small">{{ $s->paid_on->displayShortDate() }}</div>@endif</td>
<td style="white-space:nowrap">@if ($s->status === 'HELD')<form method="POST" action="{{ route('hr.payroll.release', $s) }}">@csrf<button class="btn btn-small">Release</button></form>
@elseif ($s->status === 'UNPAID' && in_array($p->status, ['CALCULATED', 'APPROVED'], true))<details class="row-edit"><summary class="muted small">Hold…</summary><form method="POST" action="{{ route('hr.payroll.hold', $s) }}" style="display:flex;gap:4px;margin-top:6px">@csrf<input name="hold_reason" placeholder="Why hold?" required style="min-height:28px;padding:2px 6px;max-width:150px"><button class="btn btn-small">Hold</button></form></details>@endif</td></tr>
@endforeach</tbody></table></div>@endif</div>

<div class="tab-marker" data-tab-start="Checks{{ ($checks['blockers'] || $checks['warnings']) ? ' ('.(count($checks['blockers']) + count($checks['warnings'])).')' : '' }}"></div>
<div class="panel"><div class="panel-body">
@forelse ($checks['blockers'] as $b)<div class="alert alert-error">{{ $b }}</div>@empty<p style="color:var(--success)"><strong>No blockers.</strong> Nothing stops this payroll being approved.</p>@endforelse
@foreach ($checks['warnings'] as $w)<div class="alert" style="background:#fff8e1;border:1px solid #e6c36a;color:#7a5b00">{{ $w }}</div>@endforeach
<p class="muted small">Blockers are open attendance exceptions, overtime still waiting for a decision and negative net pay. Fix them where they come from, then recalculate.</p>
</div></div>

<div class="tab-marker" data-tab-start="By department"></div>
<div class="panel">@if ($depts->isEmpty())<div class="empty-state">Nothing calculated yet.</div>@else<div class="table-wrap"><table><thead><tr><th>Department</th><th>People</th><th>Gross</th><th>Tax</th><th>Deductions</th><th>Net</th><th>Employer EOBI / social security</th></tr></thead><tbody>
@foreach ($depts as $d)<tr><td><strong>{{ $d['name'] }}</strong></td><td>{{ $d['people'] }}</td><td>{{ $m($d['gross']) }}</td><td>{{ $m($d['tax']) }}</td><td>{{ $m($d['deductions']) }}</td><td>{{ $m($d['net']) }}</td><td>{{ $m($d['employer']) }}</td></tr>@endforeach
<tr><td><strong>Total</strong></td><td>{{ $depts->sum('people') }}</td><td><strong>{{ $m($depts->sum('gross')) }}</strong></td><td>{{ $m($depts->sum('tax')) }}</td><td>{{ $m($depts->sum('deductions')) }}</td><td><strong>{{ $m($depts->sum('net')) }}</strong></td><td>{{ $m($depts->sum('employer')) }}</td></tr></tbody></table></div>@endif</div>

<div class="tab-marker" data-tab-start="One-off items{{ $adjustments->count() ? ' ('.$adjustments->count().')' : '' }}"></div>
<div class="panel"><div class="panel-header"><h2>Bonuses, arrears and one-time deductions</h2><span class="muted small">Join the payslip when the month is calculated</span></div>
@if ($adjustments->isNotEmpty())<div class="table-wrap"><table><thead><tr><th>Employee</th><th>Item</th><th>Amount</th><th>Taxable</th><th></th></tr></thead><tbody>
@foreach ($adjustments as $a)<tr><td>{{ $a->employee->code }} {{ $a->employee->full_name }}</td><td>{{ $a->name }}<div class="muted small">{{ $a->note }}</div></td><td>{{ $a->kind === 'DEDUCTION' ? '−' : '+' }}{{ $m($a->amount) }}</td><td>{{ $a->kind === 'EARNING' ? ($a->taxable ? 'Yes' : 'No') : '—' }}</td>
<td>@if ($a->payslip_id)<span class="status status-completed">Paid out</span>@else<form method="POST" action="{{ route('hr.payroll.adjustments.destroy', $a) }}" onsubmit="return confirm('Remove this item?')">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove', 'name' => $a->name])</form>@endif</td></tr>@endforeach</tbody></table></div>@endif
@if ($editable)<div class="panel-body"><form method="POST" action="{{ route('hr.payroll.adjustments.store') }}">@csrf<input type="hidden" name="month" value="{{ $p->month->format('Y-m') }}"><div class="form-grid">
@include('hr::_sel', ['blank' => false, 'name' => 'employee_id', 'label' => 'Employee', 'items' => $people->mapWithKeys(fn ($x) => [$x->id => $x->code.' · '.$x->full_name])->all(), 'sel' => '', 'blank' => 'Choose…', 'required' => true, 'keep' => true])
@include('hr::_sel', ['blank' => false, 'name' => 'kind', 'label' => 'Type', 'items' => ['EARNING' => 'Bonus / arrears (adds to pay)', 'DEDUCTION' => 'Deduction (takes off pay)'], 'sel' => 'EARNING', 'required' => true, 'keep' => true])
@include('hr::_in', ['name' => 'name', 'label' => 'What for', 'val' => '', 'required' => true, 'keep' => true]) @include('hr::_in', ['name' => 'amount', 'label' => 'Amount (PKR)', 'type' => 'number', 'val' => '', 'required' => true, 'keep' => true])
@include('hr::_in', ['name' => 'note', 'label' => 'Note', 'val' => ''])
<div class="field"><label><input type="checkbox" name="taxable" value="1" checked> Counts as taxable income</label></div></div><p><button class="btn btn-primary">Add item</button></p></form></div>
@else<div class="panel-body muted small">This payroll is approved, so items can no longer be added. Send it back for changes, or add the item to next month.</div>@endif</div>
@endsection
