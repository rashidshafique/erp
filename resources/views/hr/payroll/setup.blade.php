@extends('layouts.app')
@section('title', 'Payroll setup')
@section('breadcrumb', 'Payroll setup')
@section('content')
@php $nf = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'); $slabText = old('tax_slabs', collect($R['tax_slabs'])->map(fn ($s) => $nf($s['from']).' ; '.$nf($s['rate']).' ; '.$nf($s['fixed']))->implode("\n")); @endphp
<div class="page-heading"><div><h1>Payroll setup</h1><p>Pay components and the rules payroll follows.</p></div><div class="actions"><a class="btn" href="{{ route('hr.payroll.index') }}">Payroll</a></div></div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
<div class="alert" style="background:#fff8e1;border:1px solid #e6c36a;color:#7a5b00"><strong>Every rate and slab below is a placeholder.</strong> {{ $R['tax_year_note'] ?? '' }}. Check them against the current Finance Act, EOBI and provincial social security notifications and your contracts before the first real payroll.</div>

<div class="tab-marker" data-tab-start="Pay components"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Default</th><th>Taxable</th><th>Cut for absence</th><th></th></tr></thead><tbody>
@foreach ($components as $c)
<tr><td><strong>{{ $c->code }}</strong></td><td>{{ $c->name }} @unless ($c->active)<span class="status status-cancelled">Off</span>@endunless @if ($c->is_basic)<span class="muted small">(the basic salary)</span>@endif</td><td>{{ ucfirst(strtolower($c->kind)) }}</td>
<td>{{ $c->is_basic ? '—' : ($c->calc === 'PERCENT_BASIC' ? $nf($c->default_value).'% of basic' : number_format($c->default_value)) }}</td><td>{{ $c->taxable ? 'Yes' : 'No' }}</td><td>{{ $c->prorate ? 'Yes' : 'No' }}</td>
<td><details class="row-edit">@include('partials.icon-button', ['type' => 'edit', 'name' => $c->name, 'tag' => 'summary'])<form method="POST" action="{{ route('hr.payroll.components.update', $c) }}" style="min-width:300px;margin-top:8px">@csrf @method('PUT')
<div class="form-grid">@include('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => $c->code, 'required' => true, 'keep' => true]) @include('hr._in', ['name' => 'name', 'label' => 'Name', 'val' => $c->name, 'required' => true, 'keep' => true])
@unless ($c->is_basic)@include('hr._sel', ['blank' => false, 'name' => 'kind', 'label' => 'Type', 'items' => ['EARNING' => 'Earning', 'DEDUCTION' => 'Fixed deduction'], 'sel' => $c->kind, 'required' => true, 'keep' => true]) @include('hr._sel', ['blank' => false, 'name' => 'calc', 'label' => 'Default is', 'items' => ['FIXED' => 'A fixed amount', 'PERCENT_BASIC' => 'A % of basic'], 'sel' => $c->calc, 'required' => true, 'keep' => true]) @include('hr._in', ['name' => 'default_value', 'label' => 'Default value', 'type' => 'number', 'val' => $nf($c->default_value), 'keep' => true])@endunless
@include('hr._in', ['name' => 'sort', 'label' => 'Order', 'type' => 'number', 'val' => $c->sort, 'keep' => true])</div>
<label><input type="checkbox" name="taxable" value="1" @checked($c->taxable)> Taxable</label> <label><input type="checkbox" name="prorate" value="1" @checked($c->prorate)> Cut for absent days</label>@unless ($c->is_basic) <label><input type="checkbox" name="active" value="1" @checked($c->active)> In use</label>@endunless
<p><button class="btn btn-small btn-primary">Save</button></p></form></details></td></tr>
@endforeach</tbody></table></div></div>
<div class="panel"><div class="panel-header"><h2>Add a component</h2></div><div class="panel-body"><form method="POST" action="{{ route('hr.payroll.components.store') }}">@csrf<div class="form-grid">
@include('hr._in', ['name' => 'code', 'label' => 'Code', 'val' => '', 'required' => true, 'keep' => true]) @include('hr._in', ['name' => 'name', 'label' => 'Name', 'val' => '', 'required' => true, 'keep' => true])
@include('hr._sel', ['blank' => false, 'name' => 'kind', 'label' => 'Type', 'items' => ['EARNING' => 'Earning (allowance)', 'DEDUCTION' => 'Fixed deduction'], 'sel' => 'EARNING', 'required' => true, 'keep' => true])
@include('hr._sel', ['blank' => false, 'name' => 'calc', 'label' => 'Default is', 'items' => ['FIXED' => 'A fixed amount', 'PERCENT_BASIC' => 'A % of basic'], 'sel' => 'FIXED', 'required' => true, 'keep' => true]) @include('hr._in', ['name' => 'default_value', 'label' => 'Default value', 'type' => 'number', 'val' => '0', 'keep' => true])</div>
<label><input type="checkbox" name="taxable" value="1" checked> Taxable</label> <label><input type="checkbox" name="prorate" value="1" checked> Cut for absent days</label>
<p><button class="btn btn-primary">Add</button></p></form></div></div>

<div class="tab-marker" data-tab-start="Rules"></div>
<form method="POST" action="{{ route('hr.payroll.rules') }}">@csrf @method('PUT')
@if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="panel"><div class="panel-header"><h2>Days, attendance and overtime</h2></div><div class="panel-body"><div class="form-grid">
@include('hr._sel', ['blank' => false, 'name' => 'days_basis', 'label' => 'A month is worth', 'items' => ['CALENDAR' => 'Calendar days in the month', '26' => '26 days', '30' => '30 days'], 'sel' => (string) old('days_basis', $R['days_basis']), 'required' => true, 'keep' => true, 'help' => 'Pay for a day = salary ÷ this.'])
@include('hr._sel', ['blank' => false, 'name' => 'missing_attendance', 'label' => 'A working day nobody closed is', 'items' => ['PRESENT' => 'Paid', 'ABSENT' => 'Unpaid'], 'sel' => old('missing_attendance', $R['missing_attendance']), 'required' => true, 'keep' => true])
@include('hr._sel', ['blank' => false, 'name' => 'half_day_other_half', 'label' => 'Half day for short hours: the other half is', 'items' => ['UNPAID' => 'Unpaid', 'PAID' => 'Paid'], 'sel' => old('half_day_other_half', $R['half_day_other_half']), 'required' => true, 'keep' => true])
@include('hr._in', ['name' => 'late_marks_per_day', 'label' => 'Late marks that cost a day', 'type' => 'number', 'val' => old('late_marks_per_day', $R['late_marks_per_day']), 'required' => true, 'keep' => true, 'help' => '0 switches it off.'])
@include('hr._in', ['name' => 'overtime_multiplier', 'label' => 'Overtime pays', 'type' => 'number', 'val' => old('overtime_multiplier', $nf($R['overtime_multiplier'])), 'required' => true, 'keep' => true, 'help' => 'Times the normal hourly rate.'])
@include('hr._sel', ['blank' => false, 'name' => 'overtime_base', 'label' => 'Hourly rate is worked from', 'items' => ['BASIC' => 'Basic salary', 'GROSS' => 'Gross salary'], 'sel' => old('overtime_base', $R['overtime_base']), 'required' => true, 'keep' => true])
@include('hr._in', ['name' => 'hours_per_day', 'label' => 'Hours in a working day', 'type' => 'number', 'val' => old('hours_per_day', $nf($R['hours_per_day'])), 'required' => true, 'keep' => true])
@include('hr._sel', ['blank' => false, 'name' => 'round_to', 'label' => 'Round every amount to', 'items' => ['1' => 'The nearest rupee', '0.01' => 'The paisa'], 'sel' => (string) old('round_to', $nf($R['round_to'])), 'required' => true, 'keep' => true])
@include('hr._in', ['name' => 'max_recovery_percent', 'label' => 'Advance recovery cap (%)', 'type' => 'number', 'val' => old('max_recovery_percent', $nf($R['max_recovery_percent'])), 'required' => true, 'keep' => true, 'help' => 'Of pay left after tax and EOBI.'])
</div></div></div>
<div class="panel"><div class="panel-header"><h2>Income tax on salary</h2></div><div class="panel-body">
<label><input type="checkbox" name="tax_enabled" value="1" @checked(old('tax_enabled', $R['tax_enabled']))> Deduct income tax from salaries</label>
<div class="form-grid" style="margin-top:10px">@include('hr._in', ['name' => 'tax_year_note', 'label' => 'Which tax year these slabs are for', 'val' => old('tax_year_note', $R['tax_year_note'] ?? ''), 'keep' => true])
@include('hr._in', ['name' => 'tax_surcharge_over', 'label' => 'Surcharge on annual income above', 'type' => 'number', 'val' => old('tax_surcharge_over', $nf($R['tax_surcharge_over'])), 'keep' => true]) @include('hr._in', ['name' => 'tax_surcharge_percent', 'label' => 'Surcharge (%)', 'type' => 'number', 'val' => old('tax_surcharge_percent', $nf($R['tax_surcharge_percent'])), 'keep' => true])</div>
<div class="field" style="max-width:460px"><label for="tax_slabs">Annual slabs — one per line: starts at ; rate % ; tax at the start</label><textarea id="tax_slabs" name="tax_slabs" rows="7" style="font-family:ui-monospace,Menlo,monospace" required>{{ $slabText }}</textarea>
<div class="help">Tax for an annual income = the tax at the start of its slab + (income − start) × rate. Each month is one twelfth of the tax on twelve months of regular pay; bonuses and overtime are taxed as extra income in the month they are paid.</div></div></div></div>
<div class="panel"><div class="panel-header"><h2>EOBI and social security</h2></div><div class="panel-body"><div class="form-grid">
<div class="field"><label><input type="checkbox" name="eobi_enabled" value="1" @checked(old('eobi_enabled', $R['eobi_enabled']))> Deduct EOBI</label></div>
@include('hr._in', ['name' => 'eobi_wage_base', 'label' => 'EOBI wage base (PKR)', 'type' => 'number', 'val' => old('eobi_wage_base', $nf($R['eobi_wage_base'])), 'required' => true, 'keep' => true, 'help' => 'The minimum wage the contribution is worked on.'])
@include('hr._in', ['name' => 'eobi_employee_percent', 'label' => 'Employee share (%)', 'type' => 'number', 'val' => old('eobi_employee_percent', $nf($R['eobi_employee_percent'])), 'required' => true, 'keep' => true]) @include('hr._in', ['name' => 'eobi_employer_percent', 'label' => 'Employer share (%)', 'type' => 'number', 'val' => old('eobi_employer_percent', $nf($R['eobi_employer_percent'])), 'required' => true, 'keep' => true])
<div class="field"><label><input type="checkbox" name="ss_enabled" value="1" @checked(old('ss_enabled', $R['ss_enabled']))> Provincial social security</label></div>
@include('hr._in', ['name' => 'ss_employer_percent', 'label' => 'Employer share (%)', 'type' => 'number', 'val' => old('ss_employer_percent', $nf($R['ss_employer_percent'])), 'required' => true, 'keep' => true]) @include('hr._in', ['name' => 'ss_wage_ceiling', 'label' => 'Wage ceiling (PKR)', 'type' => 'number', 'val' => old('ss_wage_ceiling', $nf($R['ss_wage_ceiling'])), 'required' => true, 'keep' => true])</div>
<p class="muted small">Switch EOBI, social security and income tax on or off for each person on their Pay tab.</p></div></div>
<div class="panel"><div class="panel-header"><h2>Final settlement</h2></div><div class="panel-body"><div class="form-grid">
<div class="field"><label><input type="checkbox" name="gratuity_enabled" value="1" @checked(old('gratuity_enabled', $R['gratuity_enabled']))> Pay gratuity</label></div>
@include('hr._in', ['name' => 'gratuity_days_per_year', 'label' => 'Days of basic per completed year', 'type' => 'number', 'val' => old('gratuity_days_per_year', $nf($R['gratuity_days_per_year'])), 'required' => true, 'keep' => true]) @include('hr._in', ['name' => 'gratuity_min_years', 'label' => 'Years of service before it is due', 'type' => 'number', 'val' => old('gratuity_min_years', $R['gratuity_min_years']), 'required' => true, 'keep' => true])
@include('hr._in', ['name' => 'encash_leave_codes', 'label' => 'Leave types paid out when leaving', 'val' => old('encash_leave_codes', implode(', ', $R['encash_leave_codes'] ?? [])), 'keep' => true, 'help' => 'Leave codes, comma separated. Paid at basic ÷ 30 a day.'])</div></div></div>
<p><button class="btn btn-primary">Save rules</button></p></form>
@endsection
