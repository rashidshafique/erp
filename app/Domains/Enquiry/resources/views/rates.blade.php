@extends('layouts.app')
@section('title', 'Quotation rates')
@section('content')
<div class="page-heading"><div><h1>Quotation rates</h1><p>The costs behind every quotation, in {{ $currency }}.</p></div></div>
@if ($unset)<div class="alert alert-error">Grey figures are placeholders. Replace them with your own costs before a real quotation.</div>@endif
@php
    $sections = [
        'Process' => ['melt_grey_per_kg', 'melt_ductile_per_kg', 'moulding_per_kg', 'core_each', 'fettling_per_kg', 'heat_treatment_per_kg', 'testing_each', 'ndt_each', 'packing_each'],
        'Margins' => ['overhead_pct', 'margin_pct', 'min_margin_pct', 'tooling_markup_pct'],
        'Assumptions' => ['default_yield_pct', 'scrap_default_pct'],
    ];
@endphp
<form method="POST" action="{{ route('settings.quotation.update') }}">@csrf @method('PUT')
<div class="panel"><div class="panel-body">
@foreach ($sections as $heading => $keys)
<h3 class="rate-section-heading">{{ $heading }}</h3>
<div class="form-grid">
@foreach ($keys as $k)
@php [$label, $help] = $labels[$k]; @endphp
<div class="field"><label for="r_{{ $k }}">{{ $label }}</label><input id="r_{{ $k }}" type="number" step="any" min="0" name="{{ $k }}" value="{{ old($k, $rates[$k]) }}" required @if (in_array($k, $unset, true) && old($k) === null) class="rate-placeholder" title="Placeholder: not set yet" oninput="this.classList.remove('rate-placeholder')" @endif>@if ($help)<div class="help">{{ $help }}</div>@endif</div>
@endforeach
</div>
@endforeach
</div></div>
<p><button class="btn btn-primary" type="submit">Save rates</button></p></form>
@push('styles')<style>.rate-placeholder { color: var(--muted); font-style: italic; } .rate-section-heading { margin: 1.5rem 0 .5rem; } .rate-section-heading:first-child { margin-top: 0; }</style>@endpush
@endsection
