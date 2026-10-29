@extends('layouts.app')

@section('title', 'After pour')
@section('breadcrumb', 'Production / After pour')

@section('content')
<div class="page-heading">
    <div><h1>After pour</h1><p>Castings between the pour and quality control. Record each step as the floor does it: a batch ("20 moulds") or everything waiting. Leave the quantity empty to move all.</p></div>
    <div class="actions">@if (Route::has('quick-qc.index'))<a class="btn" href="{{ route('quick-qc.index') }}">Quick QC</a>@endif</div>
</div>
@if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $m)<div>{{ $m }}</div>@endforeach</div>@endif
<datalist id="defect-names">@foreach ($defects as $name)<option value="{{ $name }}">@endforeach</datalist>

@forelse ($rows as $stepRows)
    @php $step = $stepRows->first()['step']; @endphp
    <div class="panel">
        <div class="panel-header"><h2>{{ $step->name }}</h2><span class="muted small">{{ \App\Support\Formats::number($stepRows->sum('pieces'), 0) }} pieces waiting</span></div>
        <div class="table-wrap"><table><thead><tr><th>Product</th><th>Waiting</th><th>Poured</th><th>Record</th></tr></thead><tbody>
        @foreach ($stepRows as $r)
            @php $cooling = $r['ready_at'] && $r['ready_at']->isFuture(); @endphp
            <tr>
                <td><strong>{{ $r['product']?->code }}</strong> {{ $r['product']?->name }}<div class="muted small">{{ $r['lots']->count() }} {{ \Illuminate\Support\Str::plural('lot', $r['lots']->count()) }}@if ($r['on_hold']) · {{ $r['on_hold'] }} pieces on hold @endif</div></td>
                <td>{{ $r['pieces'] }} pcs @if ($r['moulds'] !== null)<div class="muted small">≈ {{ $r['moulds'] }} moulds</div>@endif
                    @if ($cooling)<div><span class="status status-on-hold">cooling until {{ $r['ready_at']->displayShortDateTime() }}</span></div>@endif</td>
                <td>{{ $r['oldest'] ? \Illuminate\Support\Carbon::parse($r['oldest'])->displayShortDateTime() : '—' }}</td>
                <td>@if ($r['pieces'] > 0)
                    <form method="POST" action="{{ route('after-pour.store') }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">@csrf
                        <input type="hidden" name="stage_definition_id" value="{{ $step->id }}"><input type="hidden" name="product_revision_id" value="{{ $r['product_revision_id'] }}">
                        <input type="number" name="quantity" min="1" placeholder="all" style="width:80px" aria-label="How many">
                        <select name="unit" aria-label="Unit"><option value="PIECES">pieces</option>@if ($r['moulds'] !== null)<option value="MOULDS">moulds</option>@endif</select>
                        <input type="number" name="rejected" min="0" placeholder="rejects" style="width:80px" aria-label="Rejected pieces">
                        <input name="rejection_reason" list="defect-names" maxlength="100" placeholder="why rejected" style="width:140px" aria-label="Why rejected">
                        @if ($cooling)<input name="cooling_override_reason" maxlength="300" placeholder="reason to knock out early" style="width:180px" aria-label="Reason to knock out early">@endif
                        <button class="btn btn-primary btn-small">{{ $step->name }} done</button>
                    </form>
                @endif</td>
            </tr>
        @endforeach
        </tbody></table></div>
    </div>
@empty
    <div class="panel"><div class="panel-body muted">Nothing waits between the pour and quality control.</div></div>
@endforelse
@endsection
