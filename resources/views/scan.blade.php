@extends('layouts.app')
@section('title', 'Scan or find')
@section('content')
@php $types = \App\Support\GlobalSearch::typesFor(auth()->user()); @endphp
<div class="page-heading"><div><h1>Scan or find</h1><p>Scan a barcode or type a code or name: a product, pattern, material, tooling code or engraved mark, a lot, heat, pour, order or enquiry.</p></div></div>
<div class="panel"><div class="panel-body">
<form method="GET" action="{{ route('scan') }}" style="display:flex;gap:8px;max-width:620px">
    <select name="type" style="width:auto"><option value="">Everything</option>@foreach ($types as $k => $l)<option value="{{ $k }}" @selected(($type ?? null) === $k)>{{ $l }}</option>@endforeach</select>
    <input name="code" value="{{ $code ?? '' }}" placeholder="Scan or type a code or name" autofocus autocomplete="off"><button class="btn btn-primary">Find</button>
</form>
@if (! empty($missing))<p class="error-text">Nothing has the code “{{ $code }}”.</p>@endif
</div>
@if (! empty($results))
    <div class="table-wrap"><table><thead><tr><th>Code</th><th>Name</th><th>Type</th></tr></thead><tbody>
        @foreach ($results as $r)
            <tr><td><a href="{{ $r['url'] }}">{{ $r['code'] }}</a></td><td>{{ $r['name'] ?? '—' }}</td><td>{{ $r['label'] }}</td></tr>
        @endforeach
    </tbody></table></div>
@endif
</div>
@endsection
