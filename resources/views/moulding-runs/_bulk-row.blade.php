{{-- One row of the bulk moulding-run grid. $key names the row's inputs (rows[$key][…]); $row holds its values; $run is the
     saved run or null for a new row; $open shows the inputs (always for a new row); $rowErrors are this row's messages by field.
     Lists: $patterns (with revisions), $patternPhotos, $stations, $stationCrew, $orders, $defects, $shifts.
     Team and sand are not chosen here: they come from the station, and a saved run's are changed on its own page. --}}
@php
    $isNew = ! $run;
    $name = fn (string $field) => "rows[{$key}][{$field}]";
    $val = fn (string $field) => $row[$field] ?? null;
    $bad = fn (string $field) => isset($rowErrors[$field]) ? 'bad' : '';
    $revision = $val('pattern_revision_id') ? $patterns->flatMap->revisions->firstWhere('id', $val('pattern_revision_id')) : null;
    $pattern = $val('pattern_id') ? $patterns->firstWhere('id', $val('pattern_id')) : null;
    $station = $stations->firstWhere('id', $val('moulding_station_id'));
    $order = $orders->firstWhere('id', $val('production_order_id')) ?? $run?->productionOrder;
    $defect = $defects->firstWhere('id', $val('quality_defect_id')) ?? $run?->defect;
    $mapped = $revision ? $revision->cavityMap->pluck('cavity_number')->implode(', ') : '';
@endphp
<tr data-editable-row data-bulk-row="{{ $key }}" class="{{ $open ? 'is-editing' : '' }} {{ $rowErrors ? 'has-error' : '' }}">
    <td class="mono small">@if ($run)<a href="{{ route('moulding-runs.show', $run) }}">{{ $run->run_number }}</a><input type="hidden" name="{{ $name('id') }}" value="{{ $run->id }}">@else<span class="muted">new</span>@endif</td>
    <td><span class="ro">{{ $val('run_date') ? \Illuminate\Support\Carbon::parse($val('run_date'))->displayShortDate() : '—' }}</span><span class="ed"><input type="date" name="{{ $name('run_date') }}" value="{{ $val('run_date') }}" data-fill="run_date" class="w-date {{ $bad('run_date') }}"></span></td>
    <td><span class="ro">{{ $val('shift') ?: '—' }}</span><span class="ed"><select name="{{ $name('shift') }}" data-fill="shift" class="w-shift"><option value="">—</option>@foreach ($shifts as $s)<option @selected($val('shift') === $s)>{{ $s }}</option>@endforeach @if ($val('shift') && ! in_array($val('shift'), $shifts, true))<option selected>{{ $val('shift') }}</option>@endif</select></span></td>
    <td><span class="ro">{{ $station?->code ?? '—' }}</span><span class="ed"><select name="{{ $name('moulding_station_id') }}" data-fill="moulding_station_id" @if ($run) data-saved="{{ $run->moulding_station_id }}" @endif class="w-code {{ $bad('moulding_station_id') }}"><option value="">—</option>@foreach ($stations as $s)<option value="{{ $s->id }}" @selected($val('moulding_station_id') === $s->id)>{{ $s->code }}</option>@endforeach</select></span>
        <div class="crew muted" data-crew title="Team and sand" @if ($run) data-saved-crew="{{ trim(($run->team?->code ?? $run->team_name ?? 'no team').' · '.($run->sand_system ?: 'no sand')) }}" @endif>{{ $run && $val('moulding_station_id') === $run->moulding_station_id ? trim(($run->team?->code ?? $run->team_name ?? 'no team').' · '.($run->sand_system ?: 'no sand')) : ($stationCrew[$val('moulding_station_id')] ?? '') }}</div></td>
    <td><span class="ro">@if ($pattern)<strong>{{ $pattern->name }}</strong><div class="muted small mono">{{ $pattern->code }}</div>@else — @endif</span><span class="ed"><select name="{{ $name('pattern_id') }}" data-fill="pattern_id" data-pattern data-search-select="photos" aria-label="Pattern" class="w-pat {{ $bad('pattern_id') }}"><option value="">—</option>@foreach ($patterns as $p)<option value="{{ $p->id }}" data-sub="{{ $p->code }}" @isset($patternPhotos[$p->id]) data-img="{{ $patternPhotos[$p->id] }}" @endisset @selected($val('pattern_id') === $p->id)>{{ $p->name }}</option>@endforeach</select></span></td>
    <td><span class="ro">{{ $revision?->code ?? '—' }}@if ($revision?->isSuperseded()) <span class="status status-on-hold">superseded</span>@endif</span>
        <span class="ed"><select name="{{ $name('pattern_revision_id') }}" data-fill="pattern_revision_id" data-revision @disabled(! $val('pattern_id')) title="{{ $val('pattern_id') ? 'Pattern revision' : 'Choose the pattern first' }}" class="w-rev {{ $bad('pattern_revision_id') }}"><option value="">—</option>@foreach ($patterns as $p)<optgroup label="{{ $p->code }}" data-pattern-id="{{ $p->id }}">@foreach ($p->revisions as $r)<option value="{{ $r->id }}" data-superseded="{{ $r->isSuperseded() ? 1 : 0 }}" @selected($val('pattern_revision_id') === $r->id)>{{ $r->label() }}</option>@endforeach</optgroup>@endforeach</select>
        <span class="warn" @unless ($revision?->isSuperseded()) hidden @endunless>Superseded revision: saved with a warning.</span></span></td>
    <td><span class="ro">{{ $val('cavity_numbers') ?: '—' }}</span><span class="ed"><input name="{{ $name('cavity_numbers') }}" value="{{ $val('cavity_numbers') }}" placeholder="{{ $mapped ?: 'all' }}" title="{{ $mapped ? 'Mapped cavities: '.$mapped : 'No cavity map' }}" data-cavities class="w-cav {{ $bad('cavity_numbers') }}"></span></td>
    <td><span class="ro">{{ $order?->production_order_number ?? '—' }}</span><span class="ed"><select name="{{ $name('production_order_id') }}" class="w-order {{ $bad('production_order_id') }}"><option value="">Automatic</option>@foreach ($orders as $o)<option value="{{ $o->id }}" @selected($val('production_order_id') === $o->id)>{{ $o->production_order_number }} · {{ $o->product?->code }}</option>@endforeach @if ($order && ! $orders->contains('id', $order->id))<option value="{{ $order->id }}" selected>{{ $order->production_order_number }}</option>@endif</select></span></td>
    <td><span class="ro mono">{{ $val('moulds_made_qty') ?? '—' }}</span><span class="ed"><input type="number" min="0" name="{{ $name('moulds_made_qty') }}" value="{{ $val('moulds_made_qty') }}" data-made class="w-num {{ $bad('moulds_made_qty') }}"></span></td>
    <td><span class="ro mono">{{ $val('damaged_mould_qty') ?? 0 }}</span><span class="ed"><input type="number" min="0" name="{{ $name('damaged_mould_qty') }}" value="{{ $val('damaged_mould_qty') }}" data-rejected class="w-num {{ $bad('damaged_mould_qty') }}"></span></td>
    <td><span class="ro">{{ $defect?->code ?? '—' }}</span><span class="ed"><select name="{{ $name('quality_defect_id') }}" class="w-code {{ $bad('quality_defect_id') }}"><option value="">—</option>@foreach ($defects as $d)<option value="{{ $d->id }}" title="{{ $d->name }}" @selected($val('quality_defect_id') === $d->id)>{{ $d->code }}</option>@endforeach</select></span></td>
    <td><span class="ro clip" title="{{ $val('notes') }}">{{ $val('notes') ?: '—' }}</span><span class="ed"><input name="{{ $name('notes') }}" value="{{ $val('notes') }}" maxlength="2000" class="w-notes"></span></td>
    <td><span class="ed"><label class="small" title="Save even though a run with the same date, shift, station, pattern and revision exists"><input type="checkbox" name="{{ $name('confirm_duplicate') }}" value="1" @checked(! empty($row['confirm_duplicate']))> anyway</label></span></td>
    <td class="tools">
        @if ($run)<span class="ro ro-act">@include('partials.icon-button', ['type' => 'edit', 'name' => $run->run_number, 'buttonType' => 'button', 'data' => ['row-edit' => true]])@include('partials.icon-button', ['type' => 'duplicate', 'name' => $run->run_number, 'buttonType' => 'button', 'data' => ['row-duplicate' => true]])</span>
            <span class="ed ed-act"><button type="button" class="btn btn-small" data-row-cancel>Cancel</button></span>
        @else
            @include('partials.icon-button', ['type' => 'duplicate', 'name' => 'this row', 'buttonType' => 'button', 'data' => ['row-duplicate' => true]])@include('partials.icon-button', ['type' => 'delete', 'name' => 'this row', 'buttonType' => 'button', 'data' => ['row-delete' => true]])
        @endif
    </td>
</tr>
@if ($rowErrors)
    <tr class="row-errors" data-errors-for="{{ $key }}"><td></td><td colspan="13">@foreach ($rowErrors as $message)<div>{{ $message }}</div>@endforeach</td></tr>
@endif
