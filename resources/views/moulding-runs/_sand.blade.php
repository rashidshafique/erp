@php $sandStatus = app(\App\Services\Quality\Measurements::class)->sandStatus($run->sand_system, $run->started_at ?? $run->created_at ?? now()); @endphp
<div class="panel"><div class="panel-body">
    <strong>Sand condition:</strong>
    <span class="status {{ $sandStatus['state'] === 'OK' ? 'status-completed' : 'status-cancelled' }}">{{ $sandStatus['state'] === 'OK' ? 'TESTED' : ($sandStatus['state'] === 'FAIL' ? 'FAILED' : 'NO TEST') }}</span>
    {{ $sandStatus['text'] }} <a href="{{ route('sand-tests.index') }}">Sand lab</a>
</div></div>
@php $linkedTests = \App\Models\MeasurementSet::with('measurements')->where('kind', 'SAND')->where(fn ($q) => $q->where('moulding_run_id', $run->id)->orWhere('pattern_id', $run->patternRevision?->pattern_id ?? '00000000-0000-0000-0000-000000000000'))->orderByDesc('taken_at')->limit(10)->get(); @endphp
<div class="panel">
    <div class="panel-header"><h2>Sand tests for this run and pattern</h2><a class="btn btn-primary" href="{{ route('sand-tests.index', ['moulding_run_id' => $run->id]) }}">Start sand test</a></div>
    <div class="table-wrap"><table><thead><tr><th>When</th><th>Linked to</th><th>Results</th><th>Result</th></tr></thead><tbody>
    @forelse ($linkedTests as $test)
        <tr><td>{{ $test->taken_at->displayShortDateTime() }}</td><td>{{ $test->moulding_run_id === $run->id ? 'This run' : 'Pattern' }}</td>
            <td>@foreach ($test->measurements as $m){{ $m->name }}: <strong>{{ rtrim(rtrim(number_format((float) $m->value, 4, '.', ''), '0'), '.') }}</strong> {{ $m->unit }}<br>@endforeach</td>
            <td><span class="status {{ $test->result === 'PASS' ? 'status-completed' : ($test->result === 'FAIL' ? 'status-cancelled' : '') }}">{{ $test->result }}</span></td></tr>
    @empty
        <tr><td colspan="4" class="muted">No sand test is linked to this run or its pattern yet.</td></tr>
    @endforelse
    </tbody></table></div>
</div>
