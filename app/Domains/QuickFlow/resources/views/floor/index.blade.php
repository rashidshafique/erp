@extends('layouts.app')

@section('title', 'Moulds made and poured')
@section('breadcrumb', 'Quick flow / Moulds made and poured')

@php
    $label = fn ($o) => $o['order']->production_order_number.' · '.$o['order']->product?->code.' · '.$o['customer'].' · '.$o['ordered'].' pcs'.($o['due'] ? ', due '.$o['due']->displayShortDate() : '');
@endphp

@section('content')
<div class="page-heading">
    <div><h1>Moulds made and poured</h1><p>Type in what the floor did. Each entry also writes the moulding run or pour record.</p></div>
    <div class="actions"><a class="btn" href="{{ route('quick.heat.index') }}">Next heat</a></div>
</div>
@if ($errors->any())<div class="alert alert-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:16px">
<div class="panel"><div class="panel-header"><h2>Moulds made</h2></div><div class="panel-body">
    <form method="POST" action="{{ route('quick.floor.made') }}">@csrf
        <div class="field"><label for="m_order">Order *</label><select id="m_order" name="production_order_id" required><option value="">—</option>@foreach ($orders as $o)<option value="{{ $o['order']->id }}" data-ppm="{{ $lastPerMould[$o['order']->id] ?? 1 }}">{{ $label($o) }}</option>@endforeach</select></div>
        <div class="form-grid">
            <div class="field"><label for="m_moulds">Moulds *</label><input id="m_moulds" type="number" min="1" name="moulds" required></div>
            <div class="field"><label for="m_ppm">Pieces per mould *</label><input id="m_ppm" type="number" min="1" name="pieces_per_mould" value="1" required></div>
            <div class="field"><label for="m_date">Date *</label><input id="m_date" type="date" name="entry_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
            <div class="field"><label for="m_by">By</label><input id="m_by" name="entered_by" maxlength="100"></div>
        </div>
        <button class="btn btn-primary" type="submit">Save moulds made</button>
    </form>
</div></div>

<div class="panel"><div class="panel-header"><h2>Moulds poured</h2></div><div class="panel-body">
    <form method="POST" action="{{ route('quick.floor.poured') }}">@csrf
        <div class="field"><label for="p_heat">Heat *</label><select id="p_heat" name="heat_id" required><option value="">—</option>@foreach ($heats as $h)
            @php $t = $h->thermalTests->first(); @endphp
            <option value="{{ $h->id }}">{{ $h->heat_number }} · {{ $h->ironGrade?->code }} · {{ $h->furnace?->code }} · thermal: {{ $t ? strtolower(str_replace('_', ' ', $t->result)) : 'none yet' }}</option>@endforeach</select></div>
        <div class="field"><label for="p_order">Order *</label><select id="p_order" name="production_order_id" required><option value="">—</option>@foreach ($awaiting as $o)<option value="{{ $o['order']->id }}" data-ppm="{{ $lastPerMould[$o['order']->id] ?? 1 }}">{{ $label($o) }} · {{ $o['awaiting'] }} pcs moulded, not poured</option>@endforeach</select></div>
        <div class="form-grid">
            <div class="field"><label for="p_moulds">Moulds *</label><input id="p_moulds" type="number" min="1" name="moulds" required></div>
            <div class="field"><label for="p_ppm">Pieces per mould *</label><input id="p_ppm" type="number" min="1" name="pieces_per_mould" value="1" required></div>
            <div class="field"><label for="p_date">Date *</label><input id="p_date" type="date" name="entry_date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
            <div class="field"><label for="p_by">By</label><input id="p_by" name="entered_by" maxlength="100"></div>
        </div>
        <button class="btn btn-primary" type="submit">Save moulds poured</button>
        <p class="muted small">A missing or failed thermal test shows a warning and is noted on the pour. It does not stop the pour being recorded.</p>
    </form>
</div></div>
</div>

<div class="panel"><div class="panel-header"><h2>Heats in progress</h2></div>
    @forelse ($heats as $h)
        @php $t = $h->thermalTests->first(); @endphp
        <div class="panel-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">
            <div><a href="{{ route('heats.show', $h) }}"><strong>{{ $h->heat_number }}</strong></a> · {{ $h->ironGrade?->code }} · {{ $h->furnace?->code }}
                <span class="muted small">{{ $t ? 'Last thermal test: '.strtolower(str_replace('_', ' ', $t->result)).' at '.$t->taken_at->format('H:i') : 'No thermal test yet' }}</span></div>
            <a class="btn" href="{{ route('thermal.create', ['heat_id' => $h->id]) }}">Record thermal test</a>
        </div>
    @empty
        <div class="panel-body muted">No heats yet. Start one from the next heat page.</div>
    @endforelse
</div>

<div class="panel"><div class="panel-header"><h2>Recent entries</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th></th><th>Order</th><th>Heat</th><th>Moulds</th><th>Pieces</th></tr></thead><tbody>
        @forelse ($recent as $e)<tr><td>{{ $e->entry_date->displayShortDate() }}</td><td>{{ $e->kind === 'MADE' ? 'Made' : 'Poured' }}</td><td>{{ $e->productionOrder?->production_order_number }} {{ $e->productionOrder?->product?->code }}</td><td>{{ $e->heat?->heat_number }}</td><td>{{ $e->moulds }}</td><td>{{ $e->pieces() }}</td></tr>
        @empty<tr><td colspan="6" class="muted">Nothing entered yet.</td></tr>@endforelse
    </tbody></table></div>
</div>
<script>
    document.querySelectorAll('#m_order, #p_order').forEach(function (select) {
        select.addEventListener('change', function () {
            var input = document.getElementById(select.id === 'm_order' ? 'm_ppm' : 'p_ppm');
            var chosen = select.options[select.selectedIndex];
            if (chosen && chosen.dataset.ppm) { input.value = chosen.dataset.ppm; }
        });
    });
</script>
@endsection
