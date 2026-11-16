@extends('layouts.app')

@section('title', $core->code)
@section('breadcrumb')<a href="{{ route('cores.index') }}">Cores</a> / {{ $core->code }} @endsection

@section('content')
<div class="page-heading">
    <div><h1>{{ $core->name }}</h1><p><span class="mono">{{ $core->code }}</span> · <span class="status {{ ! $core->trashed() && $core->status === 'ACTIVE' ? 'status-completed' : 'status-cancelled' }}">{{ $core->trashed() ? 'Archived' : ucfirst(strtolower($core->status)) }}</span></p></div>
    <div class="actions">
        @unless ($core->trashed())
            <a class="btn" href="{{ route('core-boxes.create', ['core' => $core->id]) }}">+ Core box</a>
            <a class="btn" href="{{ route('cores.edit', $core) }}">Edit</a>
            <form method="POST" action="{{ route('cores.archive', $core) }}" style="display:inline" onsubmit="return confirm(@js('Archive '.$core->code.'? It leaves the core list and pickers and can be restored.'))">@csrf @method('DELETE')<button class="btn" type="submit">Archive</button></form>
        @endunless
    </div>
</div>
@if ($core->trashed())<div class="alert alert-warning" style="display:flex;align-items:center;gap:10px"><span class="status status-cancelled">Archived</span><span>Archived {{ $core->deleted_at->displayDate() }} — hidden from pickers and lists. Facts stay readable.</span><form method="POST" action="{{ route('cores.restore', $core) }}" style="margin-left:auto">@csrf<button class="btn" type="submit">Restore</button></form></div>@endif
<div class="tab-marker" data-tab-start="Core"></div>
<style>.core-top { display: flex; gap: 20px; align-items: flex-start; } .core-top .cover-box { float: none; margin: 0; flex: none; } .core-top > div { flex: 1; min-width: 0; } .core-top .tool-facts { grid-template-columns: repeat(4, minmax(0, 1fr)); } .core-text { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; padding-top: 14px; margin-top: 14px; border-top: 1px solid var(--border); } @media (max-width: 800px) { .core-top { flex-direction: column; } .core-top .tool-facts, .core-text { grid-template-columns: 1fr 1fr; } }</style>
<div class="panel"><div class="panel-body">
    <div class="core-top">
        @include('attachments._cover', ['type' => 'core_definition', 'id' => $core->id])
        <div>
            <dl class="tool-facts">
                <div><dt>Type</dt><dd>{{ \App\Models\CoreDefinition::TYPES[$core->core_type] ?? $core->core_type }}</dd></div>
                <div><dt>Core process</dt><dd>{{ $core->process_type ?: '—' }}</dd></div>
                <div><dt>Weight</dt><dd>{{ $core->nominal_weight_kg !== null ? rtrim(rtrim($core->nominal_weight_kg, '0'), '.').' kg' : '—' }}</dd></div>
                <div><dt>Status</dt><dd><span class="status {{ $core->trashed() ? 'status-cancelled' : ($core->status === 'ACTIVE' ? 'status-completed' : 'status-cancelled') }}">{{ $core->trashed() ? 'Archived' : ucfirst(strtolower($core->status)) }}</span></dd></div>
                <div><dt>Mark code</dt><dd>@include('tooling::tools._mark', ['tool' => $core, 'type' => 'core_definition', 'bare' => true])</dd></div>
                <div><dt>Barcode</dt><dd><code>*{{ preg_replace('/[^A-Za-z0-9]/', '', $core->code) }}*</code></dd></div>
                <div><dt>Made in</dt><dd>@if ($madeIn)<x-item :name="$madeIn->name" :code="$madeIn->code" :href="route('core-boxes.show', $madeIn)" />@else — @endif</dd></div>
                @if (\App\Domains\Tooling\Support\CoreRules::stockOn())<div><dt>On hand</dt><dd>{{ $onHand['cores'] }} {{ $onHand['cores'] === 1 ? 'core' : 'cores' }} · {{ $onHand['lots'] }} {{ $onHand['lots'] === 1 ? 'lot' : 'lots' }}</dd></div>@endif
            </dl>
            @if ($core->description || $core->engineering_notes)
            <div class="core-text">
                <div><div class="muted small">Description</div><div>{{ $core->description ?: '—' }}</div></div>
                <div><div class="muted small">Engineering notes</div><div>{{ $core->engineering_notes ?: '—' }}</div></div>
            </div>
            @endif
        </div>
    </div>
</div></div>

<div class="panel"><div class="panel-header"><h2>Core boxes that make it</h2>@unless ($core->trashed())<a class="btn btn-small" href="{{ route('core-boxes.create', ['core' => $core->id]) }}">+ Core box</a>@endunless</div>
    @if ($cavities->isEmpty())
        <div class="panel-body"><div class="muted" style="border:1px dashed var(--border);border-radius:8px;padding:18px;text-align:center">No core box makes this core yet.@unless ($core->trashed()) <a href="{{ route('core-boxes.create', ['core' => $core->id]) }}">Add a core box</a> — or leave it empty for a bought-in core.@endunless</div></div>
    @else
    <div class="table-wrap"><table>
        <thead><tr><th>Core box</th><th>Rev</th><th>Cavities for this core</th><th>Box cavities</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($cavities as $rows)
            @php $box = $rows->first()->coreBox; $shown = $box->displayStatus(); @endphp
            <tr>
                <td><x-item :name="$box->name" :code="$box->code" :href="route('core-boxes.show', $box)" /></td>
                <td class="mono">{{ $box->revision }}</td>
                <td>{{ $rows->count() }} of {{ $box->cavities }}</td>
                <td>{{ $box->cavities }}</td>
                <td><span class="status {{ match ($shown) { 'ACTIVE' => 'status-completed', 'LENT' => 'status-in-progress', default => 'status-cancelled' } }}">{{ \App\Models\CoreBox::STATUS_LABELS[$shown] ?? $shown }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
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
