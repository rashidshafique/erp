@extends('layouts.app')

@section('title', $core->code)
@section('breadcrumb')<a href="{{ route('cores.index') }}">Cores</a> / {{ $core->code }} @endsection

@section('content')
<div class="page-heading">
    <div><h1>{{ $core->name }}</h1><p>{{ \App\Models\CoreDefinition::TYPES[$core->core_type] ?? $core->core_type }} · <span class="mono">{{ $core->code }}</span> · <span class="status {{ $core->status === 'ACTIVE' ? 'status-completed' : 'status-cancelled' }}">{{ $core->status }}</span></p></div>
    <div class="actions">
        @if ($core->trashed())
            <form method="POST" action="{{ route('cores.restore', $core) }}" style="display:inline">@csrf<button class="btn btn-primary" type="submit">Restore core</button></form>
        @else
            <a class="btn" href="{{ route('core-boxes.create', ['core' => $core->id]) }}">+ Core box</a>
            <a class="btn" href="{{ route('cores.edit', $core) }}">Edit</a>
            <form method="POST" action="{{ route('cores.archive', $core) }}" style="display:inline" onsubmit="return confirm(@js('Archive '.$core->code.'? It leaves the core list and pickers and can be restored.'))">@csrf @method('DELETE')<button class="btn" type="submit">Archive</button></form>
        @endif
    </div>
</div>
@if ($core->trashed())<div class="alert alert-warning">Archived on {{ $core->deleted_at->displayDate() }}. Restore it to use it again.</div>@endif
<div class="tab-marker" data-tab-start="Core"></div>
<div class="panel"><div class="panel-header"><h2>Core</h2></div><div class="panel-body">
    @include('attachments._cover', ['type' => 'core_definition', 'id' => $core->id])
    <dl class="tool-facts">
        <div><dt>Process</dt><dd>{{ $core->process_type ?: '—' }}</dd></div>
        <div><dt>Weight</dt><dd>{{ $core->nominal_weight_kg !== null ? rtrim(rtrim($core->nominal_weight_kg, '0'), '.').' kg' : '—' }}</dd></div>
    </dl>
    @if ($core->description)<p>{{ $core->description }}</p>@endif
    @if ($core->engineering_notes)<p class="muted">{{ $core->engineering_notes }}</p>@endif
    @include('tooling::tools._mark', ['tool' => $core, 'type' => 'core_definition'])
    <div class="muted small" style="margin-top:8px">Barcode</div>{!! \App\Support\Code128::svg($core->code, 44, 2) !!}
</div></div>

<div class="panel"><div class="panel-header"><h2>Core boxes that make it</h2><span class="muted small">A box with several cores lists the cavities that make this one.</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Core box</th><th>Revision</th><th>Cavities for this core</th><th>Box cavities</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($cavities as $rows)
            @php $box = $rows->first()->coreBox; @endphp
            <tr>
                <td><x-item :name="$box->name" :code="$box->code" :href="route('core-boxes.show', $box)" /></td>
                <td class="mono">{{ $box->revision }}</td>
                <td>{{ $rows->pluck('cavity_number')->implode(', ') }}</td>
                <td>{{ $box->cavities }}</td>
                <td><span class="status {{ $box->status === 'ACTIVE' ? 'status-completed' : 'status-cancelled' }}">{{ $box->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No core box makes this core yet. <a href="{{ route('core-boxes.create', ['core' => $core->id]) }}">Add one</a>.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
<div class="panel"><div class="panel-header"><h2>Needed by</h2>@unless ($core->trashed())<button type="button" class="btn btn-small" data-quick="qd-core-need">+ Product</button>@endunless</div>
    <div class="table-wrap"><table>
        <thead><tr><th>Product</th><th>Revision</th><th>Per casting</th><th></th></tr></thead>
        <tbody>
        @forelse ($core->requirements()->with('productRevision.product')->get() as $n)
            <tr>
                <td><x-item :name="$n->productRevision->product->name" :code="$n->productRevision->product->code" :href="route('products.show', $n->productRevision->product)" /></td>
                <td class="mono">Rev {{ $n->productRevision->revision }}</td>
                <td>{{ $n->quantity_per_casting }}</td>
                <td><form method="POST" action="{{ route('core-requirements.destroy', $n) }}" onsubmit="return confirm('Remove this product from the core?')">@csrf @method('DELETE')<button class="btn btn-small" type="submit">Remove</button></form></td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No product needs this core yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@include('partials.quick-dialog', ['id' => 'qd-core-need', 'title' => 'Product that needs '.$core->code, 'action' => route('core-requirements.store'), 'submit' => 'Add product', 'fields' => 'tooling::_requirement-fields'])
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('attachments._panel', ['type' => 'core_definition', 'id' => $core->id])
@include('audit._history', ['type' => 'CoreDefinition', 'id' => $core->id])
@endsection
