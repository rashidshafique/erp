@extends('layouts.app')

@section('title', $box->code)
@section('breadcrumb')<a href="{{ route('core-boxes.index') }}">Core boxes</a> / {{ $box->code }} @endsection

@section('content')
<div class="page-heading">
    <div><h1>{{ $box->name }}</h1><p>Core box · <span class="mono">{{ $box->code }}</span> · revision {{ $box->revision }} · <span class="status {{ $box->status === 'ACTIVE' ? 'status-completed' : 'status-cancelled' }}">{{ $box->status }}</span></p></div>
    <div class="actions">
        @if ($box->trashed())
            <form method="POST" action="{{ route('core-boxes.restore', $box) }}" style="display:inline">@csrf<button class="btn btn-primary" type="submit">Restore core box</button></form>
        @else
            <a class="btn" href="{{ route('core-boxes.edit', $box) }}">Edit</a>
            <form method="POST" action="{{ route('core-boxes.archive', $box) }}" style="display:inline" onsubmit="return confirm(@js('Archive '.$box->code.'? It leaves the lists and pickers and can be restored.'))">@csrf @method('DELETE')<button class="btn" type="submit">Archive</button></form>
        @endif
    </div>
</div>
@if ($box->trashed())<div class="alert alert-warning">Archived on {{ $box->deleted_at->displayDate() }}. Restore it to use it again.</div>@endif
<div class="tab-marker" data-tab-start="Core box"></div>
<div class="panel"><div class="panel-header"><h2>Core box</h2></div><div class="panel-body">
    @include('attachments._cover', ['type' => 'core_box', 'id' => $box->id])
    <dl class="tool-facts">
        <div><dt>Cavities</dt><dd>{{ $box->cavities }}</dd></div>
        <div><dt>Material</dt><dd>{{ $box->material ?: '—' }}</dd></div>
        <div><dt>Commissioned</dt><dd>{{ $box->commissioned_on?->displayDate() ?? '—' }}</dd></div>
        <div><dt>Origin</dt><dd>{{ \App\Models\CoreBox::ORIGINS[$box->origin] ?? $box->origin }}
            @if ($box->modifiedFromBox)<span class="sub"><a href="{{ route('core-boxes.show', $box->modifiedFromBox) }}">{{ $box->modifiedFromBox->code }} · {{ $box->modifiedFromBox->name }}</a></span>@endif
            @if ($box->modifiedFromPattern)<span class="sub"><a href="{{ route('patterns.show', $box->modifiedFromPattern) }}">{{ $box->modifiedFromPattern->code }} · {{ $box->modifiedFromPattern->name }}</a></span>@endif</dd></div>
    </dl>
    @if ($box->description)<p>{{ $box->description }}</p>@endif
    @if ($box->notes)<p class="muted">{{ $box->notes }}</p>@endif
    @include('tooling::tools._mark', ['tool' => $box, 'type' => 'core_box'])
    <div class="muted small" style="margin-top:8px">Barcode</div>{!! \App\Support\Code128::svg($box->code, 44, 2) !!}
</div></div>

<div class="panel"><div class="panel-header"><h2>Core made in each cavity</h2><span class="muted small">Leave a cavity empty when it is blocked off or not in use.</span></div>
    <form method="POST" action="{{ route('core-boxes.cavities', $box) }}">@csrf @method('PUT')
        <div class="table-wrap"><table>
            <thead><tr><th>Cavity</th><th>Core</th></tr></thead>
            <tbody>
            @foreach (range(1, $box->cavities) as $n)
                @php $current = $box->cavityMap->firstWhere('cavity_number', $n)?->core_definition_id; @endphp
                <tr><td>{{ $n }}</td><td>
                    @if ($box->trashed())
                        {{ $box->cavityMap->firstWhere('cavity_number', $n)?->coreDefinition?->name ?? '—' }}
                    @else
                        <select name="cores[{{ $n }}]"><option value="">— not in use —</option>@foreach ($cores as $c)<option value="{{ $c->id }}" @selected($current === $c->id)>{{ $c->code }} · {{ $c->name }}</option>@endforeach</select>
                    @endif
                </td></tr>
            @endforeach
            </tbody>
        </table></div>
        @unless ($box->trashed())<div class="panel-body"><button class="btn btn-primary" type="submit">Save cores per cavity</button></div>@endunless
    </form>
</div>

@if ($derived->isNotEmpty())
<div class="panel"><div class="panel-header"><h2>Modified into</h2></div><div class="panel-body">@foreach ($derived as $d)<a href="{{ route('core-boxes.show', $d) }}">{{ $d->code }} · {{ $d->name }}</a>@if (! $loop->last), @endif @endforeach</div></div>
@endif

<div class="panel"><div class="panel-header"><h2>Life &amp; maintenance</h2><span class="muted small"><a href="{{ route('tooling-life.dashboard') }}">Life limits</a> · <a href="{{ route('tooling-maintenance.index') }}">Maintenance register</a></span></div>
    <div class="panel-body">
        @if ($life)<p>Service life limit: {{ \App\Support\Formats::number((float) $life->service_life_quantity, 2) }} {{ strtolower(str_replace('_', ' ', $life->usage_unit)) }}.</p>
        @else<p class="muted small" style="margin:0 0 10px">No service-life limit is set. <a href="{{ route('tooling-life.create') }}">Set one</a> to count cores made against the box's rated life.</p>@endif
    </div>
    <div class="table-wrap"><table><thead><tr><th>Record</th><th>Activity</th><th>Status</th><th>Reported</th></tr></thead><tbody>
    @forelse ($records as $r)<tr><td><a href="{{ route('tooling-maintenance.show', $r) }}">{{ $r->record_number }}</a></td><td>{{ str_replace('_', ' ', $r->activity_type) }}</td><td>{{ $r->status }}</td><td>{{ $r->reported_at?->displayDate() }}</td></tr>
    @empty<tr><td colspan="4" class="muted">No maintenance recorded.</td></tr>@endforelse</tbody></table></div>
</div>
<div class="tab-marker" data-tab-start="Files & history"></div>
@include('attachments._panel', ['type' => 'core_box', 'id' => $box->id])
@include('audit._history', ['type' => 'CoreBox', 'id' => $box->id])
@endsection
