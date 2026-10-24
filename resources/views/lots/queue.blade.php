@extends('layouts.app')
@section('title', 'Production queue')
@section('content')
<style>
    [x-cloak] { display: none !important; }
    .qflow { display: flex; align-items: stretch; gap: 6px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 18px; }
    .qstep { flex: 1 0 128px; display: block; padding: 12px 14px; background: var(--surface); border: 1px solid var(--border); border-top: 4px solid var(--success); border-radius: 10px; color: inherit; text-decoration: none; }
    a.qstep:hover { text-decoration: none; border-color: var(--accent); }
    .qstep.amber { border-top-color: var(--warning); }
    .qstep.red { border-top-color: var(--danger); }
    .qstep.empty { border-top-color: var(--border); opacity: .55; }
    .qstep-name { color: var(--muted); font-size: 12px; font-weight: 600; line-height: 1.3; }
    .qstep-lots { margin-top: 6px; font-size: 24px; font-weight: 750; line-height: 1; }
    .qstep-lots small { font-size: 12px; font-weight: 600; color: var(--muted); }
    .qstep-note { margin-top: 4px; color: var(--muted); font-size: 11.5px; }
    .qstep.red .qstep-age { color: var(--danger); font-weight: 650; }
    .qstep.amber .qstep-age { color: var(--warning); font-weight: 650; }
    .qarrow { align-self: center; color: var(--muted); flex: 0 0 auto; }
    .btn-group { display: inline-flex; }
    .btn-group .btn { border-radius: 0; }
    .btn-group .btn:first-child { border-radius: 7px 0 0 7px; }
    .btn-group .btn:last-child { border-radius: 0 7px 7px 0; margin-left: -1px; }
    .qmould td { background: #fafbfc; font-size: 12.5px; }
    .qmould-toggle { border: 1px solid var(--border); background: var(--surface); border-radius: 999px; padding: 1px 8px; font: inherit; font-size: 11.5px; cursor: pointer; margin-top: 3px; }
    .qscan { width: 170px; }
    .qscan-msg { font-size: 12px; color: var(--danger); }
    .qflash { animation: qflash 1.6s ease-out; }
    @keyframes qflash { from { background: var(--accent-soft); } to { background: transparent; } }
    .qbar { position: sticky; bottom: 12px; z-index: 30; margin: 14px auto 0; max-width: 1100px; background: var(--surface); border: 1px solid var(--border); border-radius: 14px; box-shadow: 0 10px 30px rgba(15, 23, 42, .16), 0 2px 6px rgba(15, 23, 42, .08); padding: 10px 14px 12px; display: grid; gap: 9px; }
    .qbar-top, .qbar-fields { display: flex; flex-wrap: wrap; gap: 8px 10px; align-items: center; }
    .qbar-count { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; }
    .qbar-count strong { background: var(--accent); color: #fff; border-radius: 999px; padding: 2px 10px; font-size: 12.5px; }
    .qbar-warn { background: #fff7e6; color: #8a5a00; border: 1px solid #f3d9a4; border-radius: 999px; padding: 1px 9px; font-size: 11.5px; font-weight: 600; }
    .qbar-help { color: var(--muted); cursor: help; font-size: 13px; }
    .qbar-clear { border: 0; background: none; color: var(--muted); font: inherit; font-size: 12.5px; cursor: pointer; padding: 2px 4px; }
    .qbar-clear:hover { color: var(--text); }
    .qbar-stepper { display: flex; gap: 4px; overflow-x: auto; padding-bottom: 2px; }
    .qbar-stage { flex: 1 0 auto; min-width: 92px; border: 1px solid var(--border); border-top: 3px solid var(--border); background: var(--surface); border-radius: 8px; padding: 6px 10px; font: inherit; font-size: 12.5px; font-weight: 600; color: var(--muted); cursor: pointer; white-space: nowrap; transition: background .15s, border-color .15s, color .15s; }
    .qbar-stage:hover:not(:disabled) { border-color: var(--accent); color: var(--text); }
    .qbar-stage.on { background: var(--accent-soft); border-color: var(--accent); border-top-color: var(--accent); color: var(--accent-dark); }
    .qbar-stage:disabled { opacity: .45; cursor: default; text-decoration: line-through; }
    .qbar-fields input { height: 32px; }
    .qbar-move { margin-left: auto; }
    .qbar-move:disabled { opacity: .5; cursor: not-allowed; }
</style>

@php
    // The stage a whole step would move on to: the next active one after the stage its lots last passed. None for waiting or ready.
    $nextFor = function (array $b) use ($stages): ?array {
        $movable = array_values(array_filter($b['lots'], fn ($r) => $r['lot']->status === 'IN_PRODUCTION'));
        if (in_array($b['key'], ['waiting', 'ready'], true) || $movable === []) {
            return null;
        }
        $next = $stages->first(fn ($s) => $s->sequence > $movable[0]['last_seq']);

        return $next ? ['count' => count($movable), 'name' => $next->name] : null;
    };
    $oldStages = $stages->whereIn('id', (array) old('stages', []));
@endphp

<div x-data="moveBar(@js($stages->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'seq' => (int) $s->sequence, 'knockout' => (bool) $s->knocks_out_mould])->values()), @js($oldStages->isEmpty() ? null : (int) $oldStages->max('sequence')), @js((string) old('rejected', '')))"
    @keydown.escape.window="clear()" @keydown.enter.window="enterMoves($event)">

<div class="page-heading"><div><h1>Production queue</h1><p>How much work sits at each step after the pour. Oldest first within each step.</p></div><div class="actions">@if ($openLots)<span x-show="!ticked.length"><input type="search" class="qscan" placeholder="Scan or type lot" aria-label="Scan or type a lot code to tick it" x-model="scan" @keydown.enter.prevent="scanLot()"> <span class="qscan-msg" x-text="scanMsg"></span></span>@endif<span class="btn-group" role="group" aria-label="Queue layout"><a class="btn {{ $view === 'list' ? 'btn-primary' : '' }}" href="{{ route('lots.queue') }}" @if ($view === 'list') aria-current="page" @endif>List</a><a class="btn {{ $view === 'board' ? 'btn-primary' : '' }}" href="{{ route('lots.queue', ['view' => 'board']) }}" @if ($view === 'board') aria-current="page" @endif>Board</a></span>@if (Route::has('finished-goods.index'))<a class="btn" href="{{ route('finished-goods.index') }}">Finished goods ({{ $awaitingDispatch }} packed lines)</a>@endif</div></div>

@if ($openLots)
<nav class="qflow" aria-label="Work at each step">
    @foreach ($steps as $step)
        @if (! $loop->first)<span class="qarrow" aria-hidden="true">→</span>@endif
        @php $tag = $step['lot_count'] ? 'a' : 'div'; @endphp
        <{{ $tag }} class="qstep {{ $step['tone'] }}" @if ($step['lot_count']) href="#step-{{ $step['key'] }}" @click="window.dispatchEvent(new CustomEvent('queue-open', { detail: '{{ $step['key'] }}' }))" @endif>
            <div class="qstep-name">{{ $step['label'] }}</div>
            <div class="qstep-lots">{{ $step['lot_count'] }} <small>{{ $step['lot_count'] === 1 ? 'lot' : 'lots' }}</small></div>
            @if ($step['lot_count'])
                <div class="qstep-note">{{ \App\Support\Formats::number($step['pieces']) }} pcs</div>
                @if ($step['oldest_h'] !== null)<div class="qstep-note qstep-age">oldest {{ $step['oldest_h'] >= 72 ? intdiv($step['oldest_h'], 24).' d' : $step['oldest_h'].' h' }}</div>@endif
            @endif
            @if ($step['limit'])<div class="qstep-note" @if ($step['over_limit']) style="color:var(--danger);font-weight:650" @endif>{{ $step['over_limit'] ? 'over limit of' : 'limit' }} {{ \App\Support\Formats::number($step['limit']) }} {{ $step['limit_unit'] }}</div>@endif
        </{{ $tag }}>
    @endforeach
</nav>
@endif

<form method="POST" action="{{ route('lots.bulk-stage') }}" x-ref="form" @change="changed($event)" @click="clicked($event)" @submit="submitting($event)">@csrf

@if ($view === 'board' && $openLots)
@include('lots._queue-board')
@else
@forelse (array_filter($steps, fn ($s) => $s['lot_count'] > 0) as $b)
@php
    $isReady = $b['key'] === 'ready';
    $multi = collect($b['lots'])->contains(fn ($r) => count($r['moulds']) > 1);
    $next = $nextFor($b);
@endphp
<div class="panel" id="step-{{ $b['key'] }}" x-data="{ open: {{ $isReady ? 'false' : 'true' }}, all: false }" @queue-open.window="if ($event.detail === '{{ $b['key'] }}') open = true">
    <div class="panel-header">
        <h2>{{ $b['label'] }}</h2>
        <span class="muted small">{{ $b['lot_count'] }} {{ $b['lot_count'] === 1 ? 'lot' : 'lots' }} · {{ \App\Support\Formats::number($b['pieces']) }} pcs @if ($b['limit'])· <span @if ($b['over_limit']) style="color:var(--danger);font-weight:650" @endif>{{ $b['over_limit'] ? 'over limit of' : 'limit' }} {{ \App\Support\Formats::number($b['limit']) }} {{ $b['limit_unit'] }}</span>@endif</span>
        <span style="margin-left:auto;display:flex;gap:6px">
            @if ($multi)
                <button type="button" class="btn btn-small" x-show="open" @click="window.dispatchEvent(new CustomEvent('queue-moulds', { detail: { step: '{{ $b['key'] }}', open: true } }))">Expand all</button>
                <button type="button" class="btn btn-small" x-show="open" @click="window.dispatchEvent(new CustomEvent('queue-moulds', { detail: { step: '{{ $b['key'] }}', open: false } }))">Collapse all</button>
            @endif
            @if ($next)<button type="button" class="btn btn-small" @click="moveStep('{{ $b['key'] }}')">Move all {{ $next['count'] }} → {{ $next['name'] }}</button>@endif
            @if ($isReady)<button type="button" class="btn btn-small" @click="open = !open" x-text="open ? 'Hide lots' : 'Show lots'">Show lots</button>@endif
        </span>
    </div>
    <div class="table-wrap" x-show="open" @if ($isReady) x-cloak @endif><table>
        <thead><tr><th><input type="checkbox" class="bucket-all" aria-label="Select all in this step"></th><th>Lot</th><th>Product</th><th>Order</th><th>Pieces</th><th>Poured</th><th>Age</th><th>Cooling</th><th>Status</th></tr></thead>
        @foreach ($b['lots'] as $r)
        @php $l = $r['lot']; $moulds = count($r['moulds']); @endphp
        <tbody x-data="{ moulds: false }" @queue-moulds.window="if ($event.detail.step === '{{ $b['key'] }}') moulds = $event.detail.open" @if ($isReady && $loop->index >= 15) x-show="all" x-cloak @endif>
            <tr>
                <td>@if($l->status === 'IN_PRODUCTION')@include('lots._queue-lot-box', ['step' => $b['key']])@endif</td>
                <td><a href="{{ route('lots.show', $l) }}">{{ $l->label }}</a>
                    @if ($moulds > 1)<div><button type="button" class="qmould-toggle" @click="moulds = !moulds" :aria-expanded="moulds.toString()"><span x-text="moulds ? '▾' : '▸'">▸</span> {{ $moulds }} moulds</button></div>@endif
                </td>
                <td><x-product-cell :product="$l->product" /></td>
                <td>{{ $l->productionOrder?->production_order_number }}@if($r['urgent'])<div class="muted small" @if ($r['urgent']->lt(today())) style="color:var(--danger)" @endif>required {{ $r['urgent']->displayShortDate() }}</div>@endif</td>
                <td>{{ $r['pieces'] }}</td>
                <td>{{ $r['poured']?->displayShortDateTime() ?? '—' }}</td>
                <td>{{ $r['age_h'] !== null ? $r['age_h'].' h' : '—' }}</td>
                <td>@if($r['cool'])@if($r['cool']['wait'] > 0)<span class="status status-in-progress">wait {{ $r['cool']['wait'] }} min</span>@else <span class="muted small">met</span>@endif @endif</td>
                <td>{{ $l->status }}</td>
            </tr>
            @if ($moulds > 1)
                @foreach ($r['moulds'] as $m)
                    <tr class="qmould" x-show="moulds" x-cloak><td></td><td class="mono" style="padding-left:22px">↳ {{ $m['number'] }}</td><td colspan="7" class="muted">{{ $m['pieces'] }} {{ $m['pieces'] === 1 ? 'piece' : 'pieces' }} from this mould</td></tr>
                @endforeach
            @endif
        </tbody>
        @endforeach
    </table>
    @if ($isReady && $b['lot_count'] > 15)
        <div class="panel-body" x-show="!all"><button type="button" class="btn btn-small" @click="all = true">Show all {{ $b['lot_count'] }} lots</button> <span class="muted small">Oldest 15 shown.</span></div>
    @endif
    </div>
</div>
@empty<div class="panel"><div class="panel-body muted">No open lots.</div></div>@endforelse
@endif

@if ($openLots)
<div class="qbar" role="region" aria-label="Move ticked lots" x-show="ticked.length" x-cloak
    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
    <div class="qbar-top">
        <span class="qbar-count"><strong x-text="ticked.length + (ticked.length === 1 ? ' lot' : ' lots')"></strong><span x-text="summary"></span></span>
        <span class="qbar-warn" x-show="mixed" x-cloak>Mixed steps: lots ahead will be skipped</span>
        <span class="qbar-warn" x-show="coolingWarn" x-cloak x-text="coolingWarn"></span>
        <span class="qbar-help" title="Move ticked lots: every ticked lot passes the lit stages in order with all its pieces. Rejected pieces are taken from the oldest lots first. Shift-click ticks a range, Esc clears, Enter moves.">ⓘ</span>
        <span style="margin-left:auto;display:inline-flex;gap:6px;align-items:center"><span class="qscan-msg" x-text="scanMsg"></span><input type="search" class="qscan" placeholder="Scan or type lot" aria-label="Scan or type a lot code to tick it" x-model="scan" @keydown.enter.prevent.stop="scanLot()"></span>
        <button type="button" class="qbar-clear" @click="clear()">✕ Clear</button>
    </div>
    <div class="qbar-stepper" role="group" aria-label="Stages to move through: click the last one">
        @foreach ($stages as $st)
            <button type="button" class="qbar-stage" :class="{ on: lit({{ (int) $st->sequence }}) }" :disabled="passed({{ (int) $st->sequence }})" :aria-pressed="lit({{ (int) $st->sequence }}).toString()" @click="pick({{ (int) $st->sequence }})">{{ $st->name }}</button>
        @endforeach
    </div>
    <template x-for="s in chosen" :key="s.id"><input type="hidden" name="stages[]" :value="s.id"></template>
    <div class="qbar-fields">
        <input type="number" min="0" name="rejected" placeholder="Rejected" aria-label="Rejected (total)" style="width:100px" x-model="rejected">
        <input name="rejection_reason" placeholder="Rejection reason" maxlength="100" style="width:170px" value="{{ old('rejection_reason') }}" x-show="Number(rejected) > 0" x-cloak :required="Number(rejected) > 0">
        <input name="cooling_override_reason" placeholder="Still cooling: reason to shake out early" maxlength="300" style="width:250px" value="{{ old('cooling_override_reason') }}" x-show="coolingWarn" x-cloak>
        <input name="operator_name" placeholder="Operator" maxlength="100" style="width:140px" value="{{ old('operator_name') }}" x-ref="operator">
        <button class="btn btn-primary qbar-move" type="submit" x-ref="move" :disabled="!chosen.length" x-text="moveLabel">Move ticked lots</button>
    </div>
</div>
@endif
</form>
</div>

<script>
function moveBar(stages, endSeq, rejected) {
    return {
        stages, endSeq, rejected, ticked: [], lastBox: null, scan: '', scanMsg: '',

        init() {
            try { if (!this.$refs.operator?.value) { this.$refs.operator && (this.$refs.operator.value = localStorage.getItem('erp.queueOperator') || ''); } } catch (e) {}
            this.refresh();
        },
        boxes() { return [...this.$refs.form.querySelectorAll('.queue-lot')]; },
        refresh() {
            this.ticked = this.boxes().filter(b => b.checked).map(b => ({ ...b.dataset, seq: +b.dataset.seq, pieces: +b.dataset.pieces, cool: +b.dataset.cool, age: b.dataset.age === '' ? null : +b.dataset.age }));
            if (!this.ticked.length) { this.endSeq = null; return; }
            if (this.endSeq === null || this.endSeq <= this.fromSeq) { this.endSeq = this.stages.find(s => s.seq > this.fromSeq)?.seq ?? null; }
        },
        get fromSeq() { return Math.min(...this.ticked.map(t => t.seq)); },
        passed(seq) { return this.ticked.length > 0 && seq <= this.fromSeq; },
        lit(seq) { return this.endSeq !== null && seq > this.fromSeq && seq <= this.endSeq; },
        pick(seq) {
            // Clicking the last lit stage again steps back one, so a single stage can be switched off.
            this.endSeq = seq !== this.endSeq ? seq : (this.chosen.length > 1 ? this.chosen[this.chosen.length - 2].seq : null);
        },
        get chosen() { return this.stages.filter(s => this.lit(s.seq)); },
        get mixed() { return new Set(this.ticked.map(t => t.step)).size > 1; },
        get summary() {
            const pieces = this.ticked.reduce((n, t) => n + t.pieces, 0).toLocaleString();
            const ages = this.ticked.map(t => t.age).filter(a => a !== null);
            const oldest = ages.length ? Math.max(...ages) : null;
            return ` · ${pieces} pcs` + (oldest === null ? '' : ` · oldest ${oldest >= 72 ? Math.floor(oldest / 24) + ' d' : oldest + ' h'}`);
        },
        get coolingWarn() {
            const cooling = this.ticked.filter(t => t.cool > 0);
            if (!cooling.length || !this.chosen.some(s => s.knockout)) { return ''; }
            return cooling.length === 1 ? `${cooling[0].label} still cooling ${cooling[0].cool} min` : `${cooling.length} lots still cooling`;
        },
        get moveLabel() {
            if (!this.chosen.length) { return 'Pick a stage'; }
            return `Move ${this.ticked.length} ${this.ticked.length === 1 ? 'lot' : 'lots'} through ${this.chosen.map(s => s.name).join(' → ')}`;
        },
        changed(e) {
            if (e.target.matches('.bucket-all')) {
                // Only the lots on show: hidden ones (past the first 15 of a long step) are never ticked unseen.
                e.target.closest('table, .qcol').querySelectorAll('.queue-lot').forEach(b => { if (b.offsetParent !== null) b.checked = e.target.checked; });
            }
            if (e.target.matches('.queue-lot, .bucket-all')) { this.refresh(); }
        },
        clicked(e) {
            const box = e.target.closest('.queue-lot');
            if (!box) { return; }
            const group = box.closest('table, .qcol');
            if (e.shiftKey && this.lastBox && this.lastBox !== box && this.lastBox.closest('table, .qcol') === group) {
                const inGroup = [...group.querySelectorAll('.queue-lot')].filter(b => b.offsetParent !== null);
                const [a, b] = [inGroup.indexOf(this.lastBox), inGroup.indexOf(box)].sort((x, y) => x - y);
                inGroup.slice(a, b + 1).forEach(x => { x.checked = box.checked; });
            }
            this.lastBox = box;
        },
        clear() {
            this.boxes().forEach(b => { b.checked = false; });
            this.$refs.form.querySelectorAll('.bucket-all').forEach(b => { b.checked = false; });
            this.lastBox = null;
            this.refresh();
        },
        tick(box) {
            box.checked = true;
            window.dispatchEvent(new CustomEvent('queue-open', { detail: box.dataset.step }));
            this.refresh();
        },
        scanLot() {
            const code = this.scan.trim().toLowerCase();
            if (!code) { return; }
            const box = this.boxes().find(b => b.dataset.code.split(' ').includes(code));
            if (!box) { this.scanMsg = 'Not found or not movable'; return; }
            this.scanMsg = '';
            this.scan = '';
            this.tick(box);
            this.$nextTick(() => {
                const row = box.closest('tbody, .qcard');
                row.scrollIntoView({ block: 'center', behavior: 'smooth' });
                row.classList.remove('qflash'); void row.offsetWidth; row.classList.add('qflash');
            });
        },
        moveStep(step) {
            this.clear();
            this.boxes().filter(b => b.dataset.step === step).forEach(b => { b.checked = true; });
            this.refresh();
            this.$nextTick(() => this.$refs.form.requestSubmit(this.$refs.move));
        },
        enterMoves(e) {
            const el = document.activeElement;
            const typing = el && (['TEXTAREA', 'SELECT', 'BUTTON', 'A'].includes(el.tagName) || (el.tagName === 'INPUT' && el.type !== 'checkbox'));
            if (!this.ticked.length || typing) { return; }
            e.preventDefault();
            this.$refs.form.requestSubmit(this.$refs.move);
        },
        submitting(e) {
            if (!this.chosen.length || !confirm(this.moveLabel + '?')) { e.preventDefault(); return; }
            try { localStorage.setItem('erp.queueOperator', this.$refs.operator.value); } catch (err) {}
        },
    };
}
</script>
@endsection
