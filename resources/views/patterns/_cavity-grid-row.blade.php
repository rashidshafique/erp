{{-- One cavity row of the cavity map grid. $n (cavity number, or __n__ in the template), $value(field), $error(field), $copied
     (code of the revision it was copied from), and $productRevisions from the page. --}}
@php
    $name = fn ($field) => "rows[{$n}][{$field}]";
    $role = $value('cavity_role') ?? 'WHOLE';
    $kg = fn ($v) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
    $messages = array_filter(array_map($error, ['cavity_mark', 'product_revision_id', 'cavity_number', 'component_label', 'pieces_per_product', 'casting_weight_kg', 'poured_weight_kg', 'notes']));
@endphp
<tr data-cavity-row="{{ $n }}" class="{{ $messages ? 'has-error' : '' }}">
    <td class="cg-n"><strong>{{ $n }}</strong>@if ($copied)<div class="muted small" title="Carried over from {{ $copied }}">from {{ \Illuminate\Support\Str::afterLast($copied, '-') }}</div>@endif</td>
    <td><input name="{{ $name('cavity_mark') }}" value="{{ $value('cavity_mark') }}" maxlength="20" placeholder="e.g. A{{ is_numeric($n) ? $n : '' }}" class="cg-mark {{ $error('cavity_mark') ? 'bad' : '' }}" aria-label="Mark on cavity {{ $n }}" title="Number or code engraved or embossed on the cavity"></td>
    @php($photos = \App\Models\Attachment::coverUrlsFor('product', $productRevisions->pluck('product_id')->merge($bareProducts->pluck('id'))->all()))
    <td><select name="{{ $name('product_revision_id') }}" data-search-select="photos" class="cg-product {{ $error('product_revision_id') ? 'bad' : '' }}" aria-label="Product of cavity {{ $n }}">
        <option value="">— not used —</option>
        @foreach ($productRevisions as $pr)<option value="{{ $pr->id }}" data-sub="{{ $pr->product?->code }} rev {{ $pr->revision }}" @isset($photos[$pr->product_id]) data-img="{{ $photos[$pr->product_id] }}" @endisset @selected($value('product_revision_id') === $pr->id)>{{ $pr->product?->name ?: 'Product unavailable' }}@isset($manyRevisions[$pr->product_id]) · rev {{ $pr->revision }}@endisset</option>@endforeach
        @foreach ($bareProducts as $bp)<option value="product:{{ $bp->id }}" data-sub="{{ $bp->code }} · no revision yet" @isset($photos[$bp->id]) data-img="{{ $photos[$bp->id] }}" @endisset @selected($value('product_revision_id') === 'product:'.$bp->id)>{{ $bp->name }}</option>@endforeach
    </select></td>
    <td><select name="{{ $name('cavity_role') }}" aria-label="What cavity {{ $n }} makes"><option value="WHOLE" @selected($role === 'WHOLE')>Whole casting</option><option value="PART" @selected($role === 'PART')>One component</option></select></td>
    <td><input name="{{ $name('component_label') }}" value="{{ $value('component_label') }}" maxlength="100" placeholder="if a component" class="{{ $error('component_label') ? 'bad' : '' }}" aria-label="Component of cavity {{ $n }}"></td>
    <td><input type="number" name="{{ $name('pieces_per_product') }}" min="1" value="{{ $value('pieces_per_product') ?? 1 }}" class="cg-num" aria-label="Pieces per product, cavity {{ $n }}"></td>
    <td><input type="number" name="{{ $name('casting_weight_kg') }}" min="0.0001" step="0.0001" value="{{ $kg($value('casting_weight_kg')) }}" class="cg-num {{ $error('casting_weight_kg') ? 'bad' : '' }}" aria-label="Casting weight, cavity {{ $n }}"></td>
    <td><input type="number" name="{{ $name('poured_weight_kg') }}" min="0.0001" step="0.0001" value="{{ $kg($value('poured_weight_kg')) }}" class="cg-num {{ $error('poured_weight_kg') ? 'bad' : '' }}" aria-label="Poured weight, cavity {{ $n }}"></td>
    <td><input name="{{ $name('notes') }}" value="{{ $value('notes') }}" maxlength="2000" aria-label="Notes, cavity {{ $n }}"></td>
</tr>
@if ($messages)
    <tr class="cg-errors" data-cavity-errors="{{ $n }}"><td></td><td colspan="8">@foreach ($messages as $m)<div>{{ $m }}</div>@endforeach</td></tr>
@endif
