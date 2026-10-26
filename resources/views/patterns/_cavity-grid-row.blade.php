{{-- One cavity row of the cavity map grid. $n (cavity number, or __n__ in the template), $value(field), $error(field), $copied
     (code of the revision it was copied from), and $productRevisions from the page. --}}
@php
    $name = fn ($field) => "rows[{$n}][{$field}]";
    $role = $value('cavity_role') ?? 'WHOLE';
    $kg = fn ($v) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
    $messages = array_filter(array_map($error, ['cavity_mark', 'product_revision_id', 'cavity_number', 'component_label', 'pieces_per_product', 'casting_weight_kg', 'poured_weight_kg', 'notes', 'is_closed']));
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
    <td><input type="number" name="{{ $name('casting_weight_kg') }}" min="0" step="any" value="{{ $kg($value('casting_weight_kg')) }}" class="cg-num {{ $error('casting_weight_kg') ? 'bad' : '' }}" aria-label="Casting weight, cavity {{ $n }}"></td>
    <td class="cg-dup">
        {{-- Role, component, pieces, poured weight and notes are kept in these fields and edited in the details dialog. --}}
        <input type="hidden" name="{{ $name('cavity_role') }}" value="{{ $role }}">
        <input type="hidden" name="{{ $name('component_label') }}" value="{{ $value('component_label') }}">
        <input type="hidden" name="{{ $name('pieces_per_product') }}" value="{{ $value('pieces_per_product') ?? 1 }}">
        <input type="hidden" name="{{ $name('poured_weight_kg') }}" value="{{ $kg($value('poured_weight_kg')) }}">
        <input type="hidden" name="{{ $name('notes') }}" value="{{ $value('notes') }}">
        <input type="hidden" name="{{ $name('is_closed') }}" value="{{ filter_var($value('is_closed'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0 }}">
        <span class="cg-sum muted small" data-cavity-summary></span>
        <button type="button" class="cg-dup-btn {{ array_filter(array_map($error, ['cavity_role', 'component_label', 'pieces_per_product', 'poured_weight_kg', 'notes', 'is_closed'])) ? 'bad' : '' }}" data-cavity-details title="Details of this cavity: component, pieces per product, poured weight, notes, closed" aria-label="Details of cavity {{ $n }}"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></button>
        <button type="button" class="cg-dup-btn" data-cavity-duplicate title="Add a cavity like this one: same product, component, pieces, weights and notes (not the mark). The cavity count goes up by one." aria-label="Duplicate cavity {{ $n }} as a new cavity"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg></button>
        <button type="button" class="cg-dup-btn cg-del-btn" data-cavity-delete title="Remove this cavity. The cavities after it move up one number and the cavity count goes down by one." aria-label="Remove cavity {{ $n }}"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg></button>
    </td>
</tr>
@if ($messages)
    <tr class="cg-errors" data-cavity-errors="{{ $n }}"><td></td><td colspan="4">@foreach ($messages as $m)<div>{{ $m }}</div>@endforeach</td></tr>
@endif
