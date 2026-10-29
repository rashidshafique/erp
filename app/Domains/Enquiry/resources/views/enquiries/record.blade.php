{{-- The enquiry record once it is sent (design 14b): the stage bar, the tooling and pattern decision, where feasibility stands, questions to
     the customer, the quotes and the log; on the right the key facts, the drawing and everything linked to it. Edit details opens the
     intake page (1b), Close enquiry the closing dialog (14c), and Start pattern development the development dialog (12a, new part).
     A won, lost, declined or cancelled enquiry shows the same page without the actions. --}}
@extends('layouts.app')
@section('title', $e->code)
@section('breadcrumb')<a href="{{ route('enquiries.index') }}">Enquiries</a> / {{ $e->code }} @endsection
@section('content')
@php
    use App\Domains\Enquiry\Models\Enquiry;
    use App\Domains\Enquiry\Models\EnquiryEvent;
    $open = $e->isOpen();
    $stage = $e->stage();
    $step = $e->stageStep();
    $tones = ['FEASIBILITY' => 'er-blue', 'QUOTING' => 'er-blue', 'QUOTED' => 'er-ok', 'ON_HOLD' => 'er-bad', 'WON' => 'er-ok', 'LOST' => 'er-bad', 'DECLINED' => 'er-grey', 'CANCELLED' => 'er-grey'];
    $qtones = ['DRAFT' => 'er-grey', 'ISSUED' => 'er-blue', 'ACCEPTED' => 'er-ok', 'REJECTED' => 'er-bad', 'EXPIRED' => 'er-bad', 'SUPERSEDED' => 'er-grey'];
    $etones = ['EMAIL' => 'er-blue', 'CALL' => 'er-blue', 'MEETING' => 'er-blue', 'NOTE' => 'er-grey', 'SYSTEM' => 'er-grey'];
    $days = $e->daysToReply();
    $value = $e->annualValue();
    $route = old('pattern_route', $e->pattern_route);
    $openQuestions = $e->questions->filter->isOpen()->count();
    $f = $feasibility;
    $devOpen = $development && $development->status === 'OPEN';
    $money = fn ($v) => $v >= 1000000 ? \App\Support\Formats::withCurrency(rtrim(rtrim(number_format($v / 1000000, 2), '0'), '.').'M') : \App\Support\Formats::money($v, 0);
@endphp
@include('partials.search-select')
<style>
    .er-card { background: var(--surface, #fff); border: 1px solid var(--border); border-radius: 10px; }
    .er-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 28px; border-bottom: 1px solid var(--border); }
    .er-crumb { display: flex; align-items: center; gap: 16px; font-size: 13px; flex-wrap: wrap; } .er-crumb a { color: var(--muted); }
    .er-btn { padding: 7px 14px; border-radius: 6px; background: var(--surface, #fff); border: 1px solid var(--border); font-size: 13px; color: var(--text); cursor: pointer; text-decoration: none !important; white-space: nowrap; }
    .er-btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 500; }
    .er-pill { font-size: 11.5px; padding: 2px 8px; border-radius: 999px; font-weight: 500; white-space: nowrap; background: #f0f1f3; color: #6b6e73; }
    .er-pill.er-blue { background: #eaf0fd; color: var(--accent); } .er-pill.er-ok { background: #e7f4ec; color: #1d7048; } .er-pill.er-warn { background: #fbf0dc; color: #94610a; } .er-pill.er-bad { background: #fbe7e5; color: #b3352c; }
    .er-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; }
    .er-main { padding: 20px 28px; display: flex; flex-direction: column; gap: 22px; min-width: 0; }
    .er-side { border-left: 1px solid var(--border); padding: 20px 24px; background: #f8f8f9; display: flex; flex-direction: column; gap: 18px; font-size: 13px; min-width: 0; }
    .er-steps { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 6px; }
    .er-steps > div { display: flex; flex-direction: column; gap: 6px; font-size: 12px; color: var(--muted); } .er-steps > div::before { content: ''; height: 4px; border-radius: 2px; background: #e6e7e9; }
    .er-steps > div.done::before { background: var(--accent); } .er-steps > div.now { color: var(--text); font-weight: 600; }
    .er-two { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .er-box { border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; min-width: 0; }
    .er-h { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }
    .er-opts { display: flex; flex-direction: column; gap: 6px; }
    .er-opt { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border: 1px solid var(--border); border-radius: 7px; cursor: pointer; }
    .er-opt input { flex: none; margin: 2px 0 0; accent-color: var(--accent); } .er-opt.on { border-color: var(--accent); background: #f5f8fe; } .er-opt.on strong { font-weight: 600; }
    .er-opt strong { display: block; font-weight: 400; } .er-opt span { font-size: 12px; color: var(--muted); }
    .er-kv { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; } .er-kv .k { font-size: 12px; color: var(--muted); }
    .er-table { margin: 0 -12px; overflow-x: auto; } .er-table table { width: 100%; } .er-table td { vertical-align: top; }
    .er-r { text-align: right; }
    .er-log > div { display: grid; grid-template-columns: 110px minmax(0, 1fr) 80px; gap: 12px; padding: 11px 0; border-bottom: 1px solid #eff0f2; }
    .er-log .who { font-size: 12px; color: var(--muted); } .er-log .when { font-size: 12px; color: var(--muted); text-align: right; }
    .er-add { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-top: 4px; } .er-add input, .er-add select { width: auto; } .er-add .grow { flex: 1 1 260px; min-width: 0; }
    .er-row { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px solid var(--border); } .er-row:last-child { border-bottom: 0; } .er-row > span:last-child { color: var(--muted); text-align: right; }
    .er-preview { height: 140px; border-radius: 8px; border: 1px solid #e0e1e4; background: repeating-linear-gradient(135deg, #fff 0 8px, #f1f2f4 8px 16px); display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .er-preview img { max-width: 100%; max-height: 100%; object-fit: contain; } .er-preview iframe { width: 100%; height: 100%; border: 0; background: #fff; }
    .er-preview code { font-size: 11.5px; color: var(--muted); background: #fff; padding: 4px 8px; border-radius: 4px; }
    .er-muted { color: var(--muted); } .er-link { color: var(--accent); font-size: 12.5px; cursor: pointer; background: none; border: 0; padding: 0; }
    .er-answer { display: flex; gap: 6px; } .er-answer input { flex: 1; min-width: 0; }
    @media (max-width: 1100px) { .er-grid { grid-template-columns: 1fr; } .er-side { border-left: 0; border-top: 1px solid var(--border); } }
    @media (max-width: 700px) { .er-two, .er-kv { grid-template-columns: 1fr; } .er-bar, .er-main { padding-left: 16px; padding-right: 16px; } .er-log > div { grid-template-columns: 1fr; gap: 4px; } .er-log .when { text-align: left; } }
</style>

<div class="tab-marker" data-tab-start="Enquiry"></div>
@if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
<div class="er-card">
    <div class="er-bar">
        <div class="er-crumb"><a href="{{ route('enquiries.index') }}">Enquiries</a><span class="er-muted">/</span><code>{{ $e->code }}</code><span>{{ $e->items->first()?->description ?? $e->title }}</span><span class="er-pill {{ $tones[$stage] ?? '' }}">{{ Enquiry::STAGES[$stage] }}</span></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @if ($open)
                <a class="er-btn" href="{{ route('enquiries.edit', $e) }}">Edit details</a>
                <button class="er-btn" type="button" data-quick="closeDialog">Close enquiry</button>
                @if (Route::has('developments.create'))
                    @if ($devOpen)<a class="er-btn primary" href="{{ route('developments.show', $development) }}">Open {{ $development->code }}</a>
                    @else<a class="er-btn primary" href="{{ route('developments.create', ['from' => 'enquiry:'.$e->id]) }}">Start pattern development</a>@endif
                @endif
            @else
                <a class="er-btn" href="{{ route('enquiries.index') }}">All enquiries</a>
            @endif
        </div>
    </div>
    @if ($errors->hasAny(['pattern_route', 'pattern_id', 'question', 'answer', 'text', 'kind']))<div class="alert alert-error" style="margin:16px 28px 0">@foreach ($errors->all() as $m)<div>{{ $m }}</div>@endforeach</div>@endif
    @if (! $open)<div class="alert" style="margin:16px 28px 0">{{ Enquiry::STAGES[$stage] }} {{ $e->closed_at?->displayDate() }}@if ($e->close_reason): {{ Enquiry::CLOSE_REASONS[$e->close_reason] ?? $e->close_reason }}@endif @if ($e->winning_price) · winning price {{ \App\Support\Formats::money($e->winning_price) }} / pc @endif @if ($e->lost_reason) · {{ $e->lost_reason }}@endif @if ($e->follow_up_on) · follow up on {{ $e->follow_up_on->displayDate() }}@endif</div>@endif
    <div class="er-grid">
        <div class="er-main">
            <div class="er-steps">
                @foreach (Enquiry::STAGE_STEPS as $k => $label)
                    @php $i = $loop->index; @endphp
                    <div @class(['done' => $i <= $step, 'now' => $i === $step])>{{ $k === 'CLOSED' && ! $open ? Enquiry::STAGES[$stage] : $label }}</div>
                @endforeach
            </div>

            <div class="er-two">
                <div class="er-box"><div class="er-h">Tooling and pattern</div>
                    <form method="POST" action="{{ route('enquiries.pattern', $e) }}" id="patternForm">@csrf
                    <fieldset @disabled(! $open) style="border:0;margin:0;padding:0;min-width:0">
                        <div class="er-opts">
                            @foreach (Enquiry::PATTERN_ROUTES as $k => [$name, $sub])
                                <label class="er-opt @if ($route === $k) on @endif"><input type="radio" name="pattern_route" value="{{ $k }}" @checked($route === $k)><div><strong>{{ $name }}</strong><span>{{ $k === 'EXISTING' && $e->pattern ? $e->pattern->code.' · '.$e->pattern->name : ($k === 'NEW' && $patternText && $route === 'NEW' ? $patternText.($devOpen ? ' · '.$development->code : '') : $sub) }}</span></div></label>
                            @endforeach
                        </div>
                        <div id="patternPick" @if ($route !== 'EXISTING') hidden @endif style="margin-top:8px"><select name="pattern_id" data-search-select aria-label="Pattern from the register"><option value="">— choose the pattern —</option>@foreach ($patterns as $p)<option value="{{ $p->id }}" data-sub="{{ $p->name }}" @selected(old('pattern_id', $e->pattern_id) === $p->id)>{{ $p->code }}</option>@endforeach</select></div>
                        @if ($open)<div style="margin-top:8px"><button class="er-btn" type="submit">Save decision</button></div>@endif
                    </fieldset>
                    </form>
                    @if ($patternLife)
                        <div class="alert alert-{{ $patternLife['status'] === 'LIFE_EXCEEDED' ? 'error' : 'warning' }}" style="margin-top:10px">{{ $e->pattern->code }}: {{ strtolower($patternLife['label']) }}{{ $patternLife['percent'] !== null ? ' ('.round($patternLife['percent']).'% of its life used)' : '' }}.
                            @if (Route::has('developments.create'))<a href="{{ route('developments.create', ['from' => 'pattern:'.$e->pattern_id]) }}">Start replacement</a>@endif</div>
                    @endif
                </div>
                <div class="er-box"><div class="er-h">Feasibility</div>
                    <div class="er-kv">
                        <div><div class="k">Review</div><div>@if ($f['first'] && Route::has('feasibility.show'))<a href="{{ route('feasibility.show', $f['first']) }}">Open</a> · @endif{{ $f['signed'] }} of {{ $f['expected'] }} signed</div></div>
                        <div><div class="k">Waiting on</div><div>{{ $f['waiting']->isEmpty() ? 'No one' : $f['waiting']->implode(', ') }}</div></div>
                        <div><div class="k">Risks raised</div><div>{{ $f['risks']->count() }}@if ($f['risks']->isNotEmpty() && $f['risks']->first()->notes) · {{ \Illuminate\Support\Str::limit($f['risks']->first()->notes, 60) }}@endif</div></div>
                        <div><div class="k">Parts decided</div><div>{{ $f['decided'] }} of {{ $e->items->count() }}</div></div>
                    </div>
                </div>
            </div>

            <div style="display:flex;flex-direction:column;gap:8px">
                <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px"><div class="er-h">Questions to customer · {{ $openQuestions }} open</div>@if ($open)<button type="button" class="er-link" onclick="var f=document.getElementById('qForm');f.hidden=!f.hidden;if(!f.hidden)f.querySelector('input').focus()">+ Add question</button>@endif</div>
                @if ($open)<form method="POST" action="{{ route('enquiries.questions.store', $e) }}" class="er-add" id="qForm" @unless ($errors->has('question')) hidden @endunless>@csrf<input class="grow" name="question" maxlength="2000" value="{{ old('question') }}" placeholder="e.g. Is machining stock on face B fixed?" aria-label="Question"><input type="date" name="asked_on" value="{{ today()->toDateString() }}" aria-label="Asked on"><button class="er-btn" type="submit">Add</button></form>@endif
                @if ($e->questions->isEmpty())
                    <div class="er-muted" style="font-size:12.5px">No questions yet.</div>
                @else
                <div class="er-table"><table><thead><tr><th>Question</th><th>Asked</th><th>Answer</th></tr></thead><tbody>
                    @foreach ($e->questions as $q)
                        <tr><td>{{ $q->question }}</td><td style="white-space:nowrap">{{ $q->asked_on->displayShortDate() }}</td>
                            <td>@if (! $q->isOpen()){{ $q->answer }} · {{ $q->answered_on?->displayShortDate() }}
                                @elseif ($open)<form method="POST" action="{{ route('enquiries.questions.answer', [$e, $q]) }}" class="er-answer">@csrf @method('PUT')<span class="er-pill er-warn">Open</span><input name="answer" maxlength="2000" placeholder="Their answer" aria-label="Answer"><button class="er-btn" type="submit">Save</button></form>
                                @else<span class="er-pill er-warn">Open</span>@endif</td></tr>
                    @endforeach
                </tbody></table></div>
                @endif
            </div>

            <div style="display:flex;flex-direction:column;gap:8px">
                <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px"><div class="er-h">Quotes</div>
                    @if ($open)<form method="POST" action="{{ route('enquiries.quotations.store', $e) }}">@csrf<button class="er-link" type="submit">{{ $e->quotations->firstWhere('status', 'DRAFT') ? 'Open the draft' : ($e->quotations->isEmpty() ? '+ Create quotation' : '+ New version') }}</button></form>@endif</div>
                @if ($e->quotations->isEmpty())
                    <div class="er-muted" style="font-size:12.5px">No quotation yet. Assess each part, then create the quotation to price them.</div>
                @else
                <div class="er-table"><table><thead><tr><th>Code</th><th>Version</th><th class="er-r">Piece price</th><th class="er-r">Tooling</th><th>Status</th></tr></thead><tbody>
                    @foreach ($e->quotations as $q)
                        @php $prices = $q->lines->pluck('unit_price')->filter(); $tooling = $q->lines->sum('tooling_cost'); @endphp
                        <tr><td><a href="{{ route('quotations.show', $q) }}"><code>{{ $q->code }}</code></a></td><td>{{ $q->status === 'DRAFT' ? 'Draft ' : '' }}v{{ $q->version }}</td>
                            <td class="er-r">{{ $prices->isEmpty() ? '—' : ($prices->count() > 1 ? 'from ' : '').\App\Support\Formats::money($prices->min()) }}</td>
                            <td class="er-r">{{ $tooling > 0 ? \App\Support\Formats::money($tooling, 0) : '—' }}</td>
                            <td><span class="er-pill {{ $qtones[$q->status] ?? '' }}">{{ ucfirst(strtolower($q->status)) }}</span></td></tr>
                    @endforeach
                </tbody></table></div>
                @endif
            </div>

            <div style="display:flex;flex-direction:column">
                <div class="er-h">Log</div>
                <form method="POST" action="{{ route('enquiries.log', $e) }}" class="er-add" style="margin:8px 0 4px">@csrf
                    <select name="kind" aria-label="Kind of entry">@foreach (['EMAIL', 'CALL', 'MEETING', 'NOTE'] as $k)<option value="{{ $k }}" @selected(old('kind', 'NOTE') === $k)>{{ EnquiryEvent::KINDS[$k] }}</option>@endforeach</select>
                    <select name="direction" aria-label="In or out"><option value="">—</option><option value="IN" @selected(old('direction') === 'IN')>In</option><option value="OUT" @selected(old('direction') === 'OUT')>Out</option></select>
                    <input class="grow" name="text" maxlength="3000" value="{{ old('text') }}" placeholder="What was said or sent" aria-label="Log entry">
                    <input name="contact" maxlength="150" value="{{ old('contact') }}" placeholder="Who, e.g. buyer" aria-label="Contact" style="width:140px">
                    <input type="date" name="happened_on" value="{{ old('happened_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" aria-label="Date">
                    <button class="er-btn" type="submit">Add</button>
                </form>
                <div class="er-log">
                    @forelse ($e->events as $ev)
                        <div><div><span class="er-pill {{ $etones[$ev->kind] ?? '' }}">{{ EnquiryEvent::KINDS[$ev->kind] ?? $ev->kind }}</span></div>
                            <div style="display:flex;flex-direction:column;gap:3px"><div>{{ $ev->text }}</div><div class="who">{{ collect([$ev->direction ? ['IN' => 'In', 'OUT' => 'Out'][$ev->direction] : null, $ev->contact, $ev->user_name])->filter()->implode(' · ') }}</div></div>
                            <div class="when">{{ $ev->happened_on->displayShortDate() }}</div></div>
                    @empty
                        <div class="er-muted" style="display:block;font-size:12.5px">Nothing logged yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <aside class="er-side">
            <div><code class="er-muted">{{ $e->code }}</code><div style="font-size:16px;font-weight:600;margin-top:2px">{{ $e->items->first()?->description ?? $e->title }}</div><div class="er-muted">{{ $e->customer?->name }}@if ($e->customer_reference) · <code>{{ $e->customer_reference }}</code>@endif</div></div>
            <div class="er-kv">
                <div><div class="k">Received</div><div>{{ $e->received_on?->displayShortDate() }}</div></div>
                <div><div class="k">Reply by</div><div>{{ $e->due_on ? $e->due_on->displayShortDate().($open && $days !== null ? ' · '.($days < 0 ? abs($days).' d late' : $days.' d') : '') : '—' }}</div></div>
                <div><div class="k">Owner</div><div>{{ $e->owner?->name ?? '—' }}</div></div>
                <div><div class="k">Annual value</div><div>{{ $value ? $money($value) : '—' }}</div></div>
            </div>
            <div class="er-preview">
                @if ($preview && $preview->isImage())<img src="{{ route('attachments.download', $preview) }}" alt="{{ $preview->original_name }}">
                @elseif ($preview)<iframe src="{{ route('attachments.download', $preview) }}" title="{{ $preview->original_name }}"></iframe>
                @else<code>{{ $files->first()?->original_name ?? 'No drawing yet' }}</code>@endif
            </div>
            <div><div class="er-h">Linked</div><div style="margin-top:8px">
                <div class="er-row"><span>Intake</span><span>@if ($open)<a href="{{ route('enquiries.edit', $e) }}">Edit details</a>@else{{ $e->items->count() }} {{ \Illuminate\Support\Str::plural('part', $e->items->count()) }}@endif</span></div>
                <div class="er-row"><span>Feasibility</span><span>@if ($f['first'] && Route::has('feasibility.show'))<a href="{{ route('feasibility.show', $f['first']) }}">{{ $f['decided'] === $e->items->count() && $e->items->isNotEmpty() ? 'Decided' : 'In progress' }}</a>@else — @endif</span></div>
                <div class="er-row"><span>Quote</span><span>@if ($latestQuote)<a href="{{ route('quotations.show', $latestQuote) }}"><code>{{ $latestQuote->code }}</code></a> {{ strtolower($latestQuote->status) }}@else Not yet @endif</span></div>
                <div class="er-row"><span>Development</span><span>@if ($development && Route::has('developments.show'))@foreach ($e->developments->sortBy('code') as $dv)<span style="display:block"><a href="{{ route('developments.show', $dv) }}"><code>{{ $dv->code }}</code></a> {{ $dv->status === 'OPEN' ? strtolower($dv->stageName()) : strtolower($dv->status) }}</span>@endforeach @else Not started @endif</span></div>
                <div class="er-row"><span>Sales order</span><span>@if ($order && Route::has('sales-orders.show'))<a href="{{ route('sales-orders.show', $order) }}"><code>{{ $order->order_number }}</code></a>@else On win @endif</span></div>
            </div></div>
        </aside>
    </div>
</div>

@if ($open)@include('enquiry::enquiries._close_dialog')@endif

<div class="tab-marker" data-tab-start="Files & history"></div>
@include('attachments._panel', ['type' => 'enquiry', 'id' => $e->id])
@include('audit._history', ['type' => 'Enquiry', 'id' => $e->id])

<script>
(function () {
    var form = document.getElementById('patternForm');
    if (!form) return;
    form.addEventListener('change', function () {
        var r = (form.querySelector('[name=pattern_route]:checked') || {}).value;
        form.querySelectorAll('.er-opt').forEach(function (o) { o.classList.toggle('on', o.querySelector('input').checked); });
        document.getElementById('patternPick').hidden = r !== 'EXISTING';
    });
})();
</script>
@endsection
