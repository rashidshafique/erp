@extends('layouts.app')
@section('title', 'Organisation')
@section('breadcrumb', 'People organisation')
@section('content')
<div class="page-heading"><div><h1>Organisation</h1><p>Departments, designations and grades, locations, cost centres and the joining and leaving checklists.</p></div></div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
@php
    $tab = request('tab');
    $codeHelp = 'Leave blank to generate one.';
    $deptOpts = $departments->mapWithKeys(fn ($d) => [$d->id => $d->path()])->all();
    $ccOpts = $costCentres->mapWithKeys(fn ($c) => [$c->id => $c->code.' · '.$c->name])->all();
    $headOpts = $heads->mapWithKeys(fn ($h) => [$h->id => $h->code.' · '.$h->full_name])->all();
    $gradeOpts = $grades->mapWithKeys(fn ($g) => [$g->id => $g->name])->all();
    $money = fn ($v) => $v === null ? '—' : 'PKR '.\App\Support\Formats::number($v);
@endphp
<style>.row-edit summary { cursor: pointer; color: var(--accent); font-weight: 600; } .row-edit[open] { padding: 10px 0; }</style>

{{-- Departments --}}
<div class="tab-marker" data-tab-start="Departments"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Department</th><th>Part of</th><th>Cost centre</th><th>Head</th><th>People</th><th></th></tr></thead><tbody>
@forelse ($departments as $d)
<tr><td><strong>{{ $d->code }}</strong></td><td>{{ $d->name }} @unless ($d->active)<span class="status status-cancelled">Inactive</span>@endunless</td><td>{{ $d->parent?->name ?? '—' }}</td><td>{{ $d->costCentre?->code ?? '—' }}</td><td>{{ $d->head?->full_name ?? '—' }}</td><td>{{ $d->employees_count }}</td>
<td><details class="row-edit">@include('partials.icon-button', ['type' => 'edit', 'name' => $d->name, 'tag' => 'summary'])
<form method="POST" action="{{ route('hr.organisation.update', ['departments', $d->id]) }}" class="form-grid">@csrf @method('PUT')
    @include('hr::_in', ['name' => 'code', 'label' => 'Code', 'val' => $d->code]) @include('hr::_in', ['name' => 'name', 'label' => 'Name', 'val' => $d->name, 'required' => true])
    @include('hr::_sel', ['name' => 'parent_id', 'label' => 'Part of', 'items' => array_diff_key($deptOpts, [$d->id => 1]), 'sel' => $d->parent_id])
    @include('hr::_sel', ['name' => 'cost_centre_id', 'label' => 'Cost centre', 'items' => $ccOpts, 'sel' => $d->cost_centre_id])
    @include('hr::_sel', ['name' => 'head_employee_id', 'label' => 'Head', 'items' => $headOpts, 'sel' => $d->head_employee_id])
    <div class="field"><label><input type="checkbox" name="active" value="1" @checked($d->active)> Active</label></div>
    <div class="field"><button class="btn btn-primary">Save</button></div></form>
<form method="POST" action="{{ route('hr.organisation.destroy', ['departments', $d->id]) }}" onsubmit="return confirm('Delete this department?')">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'name' => $d->name])</form></details></td></tr>
@empty<tr><td colspan="7" class="muted">None yet. Add the first below.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body"><form method="POST" action="{{ route('hr.organisation.store', 'departments') }}">@csrf<div class="form-grid">
    @include('hr::_in', ['name' => 'name', 'label' => 'New department', 'val' => '', 'required' => true, 'ph' => 'e.g. Moulding'])
    @include('hr::_sel', ['name' => 'parent_id', 'label' => 'Part of', 'items' => $deptOpts, 'sel' => ''])
    @include('hr::_sel', ['name' => 'cost_centre_id', 'label' => 'Cost centre', 'items' => $ccOpts, 'sel' => ''])
    @include('hr::_in', ['name' => 'code', 'label' => 'Code', 'val' => '', 'help' => $codeHelp])
</div><p><button class="btn btn-primary">Add department</button></p></form></div></div>

{{-- Designations --}}
<div class="tab-marker" data-tab-start="Designations"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Designation</th><th>Grade</th><th>People</th><th></th></tr></thead><tbody>
@forelse ($designations as $d)
<tr><td><strong>{{ $d->code }}</strong></td><td>{{ $d->name }} @unless ($d->active)<span class="status status-cancelled">Inactive</span>@endunless</td><td>{{ $d->grade?->name ?? '—' }}</td><td>{{ $employeeCounts['designation_id'][$d->id] ?? 0 }}</td>
<td><details class="row-edit">@include('partials.icon-button', ['type' => 'edit', 'name' => $d->name, 'tag' => 'summary'])<form method="POST" action="{{ route('hr.organisation.update', ['designations', $d->id]) }}" class="form-grid">@csrf @method('PUT')
    @include('hr::_in', ['name' => 'code', 'label' => 'Code', 'val' => $d->code]) @include('hr::_in', ['name' => 'name', 'label' => 'Name', 'val' => $d->name, 'required' => true])
    @include('hr::_sel', ['name' => 'grade_id', 'label' => 'Grade', 'items' => $gradeOpts, 'sel' => $d->grade_id])
    <div class="field"><label><input type="checkbox" name="active" value="1" @checked($d->active)> Active</label></div><div class="field"><button class="btn btn-primary">Save</button></div></form>
<form method="POST" action="{{ route('hr.organisation.destroy', ['designations', $d->id]) }}" onsubmit="return confirm('Delete this designation?')">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'name' => $d->name])</form></details></td></tr>
@empty<tr><td colspan="5" class="muted">None yet.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body"><form method="POST" action="{{ route('hr.organisation.store', 'designations') }}">@csrf<div class="form-grid">
    @include('hr::_in', ['name' => 'name', 'label' => 'New designation', 'val' => '', 'required' => true, 'ph' => 'e.g. Moulder'])
    @include('hr::_sel', ['name' => 'grade_id', 'label' => 'Grade', 'items' => $gradeOpts, 'sel' => ''])
    @include('hr::_in', ['name' => 'code', 'label' => 'Code', 'val' => '', 'help' => $codeHelp])
</div><p><button class="btn btn-primary">Add designation</button></p></form></div></div>

{{-- Grades --}}
<div class="tab-marker" data-tab-start="Grades"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Grade</th><th>Rank</th><th>Salary band</th><th></th></tr></thead><tbody>
@forelse ($grades as $g)
<tr><td><strong>{{ $g->code }}</strong></td><td>{{ $g->name }} @unless ($g->active)<span class="status status-cancelled">Inactive</span>@endunless</td><td>{{ $g->rank }}</td><td>{{ $money($g->min_salary) }} – {{ $money($g->max_salary) }}</td>
<td><details class="row-edit">@include('partials.icon-button', ['type' => 'edit', 'name' => $g->name, 'tag' => 'summary'])<form method="POST" action="{{ route('hr.organisation.update', ['grades', $g->id]) }}" class="form-grid">@csrf @method('PUT')
    @include('hr::_in', ['name' => 'code', 'label' => 'Code', 'val' => $g->code]) @include('hr::_in', ['name' => 'name', 'label' => 'Name', 'val' => $g->name, 'required' => true])
    @include('hr::_in', ['name' => 'rank', 'label' => 'Rank', 'type' => 'number', 'val' => $g->rank, 'required' => true])
    @include('hr::_in', ['name' => 'min_salary', 'label' => 'Band from (PKR)', 'type' => 'number', 'val' => $g->min_salary]) @include('hr::_in', ['name' => 'max_salary', 'label' => 'Band to (PKR)', 'type' => 'number', 'val' => $g->max_salary])
    <div class="field"><label><input type="checkbox" name="active" value="1" @checked($g->active)> Active</label></div><div class="field"><button class="btn btn-primary">Save</button></div></form>
<form method="POST" action="{{ route('hr.organisation.destroy', ['grades', $g->id]) }}" onsubmit="return confirm('Delete this grade?')">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'name' => $g->name])</form></details></td></tr>
@empty<tr><td colspan="5" class="muted">None yet.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body"><form method="POST" action="{{ route('hr.organisation.store', 'grades') }}">@csrf<div class="form-grid">
    @include('hr::_in', ['name' => 'name', 'label' => 'New grade', 'val' => '', 'required' => true, 'ph' => 'e.g. Skilled worker'])
    @include('hr::_in', ['name' => 'rank', 'label' => 'Rank', 'type' => 'number', 'val' => ($grades->max('rank') ?? 0) + 1, 'required' => true, 'help' => 'Higher is more senior.'])
    @include('hr::_in', ['name' => 'min_salary', 'label' => 'Band from (PKR)', 'type' => 'number', 'val' => '']) @include('hr::_in', ['name' => 'max_salary', 'label' => 'Band to (PKR)', 'type' => 'number', 'val' => ''])
    @include('hr::_in', ['name' => 'code', 'label' => 'Code', 'val' => '', 'help' => $codeHelp])
</div><p><button class="btn btn-primary">Add grade</button></p></form></div></div>

{{-- Locations --}}
<div class="tab-marker" data-tab-start="Locations"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Location</th><th>Kind</th><th>City</th><th>People</th><th></th></tr></thead><tbody>
@forelse ($locations as $l)
<tr><td><strong>{{ $l->code }}</strong></td><td>{{ $l->name }} @unless ($l->active)<span class="status status-cancelled">Inactive</span>@endunless</td><td>{{ config('hr.location_kinds')[$l->kind] ?? $l->kind }}</td><td>{{ $l->city ?: '—' }}</td><td>{{ $employeeCounts['location_id'][$l->id] ?? 0 }}</td>
<td><details class="row-edit">@include('partials.icon-button', ['type' => 'edit', 'name' => $l->name, 'tag' => 'summary'])<form method="POST" action="{{ route('hr.organisation.update', ['locations', $l->id]) }}" class="form-grid">@csrf @method('PUT')
    @include('hr::_in', ['name' => 'code', 'label' => 'Code', 'val' => $l->code]) @include('hr::_in', ['name' => 'name', 'label' => 'Name', 'val' => $l->name, 'required' => true])
    @include('hr::_sel', ['name' => 'kind', 'label' => 'Kind', 'items' => config('hr.location_kinds'), 'sel' => $l->kind, 'blank' => false])
    @include('hr::_in', ['name' => 'city', 'label' => 'City', 'val' => $l->city]) @include('hr::_in', ['name' => 'address', 'label' => 'Address', 'val' => $l->address])
    <div class="field"><label><input type="checkbox" name="active" value="1" @checked($l->active)> Active</label></div><div class="field"><button class="btn btn-primary">Save</button></div></form>
<form method="POST" action="{{ route('hr.organisation.destroy', ['locations', $l->id]) }}" onsubmit="return confirm('Delete this location?')">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'name' => $l->name])</form></details></td></tr>
@empty<tr><td colspan="6" class="muted">None yet.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body"><form method="POST" action="{{ route('hr.organisation.store', 'locations') }}">@csrf<div class="form-grid">
    @include('hr::_in', ['name' => 'name', 'label' => 'New location', 'val' => '', 'required' => true, 'ph' => 'e.g. Main foundry'])
    @include('hr::_sel', ['name' => 'kind', 'label' => 'Kind', 'items' => config('hr.location_kinds'), 'sel' => 'PLANT', 'blank' => false])
    @include('hr::_in', ['name' => 'city', 'label' => 'City', 'val' => '']) @include('hr::_in', ['name' => 'code', 'label' => 'Code', 'val' => '', 'help' => $codeHelp])
</div><p><button class="btn btn-primary">Add location</button></p></form></div></div>

{{-- Cost centres --}}
<div class="tab-marker" data-tab-start="Cost centres"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Code</th><th>Cost centre</th><th>People</th><th></th></tr></thead><tbody>
@forelse ($costCentres as $c)
<tr><td><strong>{{ $c->code }}</strong></td><td>{{ $c->name }} @unless ($c->active)<span class="status status-cancelled">Inactive</span>@endunless</td><td>{{ $employeeCounts['cost_centre_id'][$c->id] ?? 0 }}</td>
<td><details class="row-edit">@include('partials.icon-button', ['type' => 'edit', 'name' => $c->name, 'tag' => 'summary'])<form method="POST" action="{{ route('hr.organisation.update', ['cost-centres', $c->id]) }}" class="form-grid">@csrf @method('PUT')
    @include('hr::_in', ['name' => 'code', 'label' => 'Code', 'val' => $c->code]) @include('hr::_in', ['name' => 'name', 'label' => 'Name', 'val' => $c->name, 'required' => true])
    <div class="field"><label><input type="checkbox" name="active" value="1" @checked($c->active)> Active</label></div><div class="field"><button class="btn btn-primary">Save</button></div></form>
<form method="POST" action="{{ route('hr.organisation.destroy', ['cost-centres', $c->id]) }}" onsubmit="return confirm('Delete this cost centre?')">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'name' => $c->name])</form></details></td></tr>
@empty<tr><td colspan="4" class="muted">None yet.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body"><form method="POST" action="{{ route('hr.organisation.store', 'cost-centres') }}">@csrf<div class="form-grid">
    @include('hr::_in', ['name' => 'name', 'label' => 'New cost centre', 'val' => '', 'required' => true, 'ph' => 'e.g. Melt shop'])
    @include('hr::_in', ['name' => 'code', 'label' => 'Code', 'val' => '', 'help' => $codeHelp])
</div><p><button class="btn btn-primary">Add cost centre</button></p></form></div></div>

{{-- Checklists --}}
<div class="tab-marker" data-tab-start="Checklists"></div>
@foreach (['ONBOARDING' => 'Joining checklist', 'EXIT' => 'Leaving checklist'] as $kind => $title)
<div class="panel"><div class="panel-header"><h2>{{ $title }}</h2><span class="muted small">Copied to each person when they join or leave</span></div>
<div class="table-wrap"><table><thead><tr><th>Task</th><th>Owner</th><th>Due (days after)</th><th></th></tr></thead><tbody>
@forelse ($checklists->get($kind, collect()) as $i)
<tr><td @unless ($i->active) class="muted" @endunless>{{ $i->title }}</td><td>{{ \App\Domains\Hr\Models\EmployeeTask::OWNERS[$i->owner] ?? $i->owner }}</td><td>{{ $i->due_days }}</td>
<td><details class="row-edit">@include('partials.icon-button', ['type' => 'edit', 'name' => $i->title, 'tag' => 'summary'])<form method="POST" action="{{ route('hr.checklist.update', $i) }}" class="form-grid">@csrf @method('PUT')
    @include('hr::_in', ['name' => 'title', 'label' => 'Task', 'val' => $i->title, 'required' => true])
    @include('hr::_sel', ['name' => 'owner', 'label' => 'Owner', 'items' => \App\Domains\Hr\Models\EmployeeTask::OWNERS, 'sel' => $i->owner, 'blank' => false])
    @include('hr::_in', ['name' => 'due_days', 'label' => 'Due after (days)', 'type' => 'number', 'val' => $i->due_days, 'required' => true])
    <div class="field"><label><input type="checkbox" name="active" value="1" @checked($i->active)> Use it</label></div><div class="field"><button class="btn btn-primary">Save</button></div></form>
<form method="POST" action="{{ route('hr.checklist.destroy', $i) }}" onsubmit="return confirm('Remove this item?')">@csrf @method('DELETE')@include('partials.icon-button', ['type' => 'delete', 'word' => 'Remove', 'name' => $i->title])</form></details></td></tr>
@empty<tr><td colspan="4" class="muted">Empty.</td></tr>@endforelse</tbody></table></div>
<div class="panel-body"><form method="POST" action="{{ route('hr.checklist.store') }}">@csrf<input type="hidden" name="kind" value="{{ $kind }}"><div class="form-grid">
    @include('hr::_in', ['name' => 'title', 'label' => 'Add a task', 'val' => '', 'required' => true])
    @include('hr::_sel', ['name' => 'owner', 'label' => 'Owner', 'items' => \App\Domains\Hr\Models\EmployeeTask::OWNERS, 'sel' => 'HR', 'blank' => false])
    @include('hr::_in', ['name' => 'due_days', 'label' => 'Due after (days)', 'type' => 'number', 'val' => 0, 'required' => true])
</div><p><button class="btn btn-primary">Add</button></p></form></div></div>
@endforeach
@if ($tab)<script>document.addEventListener('DOMContentLoaded', function () { var m = {departments:'Departments',designations:'Designations',grades:'Grades',locations:'Locations','cost-centres':'Cost centres',checklists:'Checklists'}[@json($tab)]; if (!m) return; var b = [].slice.call(document.querySelectorAll('.tabbar button')).filter(function (x) { return x.textContent.trim() === m; })[0]; if (b) b.click(); });</script>@endif
@endsection
