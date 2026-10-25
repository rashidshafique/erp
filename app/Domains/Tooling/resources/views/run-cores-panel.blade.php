{{-- The cores this run's moulds need, what has been issued and what is on hand. Slot moulding-run.show. --}}
@php $needs = app(\App\Domains\Tooling\Services\CoreLotService::class)->needsForRun($run); @endphp
@if ($needs)
<div class="panel">
    <div class="panel-header"><h2>Cores</h2><span class="muted small">For {{ max((int) $run->planned_mould_qty, (int) $run->moulds_made_qty) }} moulds · <a href="{{ route('core-lots.index') }}">Core stock</a></span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Core</th><th>Per mould</th><th>Needed</th><th>Issued</th><th>On hand</th><th></th></tr></thead>
        <tbody>
        @foreach ($needs as $n)
            @php $open = max(0, $n['needed'] - $n['issued']); @endphp
            <tr>
                <td><x-item :name="$n['core']->name" :code="$n['core']->code" :href="route('cores.show', $n['core'])" /></td>
                <td>{{ $n['per_mould'] }}</td><td>{{ $n['needed'] }}</td><td>{{ $n['issued'] }}</td>
                <td>{{ $n['on_hand'] }}@if ($n['short'] > 0) <span class="status status-on-hold" title="Cores still to be made">{{ $n['short'] }} short</span>@endif</td>
                <td style="white-space:nowrap">
                    @if ($open > 0 && $n['on_hand'] > 0)
                        <form method="POST" action="{{ route('core-lots.issue-to-run', $run) }}" style="display:flex;gap:6px">@csrf<input type="hidden" name="core_definition_id" value="{{ $n['core']->id }}"><input type="number" name="quantity" min="1" max="{{ min($open, $n['on_hand']) }}" value="{{ min($open, $n['on_hand']) }}" style="width:80px" aria-label="Cores to issue"><button class="btn btn-small" type="submit">Issue</button></form>
                    @endif
                    @if ($n['short'] > 0)<a class="small" href="{{ route('core-lots.create', ['core' => $n['core']->id]) }}">Cores made →</a>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endif
