{{-- Fields of the "Link a pattern" dialog on the product page (partials.quick-dialog). $product is the product. The revision list
     follows the chosen pattern; a pattern with no revision gets its first one when the link is saved. --}}
@if ($part === 'required')
@php
    $patterns = \App\Models\Pattern::with(['revisions' => fn ($q) => $q->orderByDesc('sequence')])->orderBy('name')->orderBy('code')->get();
    $photos = \App\Models\Attachment::coverUrlsFor('pattern', $patterns->pluck('id')->all());
@endphp
<div class="field field-full"><label for="lp-pattern">Pattern <span class="required">*</span></label>
    <select id="lp-pattern" name="pattern_id" required data-search-select="photos">
        <option value="">Choose a pattern…</option>
        @foreach ($patterns as $p)
            <option value="{{ $p->id }}" data-sub="{{ $p->code }}" @isset($photos[$p->id]) data-img="{{ $photos[$p->id] }}" @endisset data-revisions="{{ json_encode($p->revisions->map(fn ($r) => ['id' => $r->id, 'label' => $r->label().($r->is_current ? ' · current' : '')])->values()) }}" @selected($old('pattern_id') === $p->id)>{{ $p->name }}</option>
        @endforeach
    </select></div>
<div class="field field-full"><label for="lp-revision">Pattern revision</label>
    <select id="lp-revision" name="pattern_revision_id" data-search-select data-old="{{ $old('pattern_revision_id') }}"><option value="">Current revision</option></select>
    <div class="help" id="lp-revision-help">A pattern with no revision gets its first one.</div></div>
<div class="field field-full"><label for="lp-product-revision">Product revision it makes</label>
    <select id="lp-product-revision" name="product_revision_id" data-search-select>
        <option value="">Current revision</option>
        @foreach ($product->revisions->sortByDesc('created_at') as $r)
            <option value="{{ $r->id }}" @selected($old('product_revision_id') === $r->id) @disabled($r->status === 'OBSOLETE')>Rev {{ $r->revision }} · {{ $r->status }}</option>
        @endforeach
    </select>@if ($product->revisions->isEmpty())<div class="help">This product has no revision yet; it gets its first one.</div>@endif</div>
<script>
(function () {
    var pattern = document.getElementById('lp-pattern'), revision = document.getElementById('lp-revision'), help = document.getElementById('lp-revision-help');
    function fill() {
        var chosen = pattern.options[pattern.selectedIndex], list = chosen && chosen.dataset.revisions ? JSON.parse(chosen.dataset.revisions) : [], keep = revision.value || revision.dataset.old;
        revision.innerHTML = '';
        revision.add(new Option(list.length || !pattern.value ? 'Current revision' : 'Create its first revision (Rev 0)', ''));
        list.forEach(function (r) { revision.add(new Option(r.label, r.id)); });
        revision.value = keep || '';
        revision.dataset.old = '';
        help.hidden = !!list.length;
    }
    pattern.addEventListener('change', fill);
    fill();
})();
</script>
@else
<div class="field"><label for="lp-cavity">Cavity number</label><input id="lp-cavity" type="number" name="cavity_number" min="1" max="10000" step="1" value="{{ $old('cavity_number') }}"><div class="help">Blank takes the first free cavity.</div></div>
@endif
