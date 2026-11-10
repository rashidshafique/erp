@extends('layouts.app')
@section('title', 'Depreciation run · '.$period->format('F Y'))
@section('breadcrumb')<a href="{{ route('assets.index') }}">Fixed assets</a> / Depreciation run @endsection
@section('content')
@include('assets::_styles')
@php
    $num = fn ($v) => \App\Support\Formats::number((float) $v, 0);
    $tool = fn ($a) => $a->code.' '.$a->name.($a->toolCode() ? ' · '.$a->toolCode() : '');
    $methodWords = fn ($a) => match ($a->method) {
        'STRAIGHT_LINE' => 'Straight line · '.rtrim(rtrim(number_format((float) $a->life_years, 1), '0'), '.').' years',
        'REDUCING_BALANCE' => 'Reducing balance · '.rtrim(rtrim(number_format((float) $a->rate_percent, 1), '0'), '.').'% a year',
        default => '',
    };
    $state = $posted ? ['Posted', 'fa-ok'] : ['Not posted', 'fa-warn'];
@endphp
@error('run')<div class="alert alert-error">{{ $message }}</div>@enderror
<div class="fa-card">
    <div class="fa-bar">
        <div style="display:flex;align-items:center;gap:12px"><a href="{{ route('assets.index') }}">Fixed assets</a> / <strong>Depreciation run</strong> <span class="fa-pill {{ $state[1] }}">{{ $state[0] }}</span></div>
        <div style="display:flex;gap:8px">
            <a class="fa-btn" href="{{ route('assets.depreciation.sheet', ['period' => $period->format('Y-m')]) }}">Download sheet</a>
            @if ($posted)
                <button type="button" class="fa-btn" onclick="document.getElementById('reverse').showModal()">Reverse run…</button>
            @else
                <form method="POST" action="{{ route('assets.depreciation.post') }}">@csrf<input type="hidden" name="period" value="{{ $period->format('Y-m') }}">
                    <button class="fa-btn primary" @disabled($total <= 0 || $problem || $later)>Post run</button></form>
            @endif
        </div>
    </div>
    <div class="fa-grid">
        <div class="fa-main">
            <form method="GET" class="fa-filters">
                <label class="fa-chip"><span class="k">Period</span><b>{{ $period->format('F Y') }}</b><span aria-hidden="true">▾</span>
                    <select name="period" onchange="this.form.submit()" aria-label="Period">@foreach ($months as $m)<option value="{{ $m->format('Y-m') }}" @selected($m->format('Y-m') === $period->format('Y-m'))>{{ $m->format('F Y') }}</option>@endforeach</select></label>
                <span class="fa-sub">@if ($posted) Posted {{ $posted->posted_at?->format('j M') }}@if ($posted->postedBy) by {{ $posted->postedBy->name }}@endif as <a href="{{ route('ledger.journals.show', $posted->journal) }}">{{ $posted->journal->number }}</a>. @else Fills are read from moulding runs and core lots up to {{ $period->copy()->endOfMonth()->format('j M') }}. Run it after the last shift of the month. @endif</span>
            </form>

            @if ($reversed && ! $posted)
                <div class="fa-note" style="border-left-color:#2f5fd0;background:#f3f6fd">
                    <strong>{{ $reversed->label() }} · Reversed.</strong>
                    Posted <a href="{{ route('ledger.journals.show', $reversed->journal) }}">{{ $reversed->journal->number }}</a> · {{ $reversed->posted_at?->format('j M') }}@if ($reversed->postedBy) · {{ $reversed->postedBy->name }}@endif.
                    Reversed by <a href="{{ route('ledger.journals.show', $reversed->reversalJournal) }}">{{ $reversed->reversalJournal->number }}</a> · {{ $reversed->reversed_at?->format('j M') }}@if ($reversed->reversedBy) · {{ $reversed->reversedBy->name }}@endif.
                    Reason: {{ $reversed->reverse_reason }}@if ($reversed->reverse_detail) · {{ $reversed->reverse_detail }}@endif.
                    Below is the month run again with fills read fresh; both journals stay in the day book.
                </div>
            @endif

            <div class="fa-table"><table>
                <thead><tr><th>Usage-based · patterns and core boxes (dies)</th><th class="r">Fills this month</th><th class="r">Rate per fill</th><th class="r">Charge</th></tr></thead>
                <tbody>
                @forelse ($usage as $l)
                    <tr><td><a href="{{ route('assets.show', $l['asset']) }}">{{ $tool($l['asset']) }}</a>@if ($l['runs'])<div class="fa-sub">{{ implode(', ', array_slice($l['runs'], 0, 6)) }}@if (count($l['runs']) > 6) +{{ count($l['runs']) - 6 }}@endif</div>@endif</td>
                        <td class="r nw">{{ $num($l['fills']) }}</td><td class="r nw">{{ $num($l['rate']) }}</td><td class="r nw">{{ $num($l['charge']) }}@if ($l['capped'])<div class="fa-sub">to residual value</div>@endif</td></tr>
                @empty
                    <tr><td colspan="4" class="fa-sub">No usage-based tool in use.</td></tr>
                @endforelse
                <tr class="sum"><td>Usage-based</td><td></td><td></td><td class="r nw">{{ $num($usage->sum('charge')) }}</td></tr>
                </tbody>
            </table></div>

            <div class="fa-table"><table>
                <thead><tr><th>Time-based · plant, buildings, vehicles</th><th>Method</th><th class="r">Charge</th></tr></thead>
                <tbody>
                @forelse ($time as $l)
                    <tr><td><a href="{{ route('assets.show', $l['asset']) }}">{{ $l['asset']->code }} {{ $l['asset']->name }}</a></td><td class="fa-sub">{{ $methodWords($l['asset']) }}</td>
                        <td class="r nw">{{ $num($l['charge']) }}@if ($l['capped'])<div class="fa-sub">to residual value</div>@endif</td></tr>
                @empty
                    <tr><td colspan="3" class="fa-sub">No time-based asset in use.</td></tr>
                @endforelse
                <tr class="sum"><td>Time-based</td><td></td><td class="r nw">{{ $num($time->sum('charge')) }}</td></tr>
                </tbody>
            </table></div>
        </div>
        <aside class="fa-side">
            <div class="fa-two">
                <div><div class="fa-lab">Assets</div><div class="fa-big">{{ $usage->count() + $time->count() }}</div>@if (! $posted && $skipped)<div class="fa-sub">{{ $skipped }} in progress skipped</div>@endif</div>
                <div><div class="fa-lab">Total charge</div><div class="fa-big">Rs {{ $num($total) }}</div></div>
            </div>
            <div>
                <h3 class="fa-sec">Checks</h3>
                @if ($problem)
                    <div class="fa-check"><span class="i" style="color:#b3352c">✗</span><div>{{ $problem }}</div></div>
                @endif
                @if ($later && ! $posted)
                    <div class="fa-check"><span class="i" style="color:#b3352c">✗</span><div>A later month is posted<div class="fa-sub">Runs are posted in order; reverse the later month first.</div></div></div>
                @endif
                <div class="fa-check"><span class="i" style="color:{{ $noFills ? '#94610a' : '#1d7048' }}">{{ $noFills ? '!' : '✓' }}</span><div>{{ $noFills ? $noFills.' usage-based '.($noFills === 1 ? 'tool has' : 'tools have').' no fills this month' : 'Every usage-based tool has fills or none to record' }}</div></div>
                @if ($noTaxClass)
                    <div class="fa-check"><span class="i" style="color:#94610a">!</span><div>{{ $noTaxClass }} {{ $noTaxClass === 1 ? 'asset has' : 'assets have' }} no tax class<div class="fa-sub">Only the accounting book is affected by this run; set the tax class before year-end.</div></div></div>
                @endif
                <div class="fa-check"><span class="i" style="color:#1d7048">✓</span><div>No asset goes below its residual value @if ($capped)<div class="fa-sub">{{ $capped }} {{ $capped === 1 ? 'charge is' : 'charges are' }} cut to what is left.</div>@endif</div></div>
            </div>
            <div>
                <h3 class="fa-sec">{{ $posted ? 'Posted' : 'Will post' }}</h3>
                @forelse ($journal as $j)
                    <div class="fa-row"><span>{{ $j['account']?->code }} · {{ $j['account']?->name }}</span><span class="nw">{{ isset($j['debit']) ? 'Dr '.$num($j['debit']) : 'Cr '.$num($j['credit']) }}</span></div>
                @empty
                    <p class="fa-sub">Nothing to post for {{ $period->format('F Y') }}.</p>
                @endforelse
                @unless ($posted)<p class="fa-sub">Posts as one journal dated {{ $period->copy()->endOfMonth()->format('j M Y') }}. It takes the place of a manual depreciation journal in the day book.</p>@endunless
            </div>
        </aside>
    </div>
</div>

@if ($posted)
<dialog class="fa-dialog" id="reverse" @if ($errors->has('reason')) open @endif>
    @if ($block)
        <div class="body">
            <h2 style="margin:0">Reverse the {{ $posted->label() }} depreciation run</h2>
            <div class="fa-check"><span class="i" style="color:#b3352c">✗</span><div>{{ $block['later'] ? 'The '.$block['later']->label().' run is posted' : 'This run cannot be reversed' }}<div class="fa-sub">{{ $block['message'] }}</div></div></div>
            <div class="foot">
                <button type="button" class="fa-btn" onclick="this.closest('dialog').close()">Close</button>
                @if ($block['later'])<a class="fa-btn" href="{{ route('assets.depreciation', ['period' => $block['later']->period->format('Y-m')]) }}">Open {{ $block['later']->period->format('F') }} run</a>@endif
                <button class="fa-btn primary" disabled>Reverse run</button>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('assets.runs.reverse', $posted) }}">@csrf
            <h2 style="margin:0">Reverse the {{ $posted->label() }} depreciation run</h2>
            <p class="fa-sub" style="margin:0">{{ $posted->journal->number }} · Rs {{ $num($posted->total) }} · {{ $posted->lines->count() }} assets · posted {{ $posted->posted_at?->format('j M') }}@if ($posted->postedBy) by {{ $posted->postedBy->name }}@endif</p>
            <label>Reason<select name="reason" required>
                @foreach (['Fills on a moulding run were wrong', 'An asset was missing or should not be in the run', 'Wrong method, life or rate', 'Posted to the wrong month'] as $r)<option>{{ $r }}</option>@endforeach
            </select></label>
            <label>Detail<input name="detail" maxlength="300" placeholder="e.g. MR-97 count corrected from 60 to 36 moulds"></label>
            <div>
                <div class="fa-sec">What happens</div>
                <ul class="fa-sub" style="margin:0;padding-left:18px">
                    <li>Posts a journal dated {{ $posted->periodEnd()->format('j M') }}, the exact opposite of {{ $posted->journal->number }}. Nothing is deleted.</li>
                    <li>Book values of the {{ $posted->lines->count() }} assets go back to {{ $posted->period->copy()->subDay()->format('j M') }}.</li>
                    <li>The month opens again as Not posted, with fills read fresh from moulding runs and core lots.</li>
                </ul>
            </div>
            <div>
                <div class="fa-sec">Checks</div>
                <div class="fa-check"><span class="i" style="color:#1d7048">✓</span><div>{{ $posted->period->format('F') }} is open<div class="fa-sub">The books are not closed to it.</div></div></div>
                <div class="fa-check"><span class="i" style="color:#1d7048">✓</span><div>No later run is posted<div class="fa-sub">{{ $posted->period->copy()->addMonthNoOverflow()->format('F') }} has not been run.</div></div></div>
            </div>
            <div class="foot"><button type="button" class="fa-btn" onclick="this.closest('dialog').close()">Cancel</button><button class="fa-btn primary">Reverse run</button></div>
        </form>
    @endif
</dialog>
@endif
@endsection
