@extends('layouts.app')
@section('title', $asset->code.' '.$asset->name)
@section('breadcrumb')<a href="{{ route('assets.index') }}">Fixed assets</a> / {{ $asset->code }}@endsection
@section('content')
@include('assets::_styles')
@php
    $num = fn ($v) => \App\Support\Formats::number((float) $v, 0);
    $class = $asset->assetClass;
    $pill = match ($asset->status) { 'IN_USE' => 'fa-ok', 'IN_PROGRESS' => 'fa-info', default => 'fa-grey' };
    $book = $asset->cost - $depreciated;
@endphp
<div class="page-heading">
    <div><h1>{{ $asset->name }} <span class="fa-pill {{ $pill }}" style="vertical-align:middle">{{ \App\Domains\Assets\Models\FixedAsset::STATUSES[$asset->status] }}</span></h1><p>{{ $asset->code }} · {{ \App\Domains\Assets\Models\FixedAsset::KINDS[$asset->kind] ?? $asset->kind }}</p></div>
    <div class="actions">
        <a class="btn" href="{{ route('assets.edit', $asset) }}">Edit</a>
        @if ($asset->status === 'IN_PROGRESS')<button type="button" class="btn btn-primary" onclick="document.getElementById('in-use').showModal()">Put in use…</button>@endif
    </div>
</div>
@error('in_use_on')<div class="alert alert-error">{{ $message }}</div>@enderror
<div class="fa-card"><div class="fa-grid">
    <div class="fa-main">
        <div class="fa-dl">
            <div><div class="fa-lab">Kind</div><div>{{ \App\Domains\Assets\Models\FixedAsset::KINDS[$asset->kind] ?? $asset->kind }}@if ($asset->pattern?->material) · {{ strtolower($asset->pattern->material) }}@endif</div></div>
            <div><div class="fa-lab">Account</div><div>{{ $class->costAccount?->label() }}</div></div>
            <div><div class="fa-lab">Class</div><div>{{ $class->name }}</div></div>
            <div><div class="fa-lab">Location</div><div>{{ $asset->location ?: '—' }}</div></div>
            @if ($asset->pattern)<div><div class="fa-lab">Tool record</div><div>@if (Route::has('patterns.show'))<a href="{{ route('patterns.show', $asset->pattern) }}">{{ $asset->pattern->code }}</a>@else{{ $asset->pattern->code }}@endif · pattern register</div></div>@endif
            @if ($asset->coreBox)<div><div class="fa-lab">Tool record</div><div>{{ $asset->coreBox->code }} · core box register</div></div>@endif
            @if ($asset->equipment)<div><div class="fa-lab">Equipment</div><div>{{ $asset->equipment->code }} · {{ $asset->equipment->name }}</div></div>@endif
            @if ($development)<div><div class="fa-lab">Came from</div><div>@if (Route::has('developments.show'))<a href="{{ route('developments.show', $development->id) }}">{{ $development->code }}</a>@else{{ $development->code }}@endif</div></div>@endif
            <div><div class="fa-lab">In use since</div><div>{{ $asset->in_use_on?->displayDate() ?? 'Not yet' }}</div></div>
        </div>

        <div>
            <h3 class="fa-sec">Cost</h3>
            <div class="fa-table"><table>
                <thead><tr><th>Cost build-up</th><th>Source</th><th class="r">Amount</th></tr></thead>
                <tbody>
                @forelse ($asset->costs as $c)
                    <tr><td>{{ $c->description }}</td><td class="fa-sub">{{ \App\Domains\Assets\Models\AssetCost::SOURCES[$c->source_type] ?? $c->source_type }}@if ($c->reference) · {{ $c->reference }}@endif</td><td class="r nw">{{ $num($c->amount) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="fa-sub">No cost yet. Bills for this asset add to it as they are posted.</td></tr>
                @endforelse
                <tr class="sum"><td>Cost</td><td class="fa-sub" style="font-weight:400">sales tax claimed as input is not in cost</td><td class="r nw">{{ $num($asset->cost) }}</td></tr>
                </tbody>
            </table></div>
        </div>

        <div>
            <h3 class="fa-sec">Depreciation · accounting book</h3>
            <div class="fa-dl" style="margin-bottom:12px">
                <div><div class="fa-lab">Method</div><div>{{ \App\Domains\Assets\Models\FixedAsset::METHODS[$asset->method] ?? $asset->method }}</div></div>
                <div><div class="fa-lab">Life</div><div>@if ($asset->method === 'USAGE'){{ $num($asset->life_fills) }} fills @elseif ($asset->method === 'STRAIGHT_LINE'){{ rtrim(rtrim(number_format((float) $asset->life_years, 1), '0'), '.') }} years @elseif ($asset->method === 'REDUCING_BALANCE'){{ rtrim(rtrim(number_format((float) $asset->rate_percent, 1), '0'), '.') }}% a year @else — @endif</div></div>
                <div><div class="fa-lab">Residual value</div><div>Rs {{ $num($asset->residual_value) }}</div></div>
                @if ($rate !== null)<div><div class="fa-lab">Rate</div><div>Rs {{ $num($rate) }} per fill</div></div>@endif
            </div>
            <div class="fa-table"><table>
                <thead><tr><th>Month</th>@if ($asset->method === 'USAGE')<th class="r">Fills</th>@endif<th class="r">Charge</th><th class="r">Book value after</th><th></th></tr></thead>
                <tbody>
                @if ($pending && $pending['charge'] > 0)
                    <tr><td>{{ $next->format('M Y') }} to date</td>@if ($asset->method === 'USAGE')<td class="r nw">{{ $num($pending['fills']) }}@if ($pending['runs']) <span class="fa-sub">· {{ implode(', ', array_slice($pending['runs'], 0, 4)) }}</span>@endif</td>@endif
                        <td class="r nw">{{ $num($pending['charge']) }}</td><td class="r nw">{{ $num($pending['book_before'] - $pending['charge']) }}</td><td class="fa-sub">Posts with the {{ $next->format('F') }} run</td></tr>
                @endif
                @foreach ($months as $m)
                    <tr><td>{{ $m->run->period->format('M Y') }}</td>@if ($asset->method === 'USAGE')<td class="r nw">{{ $num($m->fills) }}</td>@endif<td class="r nw">{{ $num($m->charge) }}</td><td class="r nw">{{ $num($m->book_before - $m->charge) }}</td>
                        <td class="fa-sub"><a href="{{ route('assets.depreciation', ['period' => $m->run->period->format('Y-m')]) }}">Posted</a></td></tr>
                @endforeach
                @if ($asset->opening_depreciation > 0)
                    <tr><td>Brought in</td>@if ($asset->method === 'USAGE')<td class="r nw">{{ $num($asset->opening_fills) }}</td>@endif<td class="r nw">{{ $num($asset->opening_depreciation) }}</td><td class="r nw">{{ $num($asset->cost - $asset->opening_depreciation) }}</td><td class="fa-sub">to the day it was added</td></tr>
                @endif
                @if (! ($pending && $pending['charge'] > 0) && $months->isEmpty() && $asset->opening_depreciation <= 0)
                    <tr><td colspan="5" class="fa-sub">{{ $asset->status === 'IN_PROGRESS' ? 'Depreciation starts when the asset is put in use.' : 'Nothing depreciated yet.' }}</td></tr>
                @endif
                </tbody>
            </table></div>
        </div>

        <div>
            <h3 class="fa-sec">Tax book</h3>
            <p class="fa-sub" style="margin:0">Tax depreciation is kept apart from the accounting book and is set by your accountant under the tax rules.
                @if ($asset->tax_class || $class->tax_class) Tax class: {{ $asset->tax_class ?: $class->tax_class }}. @else This asset has no tax class yet: set it before the year-end tax computation. @endif</p>
        </div>
    </div>
    <aside class="fa-side">
        <div><div class="fa-lab">Book value</div><div class="fa-big" style="font-size:22px">Rs {{ $num($book) }}</div></div>
        <div class="fa-two">
            <div><div class="fa-lab">Cost</div><div>Rs {{ $num($asset->cost) }}</div></div>
            <div><div class="fa-lab">Depreciated</div><div>Rs {{ $num($depreciated) }}</div></div>
        </div>
        @if ($fillsUsed !== null && $asset->life_fills)
            <div><div class="fa-lab">Life used</div><div>{{ $num($fillsUsed) }} of {{ $num($asset->life_fills) }} fills.</div>
                <div class="cl-meter" style="margin-top:8px;height:6px;border-radius:3px;background:#e6e7e9;overflow:hidden"><div style="height:6px;width:{{ min(100, round($fillsUsed * 100 / $asset->life_fills)) }}%;background:var(--accent)"></div></div></div>
        @endif
        <div>
            <h3 class="fa-sec">History</h3>
            @foreach ($asset->events as $e)
                <div style="padding:6px 0;border-bottom:1px solid #e6e7e9">
                    <div>{{ $e->happened_on->format('j M Y') }} · {{ \App\Domains\Assets\Models\AssetEvent::KINDS[$e->kind] ?? strtolower($e->kind) }}</div>
                    <div class="fa-sub">@if ($e->journal)<a href="{{ route('ledger.journals.show', $e->journal) }}">{{ $e->journal->number }}</a> · @endif{{ $e->note }}@if ($e->user) · {{ $e->user->name }}@endif</div>
                </div>
            @endforeach
        </div>
        @if ($asset->notes)<div><div class="fa-lab">Notes</div><div>{{ $asset->notes }}</div></div>@endif
    </aside>
</div></div>

@if ($asset->status === 'IN_PROGRESS')
<dialog class="fa-dialog" id="in-use">
    <form method="POST" action="{{ route('assets.in-use', $asset) }}">@csrf
        <h2 style="margin:0">Put {{ $asset->code }} in use</h2>
        <p class="fa-sub" style="margin:0">Its cost moves out of capital work in progress into {{ $class->costAccount?->label() }}, and depreciation starts on the day it goes into use.</p>
        <label>In use from<input type="date" name="in_use_on" value="{{ today()->toDateString() }}" required></label>
        <div>
            <div class="fa-sec">Will post</div>
            <div class="fa-row"><span>{{ $class->costAccount?->label() }} · {{ $asset->code }}</span><span>Dr {{ $num($asset->cost) }}</span></div>
            <div class="fa-row"><span>{{ \App\Domains\Assets\Services\AssetPosting::CAPITAL_WIP }} Capital work in progress</span><span>Cr {{ $num($asset->cost) }}</span></div>
        </div>
        <div class="foot"><button type="button" class="fa-btn" onclick="this.closest('dialog').close()">Later</button><button class="fa-btn primary">Put in use</button></div>
    </form>
</dialog>
@endif
@endsection
