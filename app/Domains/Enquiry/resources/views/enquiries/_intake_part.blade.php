{{-- One part on the enquiry intake page (design 1b): the part, its volumes, its quality requirements and the inputs the quotation prices
     from. $key is its index in items[] ('__I__' in the template for a new part), $it the saved part or null, $num its number on the page.
     A saved part also shows where its feasibility stands and its casting analysis; those controls post to their own small forms. --}}
@php
    $n = "items[$key]";
    $v = fn (string $k, mixed $d = null) => old("items.$key.$k", $it?->{$k} ?? $d);
    $err = fn (string $k) => $errors->first("items.$key.$k");
    $q = $it?->quality ?? [];
    $tests = (array) old("items.$key.quality", $q['tests'] ?? []);
    $level = old("items.$key.ppap_level", $q['ppap_level'] ?? '');
    $fcls = ['PENDING' => 'ei-warn', 'OK' => 'ei-ok', 'CONDITIONAL' => 'ei-warn', 'NOT_FEASIBLE' => 'ei-bad'];
@endphp
<div class="ei-part" data-part data-key="{{ $key }}" @if (! $it) data-new @endif>
    @if ($it)<input type="hidden" name="{{ $n }}[id]" value="{{ $it->id }}">@endif
    <div class="ei-part-head">
        <div class="ei-h">Part <span data-num>{{ $num }}</span>@if ($it) <span class="ei-pill {{ $fcls[$it->feasibility_status] ?? '' }}">{{ \App\Domains\Enquiry\Models\EnquiryItem::FEASIBILITY[$it->feasibility_status] }}</span>@endif</div>
        @if ($open)
            @if ($it)<label class="ei-remove"><input type="checkbox" name="{{ $n }}[remove]" value="1" @checked(old("items.$key.remove"))> Remove this part</label>
            @else<button type="button" class="ei-link" data-remove-part>Remove</button>@endif
        @endif
    </div>
    @if ($err('remove'))<div class="error-text">{{ $err('remove') }}</div>@endif

    <div class="ei-grid4">
        <label class="ei-f ei-s2">Description <span class="ei-star"></span><input name="{{ $n }}[description]" value="{{ $v('description') }}" maxlength="200" placeholder="e.g. Brake caliper bracket, front LH" @if ($err('description')) aria-invalid="true" @endif>@if ($err('description'))<span class="error-text">{{ $err('description') }}</span>@endif</label>
        <label class="ei-f">Drawing no.<input name="{{ $n }}[drawing_ref]" value="{{ $v('drawing_ref') }}" maxlength="100" @if ($err('drawing_ref')) aria-invalid="true" @endif>@if ($err('drawing_ref'))<span class="error-text">{{ $err('drawing_ref') }}</span>@endif</label>
        <label class="ei-f">Revision<input name="{{ $n }}[drawing_rev]" value="{{ $v('drawing_rev') }}" maxlength="20"></label>
        <div class="ei-f ei-s2" data-own hidden><label for="p-{{ $key }}">Which of our parts? <span class="ei-star"></span></label>
            <select id="p-{{ $key }}" name="{{ $n }}[product_id]" data-search-select="photos" data-product><option value="">— choose —</option>@foreach ($products as $p)<option value="{{ $p->id }}" data-sub="{{ $p->code }}" @isset($productPhotos[$p->id]) data-img="{{ $productPhotos[$p->id] }}" @endisset data-name="{{ $p->name }}" data-weight="{{ $p->nominal_weight_kg }}" data-revs="{{ $p->revisions->map(fn ($r) => $r->revision.' ('.strtolower($r->status).')')->implode(', ') ?: 'no revisions yet' }}" @selected($v('product_id') === $p->id)>{{ $p->name }}</option>@endforeach</select>
            <span class="ei-help" data-prod-info></span>@if ($err('product_id'))<span class="error-text">{{ $err('product_id') }}</span>@endif</div>
        @php $mat = $v('material_text'); $known = $mat === null || $mat === '' || $mat === '__new__' || $grades->contains('code', $mat); @endphp
        <div class="ei-f"><label for="m-{{ $key }}">Alloy spec</label>
            <select id="m-{{ $key }}" name="{{ $n }}[material_text]" data-search-select data-material><option value="">— choose —</option>
                @unless ($known)<option value="{{ $mat }}" selected>{{ $mat }}</option>@endunless
                @foreach ($grades as $g)<option value="{{ $g->code }}" data-sub="{{ $g->name }}" data-family="{{ $g->family }}" @selected($mat === $g->code)>{{ $g->code }}</option>@endforeach
                <option value="__new__" data-sub="Not in the list" @selected($mat === '__new__')>+ Add new grade…</option></select></div>
        <label class="ei-f" data-material-new @if ($mat !== '__new__') hidden @endif>New grade<input name="{{ $n }}[material_new]" value="{{ old("items.$key.material_new") }}" placeholder="e.g. EN-GJS-500-7" @if ($mat !== '__new__') disabled @endif>@if ($err('material_new'))<span class="error-text">{{ $err('material_new') }}</span>@endif</label>
        <label class="ei-f">Casting weight, kg<input type="number" step="any" min="0" name="{{ $n }}[weight_kg]" value="{{ $v('weight_kg') }}"></label>
        <label class="ei-f">Process<select name="{{ $n }}[process]"><option value="">— decide later —</option>@foreach (\App\Domains\Enquiry\Models\EnquiryItem::PROCESSES as $k => $l)<option value="{{ $k }}" @selected($v('process') === $k)>{{ $l }}</option>@endforeach</select></label>
        <label class="ei-f">Supply condition<select name="{{ $n }}[supply_condition]"><option value="">— not stated —</option>@foreach (\App\Domains\Enquiry\Models\EnquiryItem::SUPPLY_CONDITIONS as $k => $l)<option value="{{ $k }}" @selected($v('supply_condition') === $k)>{{ $l }}</option>@endforeach</select></label>
        <label class="ei-f">Iron family<select name="{{ $n }}[family]" data-family><option value="">— decide later —</option><option value="GREY" @selected($v('family') === 'GREY')>Grey iron</option><option value="DUCTILE" @selected($v('family') === 'DUCTILE')>Ductile iron</option></select></label>
        <div class="ei-f ei-checks"><label><input type="checkbox" name="{{ $n }}[heat_treatment]" value="1" @checked($v('heat_treatment'))> Heat treated</label><label><input type="checkbox" name="{{ $n }}[ndt]" value="1" @checked($v('ndt'))> NDT required</label></div>
    </div>

    <div class="ei-sub">Volumes</div>
    <div class="ei-grid4">
        <label class="ei-f">Annual volume<input type="number" min="0" name="{{ $n }}[annual_qty]" value="{{ $v('annual_qty') }}" placeholder="pcs"></label>
        <label class="ei-f">Batch / call-off<input name="{{ $n }}[call_off]" maxlength="60" value="{{ $v('call_off') }}" placeholder="e.g. 8,000 / month"></label>
        <label class="ei-f">SOP<input type="date" name="{{ $n }}[sop_on]" value="{{ old("items.$key.sop_on", $it?->sop_on?->toDateString()) }}"></label>
        <label class="ei-f">Programme life, years<input type="number" min="1" max="50" name="{{ $n }}[programme_years]" value="{{ $v('programme_years') }}"></label>
        <label class="ei-f">First batch<input type="number" min="0" name="{{ $n }}[first_batch_qty]" value="{{ $v('first_batch_qty') }}"><span class="ei-help">The quantity the quotation prices.</span></label>
        <label class="ei-f">Target price each<input type="number" step="any" min="0" name="{{ $n }}[target_price]" value="{{ $v('target_price') }}"></label>
    </div>

    <div class="ei-sub">Quality requirements</div>
    <div class="ei-chips" data-quality>
        @foreach (\App\Domains\Enquiry\Models\EnquiryItem::QUALITY as $k => $l)
            <label class="ei-chip @if (in_array($k, $tests, true)) on @endif"><input type="checkbox" name="{{ $n }}[quality][]" value="{{ $k }}" @checked(in_array($k, $tests, true)) @if ($k === 'PPAP') data-ppap @endif>{{ $k === 'PPAP' ? 'PPAP' : $l }}<span data-ppap-label>@if ($k === 'PPAP' && $level) Level {{ $level }}@endif</span></label>
        @endforeach
    </div>
    <div class="ei-grid4" style="margin-top:10px">
        <label class="ei-f" data-ppap-level @unless (in_array('PPAP', $tests, true)) hidden @endunless>PPAP level <span class="ei-star"></span><select name="{{ $n }}[ppap_level]" @unless (in_array('PPAP', $tests, true)) disabled @endunless><option value="">— choose —</option>@foreach (range(1, 5) as $lv)<option value="{{ $lv }}" @selected((string) $level === (string) $lv)>Level {{ $lv }}</option>@endforeach</select>@if ($err('ppap_level'))<span class="error-text">{{ $err('ppap_level') }}</span>@endif</label>
        <label class="ei-f ei-s3">+ Customer-specific<input name="{{ $n }}[quality_other]" maxlength="200" value="{{ old("items.$key.quality_other", $q['other'] ?? '') }}" placeholder="e.g. CSR v4.2, salt spray 240 h"></label>
    </div>

    <details class="ei-cost" @if ($errors->hasAny(["items.$key.cores_count", "items.$key.machining_cost", "items.$key.other_cost", "items.$key.tooling_cost"])) open @endif><summary>Costing inputs for the quotation</summary>
        <div class="ei-grid4">
            <label class="ei-f">Cores<input type="number" min="0" max="200" name="{{ $n }}[cores_count]" value="{{ $v('cores_count') }}"><span class="ei-help">Used when there is no analysis.</span></label>
            <label class="ei-f">Machining cost each<input type="number" step="any" min="0" name="{{ $n }}[machining_cost]" value="{{ $v('machining_cost', 0) }}"></label>
            <label class="ei-f">Other cost each<input type="number" step="any" min="0" name="{{ $n }}[other_cost]" value="{{ $v('other_cost', 0) }}"><span class="ei-help">Coating, painting, outside processes.</span></label>
            <label class="ei-f">Tooling cost<input type="number" step="any" min="0" name="{{ $n }}[tooling_cost]" value="{{ $v('tooling_cost', 0) }}"><span class="ei-help">Zero if the customer supplies it.</span></label>
            <label class="ei-f ei-s4">Notes<textarea name="{{ $n }}[notes]" rows="2">{{ $v('notes') }}</textarea></label>
        </div>
    </details>

    @if ($it)
        @php $a = $it->analysis; $severe = $a && collect($a->result['hotspots'] ?? [])->contains('role', 'ISOLATED_SEVERE'); @endphp
        <div class="ei-strip">
            <div><span class="ei-lab">Feasibility</span> {{ \App\Domains\Enquiry\Models\EnquiryItem::FEASIBILITY[$it->feasibility_status] }}@if ($it->feasibility_notes) · <span class="ei-muted">{{ $it->feasibility_notes }}</span>@endif
                @if (Route::has('feasibility.show')) · <a href="{{ route('feasibility.show', $it) }}">Open review</a>@endif</div>
            <div><span class="ei-lab">Casting analysis</span>
                @if ($a)<a href="{{ route('casting-analyses.show', $a) }}">{{ $a->code }}</a> score {{ $a->score }} ({{ $a->score_letter }})@if ($severe) · <span class="ei-pill ei-bad">cut-off hotspot</span>@endif
                @else<span class="ei-muted">None yet</span>@endif
                @if ($open) · <a href="{{ route('casting-analyses.create', ['enquiry_item' => $it->id]) }}">Analyse a model</a>@endif</div>
            @if ($open)
                <div class="ei-strip-forms">
                    <span class="ei-inline"><select name="casting_analysis_id" form="ana-{{ $it->id }}" aria-label="Link an analysis to part {{ $num }}"><option value="">— no analysis —</option>@foreach ($analyses as $an)<option value="{{ $an->id }}" @selected($it->casting_analysis_id === $an->id)>{{ $an->code }} — {{ $an->name }} (score {{ $an->score }})</option>@endforeach</select><button class="ei-btn" type="submit" form="ana-{{ $it->id }}">Link analysis</button></span>
                    <span class="ei-inline"><select name="feasibility_status" form="fea-{{ $it->id }}" aria-label="Feasibility decision for part {{ $num }}">@foreach (['OK', 'CONDITIONAL', 'NOT_FEASIBLE'] as $s)<option value="{{ $s }}" @selected($it->feasibility_status === $s)>{{ \App\Domains\Enquiry\Models\EnquiryItem::FEASIBILITY[$s] }}</option>@endforeach</select><input name="feasibility_notes" form="fea-{{ $it->id }}" value="{{ $it->feasibility_notes }}" placeholder="Conditions or reason, unless plainly feasible" aria-label="Feasibility conditions for part {{ $num }}"><button class="ei-btn" type="submit" form="fea-{{ $it->id }}">Record decision</button></span>
                </div>
            @endif
        </div>
    @endif
</div>
