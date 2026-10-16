@php $sandStatus = app(\App\Services\Quality\Measurements::class)->sandStatus($run->sand_system, $run->started_at ?? $run->created_at ?? now()); @endphp
<div class="panel"><div class="panel-body">
    <strong>Sand condition:</strong>
    <span class="status {{ $sandStatus['state'] === 'OK' ? 'status-completed' : 'status-cancelled' }}">{{ $sandStatus['state'] === 'OK' ? 'TESTED' : ($sandStatus['state'] === 'FAIL' ? 'FAILED' : 'NO TEST') }}</span>
    {{ $sandStatus['text'] }} <a href="{{ route('sand-tests.index') }}">Sand lab</a>
</div></div>
