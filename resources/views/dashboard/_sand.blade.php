<div class="card">
    <div class="chead"><div><h2>Sand lab snapshot</h2><p class="sub">Latest test per sand system.</p></div><a class="btnx" href="{{ route('sand-tests.index') }}">Full log</a></div>
    @forelse ($d['sand'] as $s)
        <div style="margin-bottom:12px"><div style="display:flex;justify-content:space-between;font-size:12px;color:var(--dx-mute);margin-bottom:6px"><strong style="color:var(--dx-ink)">{{ $s['system'] }}</strong><span class="mono">{{ $s['set']->taken_at?->displayShortDateTime() }} · <span class="pill {{ $s['set']->result === 'PASS' ? 'green' : 'red' }}">{{ $s['set']->result }}</span></span></div>
        <div class="sand">@foreach ($s['set']->measurements->sortBy('sort_order')->take(4) as $m)<div class="t"><div class="l">{{ $m->name }}</div><div class="v mono" style="{{ $m->passed === false ? 'color:#b91c1c' : '' }}">{{ $m->value !== null ? rtrim(rtrim(\App\Support\Formats::number((float) $m->value, 2), '0'), '.') : ($m->text_value ?? '—') }} <span style="font-size:11px;font-weight:500;color:var(--dx-mute)">{{ $m->unit }}</span></div></div>@endforeach</div></div>
    @empty
        <div class="empty">No sand test has been recorded yet.</div>
    @endforelse
</div>
