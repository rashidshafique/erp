{{-- Flask sets per size: free now, in use, wanted by planned moulds that hold their flasks to shakeout, and the shortfall.
     $load from FlaskLoad::bySize(); $title; $note (optional). --}}
@if ($load->isNotEmpty())
<div class="panel">
    <div class="panel-header"><h2>{{ $title }}</h2>@if ($load->sum('short') > 0)<span class="status status-cancelled">{{ $load->where('short', '>', 0)->count() }} {{ $load->where('short', '>', 0)->count() === 1 ? 'size' : 'sizes' }} short</span>@endif</div>
    <div class="table-wrap"><table>
        <thead><tr><th>Flask size</th><th>Free sets</th><th>In use</th><th>Wanted by planned moulds</th><th>Short</th></tr></thead>
        <tbody>
        @foreach ($load as $row)
            <tr>
                <td>{{ $row['size']->name }}@if ($row['size']->sets_owned !== null && app(\App\Services\Production\FlaskManager::class)->isCountOnly($row['size']))<div class="muted small">{{ $row['size']->sets_owned }} sets owned</div>@endif</td>
                <td>{{ $row['free'] }}</td>
                <td>{{ $row['in_use'] }}</td>
                <td>{{ $row['wanted'] ?: '—' }}</td>
                <td>@if ($row['short'] > 0)<strong style="color:var(--danger,#b42318)">{{ $row['short'] }}</strong>@else<span class="muted">—</span>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="panel-body"><p class="muted small" style="margin:0">{{ $note ?? 'Only moulds that keep their flasks until shakeout count; a mould stripped at pour gives its set back the same shift.' }}</p></div>
</div>
@endif
