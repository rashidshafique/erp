@extends('layouts.app')
@section('title', 'Sand systems')
@section('content')
@include('partials.editable-rows')
<style>.sand-pick summary { cursor: pointer; font-size: 12.5px; color: var(--muted); margin-top: 4px; } .sand-pick label { display: flex; gap: 6px; align-items: center; font-weight: 400; white-space: nowrap; } .sand-pick input[type=checkbox] { accent-color: var(--accent); }</style>
<div class="page-heading"><div><h1>Sand systems</h1><p>The sands this foundry moulds with. They are offered on patterns, moulding stations, work lists and imports, in this order. The first active one is the default for a station with none set.</p></div>
<div class="actions"><button type="button" class="btn btn-primary" data-quick="qd-sand">+ Add sand system</button><button type="button" class="btn" data-quick="qd-base">+ Add sand</button></div></div>
@include('partials.quick-dialog', ['id' => 'qd-sand', 'title' => 'Add a sand system', 'action' => route('sand-systems.store'), 'submit' => 'Add sand system', 'fields' => 'sand-systems._quick-fields'])
@include('partials.quick-dialog', ['id' => 'qd-base', 'title' => 'Add a sand', 'action' => route('sand-systems.base.store'), 'submit' => 'Add sand', 'fields' => 'sand-systems._quick-base-fields'])
@if(session("success"))<div class="alert alert-success">{{ session("success") }}</div>@endif
@php
    // A sand system can keep an inactive sand it already uses; new choices are active sands only.
    $sandChoices = fn ($sd) => $baseSands->filter(fn ($b) => $b->status === 'ACTIVE' || $sd?->sands->contains('id', $b->id));
    $chip = fn ($s) => $s === 'ACTIVE' ? 'status-completed' : 'status-on-hold';
@endphp

<div class="tab-marker" data-tab-start="Sand systems"></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Order</th><th>Name</th><th>Type</th><th>Sands</th><th>Description</th><th title="When a mould's flasks are free again; patterns, work lists, runs and pours can change it">Flasks freed</th><th>Status</th><th>Used by</th><th></th></tr></thead><tbody>
@forelse($sandSystems as $sd)
@php
    $used = $sandUsage[$sd->id] ?? []; $main = $sd->mainSand(); $rowKey = 'sd-'.$sd->id; $editing = old('_row') === $rowKey; $f = 'f-'.$rowKey;
    $val = fn ($k, $d) => $editing ? old($k, $d) : $d;
    $others = $sd->sands->reject(fn ($b) => $b->id === $main?->id);
    $picked = $editing ? (array) old('sand_ids', []) : $others->pluck('id')->all();
@endphp
<tr data-editable-row class="{{ $editing ? 'is-editing' : '' }}">
<td><span class="ro">{{ $sd->sort_order }}</span><span class="ed"><input form="{{ $f }}" type="number" name="sort_order" min="0" value="{{ $val('sort_order', $sd->sort_order) }}" style="width:56px"></span></td>
<td><span class="ro"><strong>{{ $sd->name }}</strong></span><span class="ed"><input form="{{ $f }}" name="name" value="{{ $val('name', $sd->name) }}" required maxlength="100" style="width:140px"></span></td>
<td><span class="ro">{{ $sandTypes[$sd->system_type] ?? $sd->system_type }}</span><span class="ed"><select form="{{ $f }}" name="system_type" style="width:120px">@foreach($sandTypes as $k => $l)<option value="{{ $k }}" @selected($val('system_type', $sd->system_type) === $k)>{{ $l }}</option>@endforeach</select></span></td>
<td class="sand-pick" style="max-width:190px">
    <span class="ro">{{ $main?->name ?? '—' }}@if($others->isNotEmpty())<div class="muted small">also: {{ $others->pluck('name')->implode(', ') }}</div>@endif</span>
    <div class="ed"><select form="{{ $f }}" name="main_sand_id" aria-label="Main sand of {{ $sd->name }}" style="width:140px"><option value="">— no main sand —</option>@foreach($sandChoices($sd) as $b)<option value="{{ $b->id }}" @selected($val('main_sand_id', $main?->id) === $b->id)>{{ $b->name }}</option>@endforeach</select>
    <details @if($picked) open @endif><summary>Also uses</summary>
    @foreach($sandChoices($sd) as $b)<label><input form="{{ $f }}" type="checkbox" name="sand_ids[]" value="{{ $b->id }}" @checked(in_array($b->id, $picked, true))> {{ $b->name }}</label>@endforeach</details></div></td>
<td class="col-desc"><span class="ro clip" title="{{ $sd->description }}">{{ $sd->description ?: '—' }}</span><span class="ed"><input form="{{ $f }}" name="description" value="{{ $val('description', $sd->description) }}" maxlength="1000" style="width:100%"></span></td>
<td><span class="ro">{{ $sd->flasks_freed ? \App\Services\Production\FlaskManager::FREED[$sd->flasks_freed] : 'Flask board rule' }}</span><span class="ed"><select form="{{ $f }}" name="flasks_freed" style="width:150px"><option value="">Flask board rule</option>@foreach(\App\Services\Production\FlaskManager::FREED as $k => $l)<option value="{{ $k }}" @selected($val('flasks_freed', $sd->flasks_freed) === $k)>{{ $l }}</option>@endforeach</select></span></td>
<td><span class="ro"><span class="status {{ $chip($sd->status) }}">{{ $sd->status }}</span></span><span class="ed"><select form="{{ $f }}" name="status" style="width:110px"><option @selected($val('status', $sd->status)==='ACTIVE')>ACTIVE</option><option @selected($val('status', $sd->status)==='INACTIVE')>INACTIVE</option></select></span></td>
<td class="small" style="max-width:150px">{{ $used ? collect($used)->map(fn ($n, $what) => "$n $what")->implode(', ') : '—' }}</td>
<td style="white-space:nowrap">
    <span class="ro ro-act">@include('partials.icon-button', ['type' => 'edit', 'name' => $sd->name, 'buttonType' => 'button', 'data' => ['row-edit' => true]])@unless($used)@include('partials.icon-button', ['type' => 'delete', 'name' => $sd->name, 'form' => 'del-sand-'.$sd->id, 'confirm' => "Delete {$sd->name}?"])@endunless</span>
    <span class="ed ed-act"><button type="submit" form="{{ $f }}" class="btn btn-small btn-primary">Save</button><button type="button" form="{{ $f }}" class="btn btn-small" data-row-cancel>Cancel</button></span></td></tr>
@empty
<tr><td colspan="9" class="muted" style="padding:16px">No sand systems yet. Press “+ Add sand system” to add the first one.</td></tr>
@endforelse
</tbody></table></div>
@foreach($sandSystems as $sd)
<form id="f-sd-{{ $sd->id }}" method="POST" action="{{ route('sand-systems.update', $sd) }}">@csrf @method('PUT')<input type="hidden" name="_row" value="sd-{{ $sd->id }}"></form>
<form id="del-sand-{{ $sd->id }}" method="POST" action="{{ route('sand-systems.destroy', $sd) }}">@csrf @method('DELETE')</form>
@endforeach
<div class="panel-body"><p class="muted small">Renaming a sand system changes it everywhere it is recorded. A sand system in use cannot be deleted; set it to inactive to stop offering it while keeping the records that use it.</p></div></div>

<div class="tab-marker" data-tab-start="Sands"></div>
<div class="panel" id="base-sands"><div class="panel-header"><h2>Sands</h2><span class="muted small">The grain each sand system is made of. Link a sand to its raw material to follow it in inventory.</span></div>
<div class="table-wrap"><table><thead><tr><th>Order</th><th>Name</th><th>Description</th><th>Inventory material</th><th>Status</th><th>Used by</th><th></th></tr></thead><tbody>
@forelse($baseSands as $b)
@php
    $rowKey = 'bs-'.$b->id; $editing = old('_row') === $rowKey; $f = 'f-'.$rowKey;
    $val = fn ($k, $d) => $editing ? old($k, $d) : $d;
@endphp
<tr data-editable-row class="{{ $editing ? 'is-editing' : '' }}">
<td><span class="ro">{{ $b->sort_order }}</span><span class="ed"><input form="{{ $f }}" type="number" name="sort_order" min="0" value="{{ $val('sort_order', $b->sort_order) }}" style="width:56px"></span></td>
<td><span class="ro"><strong>{{ $b->name }}</strong></span><span class="ed"><input form="{{ $f }}" name="name" value="{{ $val('name', $b->name) }}" required maxlength="100" style="width:140px"></span></td>
<td class="col-desc"><span class="ro clip" title="{{ $b->description }}">{{ $b->description ?: '—' }}</span><span class="ed"><input form="{{ $f }}" name="description" value="{{ $val('description', $b->description) }}" maxlength="1000" style="width:100%"></span></td>
<td><span class="ro">@if($b->material)<a href="{{ route('materials.show', $b->material) }}">{{ $b->material->code }} · {{ $b->material->name }}</a>@else —@endif</span>
    <span class="ed"><select form="{{ $f }}" name="material_id" aria-label="Inventory material for {{ $b->name }}" style="width:190px"><option value="">— not linked —</option>@foreach($sandMaterials as $m)<option value="{{ $m->id }}" @selected($val('material_id', $b->material_id) === $m->id)>{{ $m->code }} · {{ $m->name }}{{ $m->status !== 'ACTIVE' ? ' ('.strtolower($m->status).')' : '' }}</option>@endforeach</select></span></td>
<td><span class="ro"><span class="status {{ $chip($b->status) }}">{{ $b->status }}</span></span><span class="ed"><select form="{{ $f }}" name="status" style="width:110px"><option @selected($val('status', $b->status)==='ACTIVE')>ACTIVE</option><option @selected($val('status', $b->status)==='INACTIVE')>INACTIVE</option></select></span></td>
<td class="small">{{ $b->sand_systems_count ? $b->sand_systems_count.' sand system'.($b->sand_systems_count === 1 ? '' : 's') : '—' }}</td>
<td style="white-space:nowrap">
    <span class="ro ro-act">@include('partials.icon-button', ['type' => 'edit', 'name' => $b->name, 'buttonType' => 'button', 'data' => ['row-edit' => true]])@unless($b->sand_systems_count)@include('partials.icon-button', ['type' => 'delete', 'name' => $b->name, 'form' => 'del-base-'.$b->id, 'confirm' => "Delete {$b->name}?"])@endunless</span>
    <span class="ed ed-act"><button type="submit" form="{{ $f }}" class="btn btn-small btn-primary">Save</button><button type="button" form="{{ $f }}" class="btn btn-small" data-row-cancel>Cancel</button></span></td></tr>
@empty
<tr><td colspan="7" class="muted" style="padding:16px">No sands yet. Press “+ Add sand” to add one (for example silica or chromite).</td></tr>
@endforelse
</tbody></table></div>
@foreach($baseSands as $b)
<form id="f-bs-{{ $b->id }}" method="POST" action="{{ route('sand-systems.base.update', $b) }}">@csrf @method('PUT')<input type="hidden" name="_row" value="bs-{{ $b->id }}"></form>
<form id="del-base-{{ $b->id }}" method="POST" action="{{ route('sand-systems.base.destroy', $b) }}">@csrf @method('DELETE')</form>
@endforeach
<div class="panel-body">@if($sandMaterials->isEmpty())<p class="muted small">No raw materials of type Sand yet. <a href="{{ route('materials.create') }}">Add one under Materials</a>, then link it here.</p>@else<p class="muted small">Only raw materials of type Sand are listed, and each can back one sand.</p>@endif</div></div>
@endsection
