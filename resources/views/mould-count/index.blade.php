@extends('layouts.app')

@section('title', 'Count moulds')
@section('breadcrumb', 'Production / Count moulds')

@section('content')
<div class="page-heading">
    <div><h1>Count moulds before pouring</h1><p>Count what is on the floor for each pattern. Moulds short of the records are written off, and the orders need them again.</p></div>
    <div class="actions">@if (Route::has('quick.floor.index') && \App\Support\FloorRules::usesQuickScreens())<a class="btn" href="{{ route('quick.floor.index') }}">Moulds poured</a>@else<a class="btn" href="{{ route('pours.create') }}">New pour</a>@endif</div>
</div>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $m)<div>{{ $m }}</div>@endforeach</div>@endif

<div class="panel">
@if ($waiting->isEmpty())
    <div class="panel-body muted">No moulds are waiting for metal.</div>
@else
<form method="POST" action="{{ route('mould-count.store') }}">@csrf
    <div class="table-wrap"><table><thead><tr><th>Pattern</th><th>Waiting since</th><th>Records say</th><th>Counted</th><th>Short</th></tr></thead><tbody>
    @foreach ($waiting as $id => $w)
        <tr data-expected="{{ $w['expected'] }}">
            <td><strong>{{ $w['revision']->pattern->code }}</strong> {{ $w['revision']->pattern->name }}<div class="muted small">rev {{ $w['revision']->revision }}</div></td>
            <td>{{ $w['oldest'] ? \Illuminate\Support\Carbon::parse($w['oldest'])->displayShortDateTime() : '—' }}</td>
            <td>{{ $w['expected'] }}@if ($w['unassembled'])<div class="muted small">{{ $w['unassembled'] }} not assembled</div>@endif</td>
            <td><input type="number" name="counted[{{ $id }}]" min="0" max="100000" value="{{ old('counted.'.$id) }}" placeholder="{{ $w['expected'] }}" style="width:90px" aria-label="Moulds counted for {{ $w['revision']->pattern->code }}" data-count></td>
            <td data-short class="muted">—</td>
        </tr>
    @endforeach
    </tbody></table></div>
    <div class="panel-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <label>Why short<select name="reason"><option value="">—</option>@foreach (\App\Models\MouldCount::REASONS as $k => $l)<option value="{{ $k }}" @selected(old('reason') === $k)>{{ $l }}</option>@endforeach</select></label>
        <label style="flex:1;min-width:200px">Note<input name="note" maxlength="300" value="{{ old('note') }}" placeholder="e.g. two drags dropped by the crane"></label>
        <label>For heat<select name="heat_id"><option value="">—</option>@foreach ($heats as $h)<option value="{{ $h->id }}" @selected(old('heat_id') === $h->id)>{{ $h->heat_number }}</option>@endforeach</select></label>
        <button class="btn btn-primary">Save count</button>
    </div>
    <p class="panel-body muted small" style="margin:0">Leave a pattern empty to skip it. Moulds counted above the records are kept as counted; the pour records them.</p>
</form>
@endif
</div>

@if ($recent->isNotEmpty())
<div class="panel"><div class="panel-header"><h2>Recent counts</h2></div>
    <div class="table-wrap"><table><thead><tr><th>When</th><th>Pattern</th><th>Records</th><th>Counted</th><th>Written off</th><th>Heat</th><th>By</th></tr></thead><tbody>
    @foreach ($recent as $c)
        <tr><td>{{ $c->counted_at->displayShortDateTime() }}</td><td>{{ $c->patternRevision?->pattern?->code }}</td><td>{{ $c->expected }}</td><td>{{ $c->counted }}</td>
            <td>{{ $c->written_off ?: '—' }}@if ($c->reason)<div class="muted small">{{ \App\Models\MouldCount::REASONS[$c->reason] }}{{ $c->note ? ': '.$c->note : '' }}</div>@endif</td>
            <td>{{ $c->heat?->heat_number ?? '—' }}</td><td>{{ $c->counted_by }}</td></tr>
    @endforeach
    </tbody></table></div>
</div>
@endif

<script>
document.querySelectorAll('[data-count]').forEach(function (input) {
    input.addEventListener('input', function () {
        var row = input.closest('tr'), cell = row.querySelector('[data-short]'), expected = +row.dataset.expected;
        var short = input.value === '' ? 0 : expected - (+input.value);
        cell.textContent = short > 0 ? short : '—';
        cell.style.color = short > 0 ? '#b3352c' : '';
    });
});
</script>
@endsection
