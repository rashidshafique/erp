{{-- The cores this run's moulds need and whether stock covers them (design 29j). It warns and never blocks: cores can be made
     during the shift. With core stock off it only lists the cores needed. Slot moulding-run.show. --}}
@php
    $needs = app(\App\Domains\Tooling\Services\CoreLotService::class)->needsForRun($run);
    $stock = \App\Domains\Tooling\Support\CoreRules::stockOn();
    $short = collect($needs)->where('short', '>', 0);
@endphp
@if ($needs)
<div class="panel">
    <div class="panel-header"><h2>Cores</h2><span class="muted small">{{ max((int) $run->planned_mould_qty, (int) $run->moulds_made_qty) }} moulds planned @if ($stock) · <a href="{{ route('core-lots.index') }}">Core stock</a>@endif</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Core</th><th class="r">Needed</th>@if ($stock)<th class="r">On hand</th><th></th><th></th>@endif</tr></thead>
        <tbody>
        @foreach ($needs as $n)
            @php $open = max(0, $n['needed'] - $n['issued']); @endphp
            <tr>
                <td><x-item :name="$n['core']->name" :code="$n['core']->code" :href="route('cores.show', $n['core'])" /></td>
                <td class="r">{{ $n['needed'] }}@if ($n['issued'] > 0)<div class="muted small">{{ $n['issued'] }} issued</div>@endif</td>
                @if ($stock)
                    <td class="r">{{ $n['on_hand'] }}</td>
                    <td>@if ($n['short'] > 0)<span class="status status-on-hold">! Short {{ $n['short'] }}</span>@else<span style="color:#1e7a46" aria-label="Covered">✓</span>@endif</td>
                    <td class="r" style="white-space:nowrap">
                        @if ($open > 0 && $n['on_hand'] > 0)
                            <form method="POST" action="{{ route('core-lots.issue-to-run', $run) }}" style="display:inline-flex;gap:6px">@csrf<input type="hidden" name="core_definition_id" value="{{ $n['core']->id }}"><input type="number" name="quantity" min="1" max="{{ min($open, $n['on_hand']) }}" value="{{ min($open, $n['on_hand']) }}" style="width:76px" aria-label="Cores to issue"><button class="btn btn-small" type="submit">Issue</button></form>
                        @endif
                    </td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @if ($stock && $short->isNotEmpty())
        <div class="panel-body" style="border-top:1px solid var(--border);background:#fffaf0;color:#5d3d05">
            Short of {{ $short->map(fn ($n) => $n['core']->name)->join(', ', ' and ') }}. The run can still start — cores can be made during the shift.
            <a href="{{ route('core-lots.create', ['core' => $short->first()['core']->id, 'run' => $run->id]) }}">Record cores made</a>
        </div>
    @endif
</div>
@endif
