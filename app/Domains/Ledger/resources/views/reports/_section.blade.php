{{-- One top-level account of a statement: its heading, the accounts under it and its total. Needs $section, $m, $cols (1 or 2) and $link ([from, to] or [as_at]). --}}
@if ($section['rows'] !== [] || $section['total'][0] != 0.0 || ($section['total'][1] ?? 0) != 0.0)
@if ($section['rows'] === [])
<tr><td style="padding-left:12px"><a href="{{ route('ledger.accounts.show', [$section['account']] + $link) }}">{{ $section['account']->name }}</a></td><td class="num">{{ $m($section['total'][0]) }}</td>@if ($cols === 2)<td class="num muted">{{ $m($section['total'][1]) }}</td>@endif</tr>
@else
<tr><td colspan="{{ $cols + 1 }}" style="padding-left:12px"><strong>{{ $section['account']->name }}</strong></td></tr>
@foreach ($section['rows'] as $row)
<tr><td style="padding-left:{{ 12 + $row['account']->depth * 22 }}px">@if ($row['account']->is_group)<span class="muted">{{ $row['account']->name }}</span>@else<a href="{{ route('ledger.accounts.show', [$row['account']] + $link) }}">{{ $row['account']->name }}</a>@endif</td>
<td @class(['num', 'muted' => $row['account']->is_group])>{{ $m($row['amount'][0]) }}</td>@if ($cols === 2)<td class="num muted">{{ $m($row['amount'][1]) }}</td>@endif</tr>
@endforeach
<tr><td style="padding-left:12px"><strong>Total {{ mb_strtolower($section['account']->name) }}</strong></td><td class="num"><strong>{{ $m($section['total'][0]) }}</strong></td>@if ($cols === 2)<td class="num"><strong>{{ $m($section['total'][1]) }}</strong></td>@endif</tr>
@endif
@endif
