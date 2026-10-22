{{-- One row of a floor entry grid. $n (row index, '__n__' in the template), $row (old input), plus the grid's lists. --}}
@php
    $err = fn ($field) => $mine && is_numeric($n) && $errors->has("rows.{$n}.{$field}");
    $chosen = $patterns->first(fn ($c) => $c['pattern']->id === ($row['pattern_id'] ?? null));
    $chosenOrder = $chosen ? ($pouring ? $chosen['pour_order'] : $chosen['make_order']) : null;
@endphp
<tr>
    <td class="fg-n">{{ is_numeric($n) ? $n + 1 : '__num__' }}</td>
    <td><select name="rows[{{ $n }}][pattern_id]" class="fg-pattern" data-fg-pattern data-search-select="photos" aria-label="Pattern" @if ($err('pattern_id')) aria-invalid="true" @endif>
        <option value="">—</option>
        @foreach ($patterns as $c)
            @php $o = $pouring ? $c['pour_order'] : $c['make_order']; @endphp
            <option value="{{ $c['pattern']->id }}" data-sub="{{ $c['pattern']->code }}" data-station="{{ $c['station_id'] }}" data-ppm="{{ $pouring ? $c['pour_ppm'] : $c['make_ppm'] }}" data-order="{{ $autoLabel($o) }}" @isset($patternPhotos[$c['pattern']->id]) data-img="{{ $patternPhotos[$c['pattern']->id] }}" @endisset @selected(($row['pattern_id'] ?? null) === $c['pattern']->id)>{{ $c['pattern']->name }}</option>
        @endforeach
    </select></td>
    <td><input type="number" name="rows[{{ $n }}][moulds]" min="1" max="100000" class="fg-num" value="{{ $row['moulds'] ?? '' }}" aria-label="Moulds" @if ($err('moulds')) aria-invalid="true" @endif></td>
    <td><input type="number" name="rows[{{ $n }}][pieces_per_mould]" min="1" max="1000" class="fg-num" value="{{ $row['pieces_per_mould'] ?? 1 }}" data-fg-ppm aria-label="Pieces per mould"></td>
    <td><select name="rows[{{ $n }}][production_order_id]" class="fg-order" aria-label="Order" @if ($err('production_order_id')) aria-invalid="true" @endif>
        <option value="" data-fg-auto>{{ $chosen ? $autoLabel($chosenOrder) : 'Automatic' }}</option>
        @foreach ($orderChoices as $o)<option value="{{ $o['order']->id }}" @selected(($row['production_order_id'] ?? null) === $o['order']->id)>{{ $label($o) }}</option>@endforeach
    </select></td>
    <td><select name="rows[{{ $n }}][moulding_station_id]" class="fg-station" data-fg-station aria-label="Station">
        <option value="">—</option>
        @foreach ($stations as $s)<option value="{{ $s->id }}" @selected(($row['moulding_station_id'] ?? ($chosen['station_id'] ?? null)) === $s->id)>{{ $s->code }} — {{ $s->name }}</option>@endforeach
    </select></td>
    <td><input type="date" name="rows[{{ $n }}][entry_date]" value="{{ $row['entry_date'] ?? $today }}" max="{{ $today }}" data-fg-row-date aria-label="Date" @if ($err('entry_date')) aria-invalid="true" @endif></td>
</tr>
