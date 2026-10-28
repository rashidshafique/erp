{{-- Close an enquiry (design 14c): won, lost or declined, with a reason. Won accepts the issued quotation, which creates the sales order,
     and opens the pattern development when a new pattern was decided. Lost and declined need a reason; lost can record the winning
     price; both can set a date to follow up. Opened by any [data-quick="closeDialog"] button. --}}
@php
    use App\Domains\Enquiry\Models\Enquiry;
    $cb = $errors->getBag('close');
    $outcome = old('outcome', 'LOST');
    $reason = old('close_reason');
    $issued = $e->quotations->where('status', 'ISSUED')->sortByDesc('version')->first();
    $shown = $issued ?? $e->quotations->sortByDesc('version')->first();
    $piece = $shown?->lines->first()?->unit_price;
@endphp
@include('partials.quick-dialog-assets')
<style>
    dialog.ec-modal { width: min(640px, calc(100vw - 32px)); }
    .ec-modal .qd-head { padding: 16px 24px; } .ec-modal .qd-head h2 { font-size: 15px; font-weight: 600; }
    .ec-modal .qd-body { padding: 20px 24px; display: flex; flex-direction: column; gap: 18px; font-size: 13px; }
    .ec-modal .qd-foot { padding: 14px 24px; border-top: 1px solid var(--border); justify-content: space-between; align-items: center; }
    .ec-modal .qd-foot > div { display: flex; gap: 8px; }
    .ec-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
    .ec-card { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border: 1px solid var(--border); border-radius: 7px; cursor: pointer; }
    .ec-card input { flex: none; margin: 2px 0 0; accent-color: var(--accent); } .ec-card.on { border-color: var(--accent); background: #f5f8fe; } .ec-card.on strong { font-weight: 600; }
    .ec-card strong { display: block; font-weight: 400; } .ec-card span { font-size: 12px; color: var(--muted); }
    .ec-f { display: flex; flex-direction: column; gap: 5px; font-size: 12px; color: var(--muted); } .ec-f input, .ec-f textarea { font-size: 13px; color: var(--text); }
    .ec-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .ec-chip { padding: 6px 12px; border-radius: 999px; border: 1px solid var(--border); cursor: pointer; color: var(--text); position: relative; font-size: 13px; }
    .ec-chip.on { background: var(--accent); border-color: var(--accent); color: #fff; } .ec-chip input { position: absolute; opacity: 0; pointer-events: none; }
    .ec-chip:has(input:focus-visible) { outline: 2px solid var(--accent); outline-offset: 1px; }
    .ec-grid2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .ec-ro { padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; background: #f8f8f9; color: var(--text); font-size: 13px; }
    .ec-won { padding: 10px 12px; border-radius: 7px; background: #f5f8fe; border: 1px solid color-mix(in srgb, var(--accent) 30%, #fff); color: var(--text); }
    .ec-modal [hidden] { display: none !important; }
    @media (max-width: 600px) { .ec-cards, .ec-grid2 { grid-template-columns: 1fr; } }
</style>
<dialog class="qd ec-modal" id="closeDialog" aria-labelledby="closeTitle" @if ($cb->any()) data-open-now @endif>
<form method="POST" action="{{ route('enquiries.close', $e) }}" id="ecForm" novalidate>@csrf
    <div class="qd-head"><h2 id="closeTitle">Close <code>{{ $e->code }}</code> · {{ $e->items->first()?->description ?? $e->title }}</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
    <div class="qd-body">
        @if ($cb->any())<div class="alert alert-error" role="alert">@foreach ($cb->all() as $m)<div>{{ $m }}</div>@endforeach</div>@endif
        <div class="ec-cards">
            @foreach (Enquiry::OUTCOMES as $k => [$name, $sub])
                <label class="ec-card @if ($outcome === $k) on @endif"><input type="radio" name="outcome" value="{{ $k }}" @checked($outcome === $k)><div><strong>{{ $name }}</strong><span>{{ $sub }}</span></div></label>
            @endforeach
        </div>
        <div class="ec-won" data-for="WON" @if ($outcome !== 'WON') hidden @endif>
            @if ($issued)Accepts <code>{{ $issued->code }}</code> and creates the sales order.@if ($e->pattern_route === 'NEW' && Route::has('developments.create')) A pattern development (new part) is opened for the new pattern.@endif
            @else There is no issued quotation to accept. Issue the quotation first, then close as won.@endif
        </div>
        <div class="ec-f" data-for="LOST DECLINED" @if ($outcome === 'WON') hidden @endif><span>Reason</span>
            <div class="ec-chips">@foreach (Enquiry::CLOSE_REASONS as $k => $l)<label class="ec-chip @if ($reason === $k) on @endif"><input type="radio" name="close_reason" value="{{ $k }}" @checked($reason === $k)>{{ $l }}</label>@endforeach</div>
        </div>
        <div class="ec-grid2">
            <div class="ec-f"><span>Our quote</span><div class="ec-ro">@if ($shown){{ $piece !== null ? \App\Support\Formats::money($piece).' / pc · ' : '' }}<code>{{ $shown->code }}</code>@else No quotation yet @endif</div></div>
            <label class="ec-f" data-for="LOST" @if ($outcome !== 'LOST') hidden @endif>Winning price, if known<input type="number" step="any" min="0" name="winning_price" value="{{ old('winning_price') }}" placeholder="per piece"></label>
        </div>
        <label class="ec-f">Note<textarea name="note" rows="3" placeholder="What the buyer said, and what to do next time">{{ old('note') }}</textarea></label>
        <div data-for="LOST DECLINED" @if ($outcome === 'WON') hidden @endif style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <label style="display:inline-flex;gap:8px;align-items:center"><input type="checkbox" name="follow_up" value="1" @checked(old('follow_up')) style="accent-color:var(--accent)"> Remind me to follow up on</label>
            <input type="date" name="follow_up_on" value="{{ old('follow_up_on', today()->addMonths(6)->toDateString()) }}" min="{{ today()->addDay()->toDateString() }}" style="width:auto" aria-label="Follow-up date">
        </div>
    </div>
    <div class="qd-foot"><span class="muted small">Draft quote and feasibility are kept for reference.</span><div><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit" id="ecSubmit">Close as {{ strtolower(Enquiry::OUTCOMES[$outcome][0]) }}</button></div></div>
</form>
</dialog>
<script>
(function () {
    var dlg = document.getElementById('closeDialog'), form = document.getElementById('ecForm');
    if (!dlg) return;
    function apply() {
        var o = (form.querySelector('[name=outcome]:checked') || {}).value || 'LOST';
        form.querySelectorAll('.ec-card').forEach(function (c) { c.classList.toggle('on', c.querySelector('input').checked); });
        form.querySelectorAll('.ec-chip').forEach(function (c) { c.classList.toggle('on', c.querySelector('input').checked); });
        form.querySelectorAll('[data-for]').forEach(function (g) {
            var on = g.getAttribute('data-for').split(' ').indexOf(o) >= 0; g.hidden = !on;
            g.querySelectorAll('input,select,textarea').forEach(function (i) { i.disabled = !on; });
        });
        document.getElementById('ecSubmit').textContent = 'Close as ' + ({ WON: 'won', LOST: 'lost', DECLINED: 'declined' })[o];
    }
    form.addEventListener('change', apply);
    form.addEventListener('submit', function () { document.getElementById('ecSubmit').disabled = true; });
    apply();
    if (dlg.hasAttribute('data-open-now')) { if (dlg.showModal) dlg.showModal(); else dlg.setAttribute('open', ''); }
})();
</script>
