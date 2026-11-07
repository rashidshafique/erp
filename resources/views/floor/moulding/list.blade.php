@extends('layouts.app')

@section('title', 'Moulding list')
@section('breadcrumb', 'Moulding / List')

{{-- design: F3·1 today's list, F3·E no list issued (carried-over rows) --}}
@section('content')
<div class="fl-tablet fl-has-tabs">
    @include('floor.moulding._frame')

    <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap">
        <div>
            @if ($list)
                <h1 style="margin:0;font-size:22px"><span class="fl-mono">{{ $list->list_number }}</span> · {{ $station?->code }}</h1>
                <p style="color:var(--fl-meta);font-size:13.5px;margin:4px 0 0">{{ $list->list_date->format('D j M') }} · {{ $list->shift }} · issued{{ $list->issued_at ? ' '.$list->issued_at->format('D H:i') : '' }}{{ $list->issued_by ? ' by '.$list->issued_by : '' }}</p>
            @else
                <h1 style="margin:0;font-size:22px">{{ $station?->code ?? 'Moulding' }} · no list for today</h1>
                <p style="color:var(--fl-meta);font-size:13.5px;margin:4px 0 0">{{ today()->format('D j M') }}</p>
            @endif
        </div>
        @if ($stations->count() > 1)
            <form method="GET" action="{{ route('floor.moulding.list') }}">
                <label style="display:flex;align-items:center;gap:8px;color:var(--fl-meta)">Station
                    <select name="station" onchange="this.form.submit()" style="min-height:48px;min-width:180px;font-size:15px">
                        @foreach ($stations as $s)<option value="{{ $s->id }}" @selected($s->id === $station?->id)>{{ $s->code }}</option>@endforeach
                    </select>
                </label>
            </form>
        @endif
    </div>

    @if ($list)
        <div class="fl-block-head">Pattern · order <span style="float:right">Made / plan</span></div>
        <div class="fl-rows">
            @forelse ($active as $item)
                @php($made = (int) ($item->run?->accepted_mould_qty ?? 0))
                <x-floor.row :title="$item->patternRevision->pattern->code.' '.$item->patternRevision->pattern->name"
                    :meta="trim(($item->productionOrder?->production_order_number ?? '').($item->run ? ' · '.$item->run->run_number.' in progress since '.$item->run->started_at?->format('H:i') : '').($item->productionOrder?->required_date ? ' · due '.$item->productionOrder->required_date->format('j M') : ''), ' ·')"
                    :age="$made.' / '.$item->planned_moulds" :action="$item->run ? 'Continue' : 'Enter moulds'" :href="route('floor.moulding.item', $item)" />
            @empty
                <div class="fl-row-empty">Nothing left to mould on this list.</div>
            @endforelse
            @if ($done->isNotEmpty())
                <details class="fl-row-empty">
                    <summary style="cursor:pointer;min-height:40px;display:flex;align-items:center">✓ Done · {{ $done->count() }} {{ \Illuminate\Support\Str::plural('pattern', $done->count()) }}
                        <span class="fl-row-meta" style="margin-left:10px">{{ $done->map(fn ($i) => $i->patternRevision->pattern->code.' '.(int) ($i->run?->accepted_mould_qty ?? 0).' / '.$i->planned_moulds)->join(' · ') }}</span></summary>
                    <div class="fl-rows" style="margin-top:8px">
                        @foreach ($done as $item)
                            <x-floor.row :title="$item->patternRevision->pattern->code.' '.$item->patternRevision->pattern->name" :meta="$item->run?->run_number" :age="(int) ($item->run?->accepted_mould_qty ?? 0).' / '.$item->planned_moulds"
                                action="Open run" :href="$item->run ? route('moulding-runs.show', $item->run) : null" />
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
        @php($all = $active->concat($done))
        <p style="color:var(--fl-meta);font-size:13.5px">Station total {{ $all->sum(fn ($i) => (int) ($i->run?->accepted_mould_qty ?? 0)) }} / {{ $all->sum('planned_moulds') }} moulds. Finished rows fold into Done until the list closes; open the run to fix a count.</p>
    @else
        <div class="fl-block-head">Due now</div>
        <div class="fl-rows">
            @if ($draft)
                <x-floor.row title="No moulding list issued for today" meta="Today's list is still a draft. New rows appear here when it is issued." tone="warn" age="draft" />
            @else
                <div class="fl-row-empty">No moulding list for today at {{ $station?->code }}.</div>
            @endif
            @foreach ($carried as $item)
                <x-floor.row :title="'Carried over · '.$item->patternRevision->pattern->code.' '.$item->patternRevision->pattern->name"
                    :meta="($item->productionOrder?->production_order_number ?? '').' · '.(int) ($item->run?->accepted_mould_qty ?? 0).' / '.$item->planned_moulds.' · from '.$item->workList->list_number"
                    :age="$item->workList->list_date->format('D')" :action="$item->run ? 'Continue' : 'Enter moulds'" :href="route('floor.moulding.item', $item)" />
            @endforeach
        </div>
    @endif
</div>
@endsection
