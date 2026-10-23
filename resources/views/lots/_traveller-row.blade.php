@php
    $tags = array_filter([$stage->is_quality_gate ? 'Quality gate' : null, $stage->knocks_out_mould ? 'Check cooling time first' : null, $stage->records_weight ? 'Record weight' : null]);
@endphp
<tr @class(['done' => $events])>
<td class="n">{{ $number }}</td>
<td><strong>{{ $stage->name }}</strong>@if($number === '+') <span class="tags">recorded, not on the process</span>@endif
@if($tags)<div class="tags">{{ implode(' · ', $tags) }}</div>@endif
@if($stage->description)<div class="what">{{ $stage->description }}</div>@endif
@if($stage->quality_notes)<div class="check"><strong>Check:</strong> {{ $stage->quality_notes }}</div>@endif</td>
@if($events)
<td>{{ $events->sum('quantity_in') }}</td><td>{{ $events->sum('quantity_accepted') }}</td><td>{{ $events->sum('quantity_rejected') }}</td>
<td>{{ $events->pluck('rejection_reason')->filter()->unique()->implode(', ') }}</td><td>{{ $events->pluck('operator_name')->filter()->unique()->implode(', ') }}</td>
<td>{{ $events->last()->created_at?->displayShortDate() }}</td><td></td>
@else
<td></td><td></td><td></td><td></td><td></td><td></td><td></td>
@endif
</tr>
