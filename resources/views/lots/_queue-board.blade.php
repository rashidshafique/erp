{{-- The production queue as a board: one column per step, one card per lot, ticked cards moved with the form above. $steps --}}
<style>
    .qboard { display: flex; gap: 12px; overflow-x: auto; align-items: flex-start; padding-bottom: 8px; }
    .qcol { flex: 0 0 250px; background: #eef1f3; border-radius: 10px; border-top: 4px solid var(--success); }
    .qcol.amber { border-top-color: var(--warning); }
    .qcol.red { border-top-color: var(--danger); }
    .qcol.empty { border-top-color: var(--border); opacity: .6; }
    .qcol-head { padding: 10px 12px 8px; display: flex; gap: 8px; align-items: flex-start; }
    .qcol-head h2 { margin: 0; font-size: 13.5px; }
    .qcol-body { padding: 0 8px 8px; display: grid; gap: 8px; }
    .qcard { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 9px 10px; font-size: 12.5px; }
    .qcard-top { display: flex; gap: 6px; align-items: center; justify-content: space-between; }
    .qcard-meta { display: flex; gap: 10px; flex-wrap: wrap; color: var(--muted); margin-top: 4px; }
    .qcard-moulds { margin: 6px 0 0; padding: 0; list-style: none; border-top: 1px dashed var(--border); padding-top: 5px; }
</style>
<div class="qboard">
@foreach ($steps as $b)
    @php $isReady = $b['key'] === 'ready'; $next = $nextFor($b); @endphp
    <section class="qcol {{ $b['tone'] }}" id="step-{{ $b['key'] }}" x-data="{ all: false }" aria-label="{{ $b['label'] }}">
        <div class="qcol-head">
            @if (collect($b['lots'])->contains(fn ($r) => $r['lot']->status === 'IN_PRODUCTION'))<input type="checkbox" class="bucket-all" aria-label="Select all in {{ $b['label'] }}" style="margin-top:3px">@endif
            <div><h2>{{ $b['label'] }}</h2><div class="muted small">{{ $b['lot_count'] }} {{ $b['lot_count'] === 1 ? 'lot' : 'lots' }}@if ($b['lot_count']) · {{ \App\Support\Formats::number($b['pieces']) }} pcs @endif</div>@if ($b['limit'])<div class="small {{ $b['over_limit'] ? '' : 'muted' }}" @if ($b['over_limit']) style="color:var(--danger);font-weight:650" @endif>{{ $b['over_limit'] ? 'Over limit of' : 'Limit' }} {{ \App\Support\Formats::number($b['limit']) }} {{ $b['limit_unit'] }}</div>@endif @if ($next)<button type="button" class="btn btn-small" style="margin-top:6px" @click="moveStep('{{ $b['key'] }}')">Move all {{ $next['count'] }} → {{ $next['name'] }}</button>@endif</div>
        </div>
        <div class="qcol-body">
            @foreach ($b['lots'] as $r)
                @php $l = $r['lot']; $moulds = count($r['moulds']); @endphp
                <article class="qcard" x-data="{ moulds: false }" @if ($isReady && $loop->index >= 15) x-show="all" x-cloak @endif>
                    <div class="qcard-top">
                        <label style="display:flex;gap:6px;align-items:center;margin:0">@if ($l->status === 'IN_PRODUCTION')@include('lots._queue-lot-box', ['step' => $b['key']])@endif<a href="{{ route('lots.show', $l) }}" class="mono"><strong>{{ $l->label }}</strong></a></label>
                        <span class="muted">{{ $r['pieces'] }} pcs</span>
                    </div>
                    <div>{{ $l->product?->name ?? '—' }}</div>
                    <div class="qcard-meta">
                        <span>{{ $r['age_h'] !== null ? $r['age_h'].' h' : 'not poured' }}</span>
                        @if ($r['urgent'])<span @if ($r['urgent']->lt(today())) style="color:var(--danger);font-weight:650" @endif>req {{ $r['urgent']->displayShortDate() }}</span>@endif
                        @if ($r['cool'] && $r['cool']['wait'] > 0)<span class="status status-in-progress">wait {{ $r['cool']['wait'] }} min</span>@endif
                        @if ($l->status === 'ON_HOLD')<span class="status status-on-hold">on hold</span>@endif
                    </div>
                    @if ($moulds > 1)
                        <button type="button" class="qmould-toggle" @click="moulds = !moulds" :aria-expanded="moulds.toString()"><span x-text="moulds ? '▾' : '▸'">▸</span> {{ $moulds }} moulds</button>
                        <ul class="qcard-moulds" x-show="moulds" x-cloak>@foreach ($r['moulds'] as $m)<li><span class="mono">{{ $m['number'] }}</span> <span class="muted">· {{ $m['pieces'] }} pcs</span></li>@endforeach</ul>
                    @endif
                </article>
            @endforeach
            @if ($isReady && $b['lot_count'] > 15)
                <button type="button" class="btn btn-small" x-show="!all" @click="all = true">Show all {{ $b['lot_count'] }} lots</button>
            @endif
            @if ($b['lot_count'] === 0)<div class="muted small" style="padding:4px 4px 2px">Nothing here.</div>@endif
        </div>
    </section>
@endforeach
</div>
