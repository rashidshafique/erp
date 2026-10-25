{{-- When each charge of the recipe is due, from the furnace's melting rate and the metal in one charge. $schedule from
     ChargeSchedule::for(). The schedule only says what is due; a charge is recorded by a person (the form below, or the banner). --}}
@if ($schedule['reason'])
    <p class="muted small">{{ $schedule['reason'] }}</p>
@elseif ($schedule['rounds']->isNotEmpty())
    <p class="muted small" style="margin-top:0">
        A charge is due every <strong>{{ \App\Support\Formats::duration($schedule['interval_minutes']) }}</strong>:
        {{ \App\Support\Formats::number($schedule['metal_per_round_kg'], 0) }} kg of metal per charge at the furnace's {{ $schedule['rate']['label'] }}.
        The next one counts from when the last was actually put in.
    </p>
    <div class="table-wrap"><table>
        <thead><tr><th>Charge</th><th>Due</th><th>State</th></tr></thead>
        <tbody>
        @php
            $doneCount = $schedule['rounds']->where('state', 'done')->count();
            $shown = $schedule['rounds']->filter(fn ($r) => $r['state'] !== 'done')->take(6);
            $later = $schedule['rounds']->filter(fn ($r) => $r['state'] !== 'done')->count() - $shown->count();
        @endphp
        @foreach ($shown as $r)
            <tr>
                <td>{{ $r['round'] === 0 ? 'Start-up (bed)' : 'Charge '.$r['round'] }}</td>
                <td>{{ $r['due_at']?->displayAt() ?? '—' }}</td>
                <td>
                    @if ($r['state'] === 'done')<span class="status status-completed">Charged {{ $r['recorded_at']->displayAt() }}</span>
                    @elseif ($r['state'] === 'due')<span class="status status-in-progress">Due{{ $r['minutes'] !== null && $r['minutes'] < 0 ? ', '.abs($r['minutes']).' min ago' : ' now' }}</span>
                    @elseif ($r['state'] === 'upcoming')<span class="status">in {{ \App\Support\Formats::duration($r['minutes']) }}</span>
                    @elseif ($r['state'] === 'projected')<span class="muted small">projected, counted from the charge before</span>
                    @else<span class="muted small">after the heat starts</span>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <p class="muted small">{{ $doneCount }} {{ $doneCount === 1 ? 'charge' : 'charges' }} recorded{{ $later > 0 ? ', '.$later.' more planned after these' : '' }}.</p>
@endif
