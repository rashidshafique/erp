@extends('layouts.app')
@section('title', 'Sand systems')
@section('content')
<style>.sand-pick summary { cursor: pointer; font-size: 12.5px; color: var(--muted); margin-top: 4px; } .sand-pick label { display: flex; gap: 6px; align-items: center; font-weight: 400; white-space: nowrap; } .sand-pick input[type=checkbox] { accent-color: var(--accent); }</style>
<div class="page-heading"><div><h1>Sand systems</h1><p>The sands this foundry moulds with. They are offered on patterns, moulding stations, work lists and imports, in this order. The first active one is the default for a station with none set.</p></div>
<div class="actions"><button type="button" class="btn btn-primary" data-quick="qd-sand">+ Add sand system</button></div></div>
@include('partials.quick-dialog', ['id' => 'qd-sand', 'title' => 'Add a sand system', 'action' => route('sand-systems.store'), 'submit' => 'Add sand system', 'fields' => 'sand-systems._quick-fields'])
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
@if($errors->any())<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
@php
    // A sand system can keep an inactive sand it already uses; new choices are active sands only.
    $sandChoices = fn ($sd) => $baseSands->filter(fn ($b) => $b->status === 'ACTIVE' || $sd?->sands->contains('id', $b->id));
@endphp
<div class="panel"><div class="panel-header"><h2>Sand systems</h2></div><div class="table-wrap"><table><thead><tr><th>Order</th><th>Name</th><th>Type</th><th>Sands</th><th>Description</th><th>Status</th><th>Used by</th><th></th></tr></thead><tbody>
@foreach($sandSystems as $sd)@php $used = $sandUsage[$sd->id] ?? []; $main = $sd->mainSand(); @endphp
<form method="POST" action="{{ route('sand-systems.update', $sd) }}">@csrf @method('PUT')
<tr><td><input type="number" name="sort_order" min="0" value="{{ $sd->sort_order }}" style="width:70px"></td><td><input name="name" value="{{ $sd->name }}" required maxlength="100"></td>
<td><select name="system_type">@foreach($sandTypes as $k => $l)<option value="{{ $k }}" @selected($sd->system_type===$k)>{{ $l }}</option>@endforeach</select></td>
<td class="sand-pick"><select name="main_sand_id" aria-label="Main sand of {{ $sd->name }}"><option value="">— no main sand —</option>@foreach($sandChoices($sd) as $b)<option value="{{ $b->id }}" @selected($main?->id === $b->id)>{{ $b->name }}</option>@endforeach</select>
<details @if($sd->sands->count() > 1) open @endif><summary>Also uses{{ $sd->sands->count() > 1 ? ': '.$sd->sands->reject(fn ($b) => $b->id === $main?->id)->pluck('name')->implode(', ') : '' }}</summary>
@foreach($sandChoices($sd) as $b)<label><input type="checkbox" name="sand_ids[]" value="{{ $b->id }}" @checked($sd->sands->contains('id', $b->id) && $main?->id !== $b->id)> {{ $b->name }}</label>@endforeach</details></td>
<td><input name="description" value="{{ $sd->description }}" maxlength="1000"></td>
<td><select name="status"><option @selected($sd->status==='ACTIVE')>ACTIVE</option><option @selected($sd->status==='INACTIVE')>INACTIVE</option></select></td>
<td class="small">{{ $used ? collect($used)->map(fn ($n, $what) => "$n $what")->implode(', ') : '—' }}</td>
<td style="white-space:nowrap"><button class="btn">Save</button>@unless($used) <button class="btn btn-danger" form="del-sand-{{ $sd->id }}" onclick="return confirm('Delete {{ $sd->name }}?')">Delete</button>@endunless</td></tr></form>
@endforeach</tbody></table></div>
@foreach($sandSystems as $sd)<form id="del-sand-{{ $sd->id }}" method="POST" action="{{ route('sand-systems.destroy', $sd) }}">@csrf @method('DELETE')</form>@endforeach
<div class="panel-body"><p class="muted small">Renaming a sand system changes it everywhere it is recorded. A sand system in use cannot be deleted; set it to inactive to stop offering it while keeping the records that use it.</p></div></div>

<div class="panel" id="base-sands"><div class="panel-header"><h2>Sands</h2><span class="muted small">The grain each sand system is made of. Link a sand to its raw material to follow it in inventory.</span></div>
<div class="table-wrap"><table><thead><tr><th>Order</th><th>Name</th><th>Description</th><th>Inventory material</th><th>Status</th><th>Used by</th><th></th></tr></thead><tbody>
@foreach($baseSands as $b)
<form method="POST" action="{{ route('sand-systems.base.update', $b) }}">@csrf @method('PUT')
<tr><td><input type="number" name="sort_order" min="0" value="{{ $b->sort_order }}" style="width:70px"></td><td><input name="name" value="{{ $b->name }}" required maxlength="100"></td>
<td><input name="description" value="{{ $b->description }}" maxlength="1000" style="min-width:260px"></td>
<td><select name="material_id" aria-label="Inventory material for {{ $b->name }}"><option value="">— not linked —</option>@foreach($sandMaterials as $m)<option value="{{ $m->id }}" @selected($b->material_id === $m->id)>{{ $m->code }} · {{ $m->name }}{{ $m->status !== 'ACTIVE' ? ' ('.strtolower($m->status).')' : '' }}</option>@endforeach</select>
@if($b->material)<div class="small"><a href="{{ route('materials.show', $b->material) }}">Open {{ $b->material->code }} in inventory</a></div>@endif</td>
<td><select name="status"><option @selected($b->status==='ACTIVE')>ACTIVE</option><option @selected($b->status==='INACTIVE')>INACTIVE</option></select></td>
<td class="small">{{ $b->sand_systems_count ? $b->sand_systems_count.' sand system'.($b->sand_systems_count === 1 ? '' : 's') : '—' }}</td>
<td style="white-space:nowrap"><button class="btn">Save</button>@unless($b->sand_systems_count) <button class="btn btn-danger" form="del-base-{{ $b->id }}" onclick="return confirm('Delete {{ $b->name }}?')">Delete</button>@endunless</td></tr></form>
@endforeach</tbody></table></div>
@foreach($baseSands as $b)<form id="del-base-{{ $b->id }}" method="POST" action="{{ route('sand-systems.base.destroy', $b) }}">@csrf @method('DELETE')</form>@endforeach
<div class="panel-body"><form method="POST" action="{{ route('sand-systems.base.store') }}" class="form-grid">@csrf
<label>Name<input name="name" required maxlength="100" placeholder="e.g. Reclaimed silica"></label>
<label>Inventory material<select name="material_id"><option value="">— not linked —</option>@foreach($sandMaterials as $m)<option value="{{ $m->id }}">{{ $m->code }} · {{ $m->name }}</option>@endforeach</select></label>
<label>Description<input name="description" maxlength="1000"></label>
<div><button class="btn btn-primary">+ Add sand</button></div></form>
@if($sandMaterials->isEmpty())<p class="muted small">No raw materials of type Sand yet. <a href="{{ route('materials.create') }}">Add one under Materials</a>, then link it here.</p>@else<p class="muted small">Only raw materials of type Sand are listed, and each can back one sand.</p>@endif
</div></div>
@endsection
