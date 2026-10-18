{{-- Change history of a pattern: the pattern, its revisions, cavity map rows and files, newest first. $pattern. --}}
@php
    $history = app(\App\Services\Patterns\PatternHistory::class)->for($pattern);
    $show = fn ($v) => $v === null || $v === '' ? '—' : (is_bool($v) ? ($v ? 'yes' : 'no') : (is_scalar($v) ? \Illuminate\Support\Str::limit((string) $v, 80) : json_encode($v)));
    $verb = ['CREATED' => 'Added', 'UPDATED' => 'Changed', 'DELETED' => 'Removed'];
@endphp
<style>
    .phist td { vertical-align: top; font-size: 12.5px; }
    .phist .ch { display: block; }
    .phist .ch .f { font-weight: 600; }
    .phist .ch .o { color: var(--muted); text-decoration: line-through; }
    .phist .why { color: var(--muted); font-style: italic; margin-top: 2px; }
</style>
<div class="panel" id="history">
    <div class="panel-header"><h2>Change history</h2><span class="muted small">The pattern, its revisions, cavity map and files. Every save is kept.</span></div>
    @if ($history->isEmpty())
        <div class="empty-state">No changes recorded yet.</div>
    @else
        <div class="table-wrap"><table class="phist">
            <thead><tr><th>When</th><th>Who</th><th>What</th><th>Change</th></tr></thead>
            <tbody>
                @foreach ($history as $h)
                    <tr>
                        <td style="white-space:nowrap">{{ $h['at']->setTimezone(config('app.timezone'))->displayDateTime() }}</td>
                        <td>{{ $h['who'] ?? 'System' }}</td>
                        <td><strong>{{ $verb[$h['action']] ?? $h['action'] }}</strong> {{ $h['what'] }}</td>
                        <td>
                            @if ($h['action'] === 'UPDATED')
                                @foreach ($h['changes'] as $field => $c)<span class="ch"><span class="f">{{ $field }}</span>: <span class="o">{{ $show($c['old'] ?? null) }}</span> → {{ $show($c['new'] ?? null) }}</span>@endforeach
                            @elseif ($h['action'] === 'CREATED')
                                @foreach (collect($h['changes'])->filter(fn ($c) => ($c['new'] ?? null) !== null && ($c['new'] ?? null) !== '')->take(6) as $field => $c)<span class="ch"><span class="f">{{ $field }}</span>: {{ $show($c['new']) }}</span>@endforeach
                            @else
                                <span class="muted">removed</span>
                            @endif
                            @if ($h['reason'])<div class="why">Reason: {{ $h['reason'] }}</div>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    @endif
</div>
