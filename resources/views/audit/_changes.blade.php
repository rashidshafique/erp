@php $set = $entry->changeSet(); @endphp
@if ($entry->action === 'CREATED')
    <span class="muted small">{{ count($set) }} field(s) set</span>
    <details><summary class="small">show</summary>@foreach ($set as $k => $v)<div class="small"><strong>{{ $k }}</strong>: {{ is_scalar($v['new'] ?? null) || ($v['new'] ?? null) === null ? ($v['new'] ?? '—') : json_encode($v['new']) }}</div>@endforeach</details>
@elseif ($entry->action === 'DELETED')
    <span class="muted small">record removed</span>
@else
    @foreach ($set as $k => $v)<div class="small"><strong>{{ $k }}</strong>: <span class="muted">{{ is_scalar($v['old'] ?? null) || ($v['old'] ?? null) === null ? (($v['old'] ?? null) === null ? '—' : $v['old']) : json_encode($v['old']) }}</span> → {{ is_scalar($v['new'] ?? null) || ($v['new'] ?? null) === null ? (($v['new'] ?? null) === null ? '—' : $v['new']) : json_encode($v['new']) }}</div>@endforeach
@endif
@if ($entry->reason)<div class="small"><em>Reason: {{ $entry->reason }}</em></div>@endif
