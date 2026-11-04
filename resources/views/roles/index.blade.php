@extends('layouts.app')
@section('title', 'Roles and permissions')
@section('content')
<div class="page-heading"><div><h1>Roles and permissions</h1><p>Administrators have everything. Anyone signed in can read an area unless "Limit viewing" is ticked for it (this applies to every role). Tick what a role may do in each area.</p></div></div>

<div class="panel"><div class="panel-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
    <form method="GET" action="{{ route('roles.index') }}" style="display:flex;gap:8px;align-items:end">
        <label>Role<select name="role" onchange="this.form.submit()">@foreach($roles as $r)<option value="{{ $r->id }}" @selected($r->id === $role?->id)>{{ $r->name }}</option>@endforeach</select></label>
        <label>Start from<select name="copy" onchange="this.form.submit()"><option value="">this role's own</option>@foreach($roles->where('id', '!=', $role?->id) as $r)<option value="{{ $r->id }}" @selected($copying?->id === $r->id)>{{ $r->name }}</option>@endforeach</select></label>
    </form>
    <form method="POST" action="{{ route('roles.store') }}" style="display:flex;gap:8px;align-items:end;margin-left:auto">@csrf
        <label>New role<input name="name" maxlength="100" placeholder="e.g. Fettling lead" required></label>
        <label>Copy from<select name="copy"><option value="">nothing</option>@foreach($roles as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach</select></label>
        <button class="btn">Add role</button>
    </form>
</div></div>

@if($role && $role->code === 'ADMIN')
    <div class="panel"><div class="panel-body">Administrators can do everything; this cannot be changed.</div></div>
@elseif($role)
<form method="POST" action="{{ route('roles.update') }}">@csrf @method('PUT')
<input type="hidden" name="roles[]" value="{{ $role->id }}"><input type="hidden" name="limits_shown" value="1">
@if($copying)<div class="panel"><div class="panel-body">Showing what <strong>{{ $copying->name }}</strong> may do. Nothing changes for {{ $role->name }} until you save.</div></div>@endif

<div class="panel"><div class="panel-body"><strong>What {{ $role->name }} can do</strong>
    <div style="margin-top:6px;display:flex;flex-direction:column;gap:2px">
    @forelse($summary as $g)<div>{{ $g['label'] }}: <strong>{{ $g['done'] === $g['of'] ? 'everything' : $g['done'].' of '.$g['of'].' actions' }}</strong></div>@empty<div class="muted">Nothing beyond reading pages.</div>@endforelse
    <div class="muted small" style="margin-top:4px">Sees prices: {{ $fields['prices'] ? 'yes' : 'no' }} · sees costs: {{ $fields['costs'] ? 'yes' : 'no' }}</div>
    </div></div></div>

@foreach($tree as $permission => $group)
<details class="panel" @if(collect($group['areas'])->contains(fn ($a, $n) => collect($a['actions'])->contains(fn ($x) => $has($permission, $n, $x)))) open @endif>
    <summary class="panel-body" style="cursor:pointer"><strong>{{ $group['label'] }}</strong> <span class="muted small">{{ $permission }}</span></summary>
    <div class="table-wrap"><table data-grid>
        <thead><tr><th>Area</th>@foreach($actions as $k => $label)<th style="text-align:center">{{ $label }}</th>@endforeach<th style="text-align:center">All</th><th style="text-align:center" title="Applies to every role">Limit viewing</th><th style="text-align:center" title="This role sees and changes only the records its people created">Own only</th></tr></thead>
        <tbody>@foreach($group['areas'] as $area => $def)
            <tr><td>{{ $def['label'] }}<div class="muted small">{{ $area }}</div></td>
            @foreach($actions as $k => $label)<td style="text-align:center">@if(in_array($k, $def['actions'], true))<input type="checkbox" name="perm[{{ $role->id }}][{{ $area }}:{{ $k }}]" value="1" @checked($has($permission, $area, $k))>@else<span class="muted">·</span>@endif</td>@endforeach
            <td style="text-align:center"><input type="checkbox" data-row title="Every action of {{ $def['label'] }}"></td>
            <td style="text-align:center">@if(collect(config('permissions.routes'))->contains(fn ($r) => \App\Support\AccessMap::areaOf($r) === $area && ! empty($r['reads'])))<span class="muted" title="Always limited">always</span>@else<input type="checkbox" name="limit[{{ $area }}]" value="1" @checked(in_array($area, $limited, true)) title="Only roles with View may open its pages. Applies to every role.">@endif</td>
            <td style="text-align:center">@if(isset(config('permissions.own')[$area]))<input type="checkbox" name="perm[{{ $role->id }}][{{ $area }}:own]" value="1" @checked(in_array($area.':own', $held, true)) title="Only the records its people created">@endif</td></tr>
        @endforeach</tbody>
        <tfoot><tr><td><em>Everything in this group</em></td><td colspan="{{ count($actions) }}"></td><td style="text-align:center"><input type="checkbox" data-all></td><td></td><td></td></tr></tfoot>
    </table></div>
</details>
@endforeach

@if($plain->isNotEmpty())
<div class="panel"><div class="panel-body"><strong>Other permissions</strong></div><div class="table-wrap"><table><tbody>
@foreach($plain as $key => $label)<tr><td>{{ $label }}<div class="muted small">{{ $key }}</div></td><td style="text-align:center;width:80px"><input type="checkbox" name="perm[{{ $role->id }}][{{ $key }}]" value="1" @checked(in_array($key, $held, true))></td></tr>@endforeach
</tbody></table></div></div>
@endif

<div class="panel"><div class="panel-body"><button class="btn btn-primary">Save {{ $role->name }}</button></div></div>
</form>
<script>
document.querySelectorAll('table[data-grid]').forEach(function (t) {
    var cells = function (scope) { return scope.querySelectorAll('input[type=checkbox][name^="perm["]'); };
    t.querySelectorAll('[data-row]').forEach(function (b) {
        var tr = b.closest('tr'); b.checked = cells(tr).length > 0 && Array.from(cells(tr)).every(function (c) { return c.checked; });
        b.addEventListener('change', function () { cells(tr).forEach(function (c) { c.checked = b.checked; }); });
    });
    var all = t.querySelector('[data-all]');
    all.checked = Array.from(cells(t)).every(function (c) { return c.checked; });
    all.addEventListener('change', function () { cells(t).forEach(function (c) { c.checked = all.checked; }); t.querySelectorAll('[data-row]').forEach(function (b) { b.checked = all.checked; }); });
});
</script>
@endif
@endsection
