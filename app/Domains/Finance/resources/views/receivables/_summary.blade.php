@php $m = fn ($v) => \App\Support\Formats::number($v, 2); @endphp
<div class="panel"><div class="panel-body" style="display:flex;gap:10px;align-items:center">
    <a class="btn {{ $tab === 'receipts' ? 'btn-primary' : '' }}" href="{{ route('receipts.index') }}">Receipts</a>
    <a class="btn {{ $tab === 'ageing' ? 'btn-primary' : '' }}" href="{{ route('receivables.index') }}">Ageing and statements</a>
</div><div class="panel-body" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px">
    <div><div class="muted small">Unapplied receipts</div><strong style="font-size:22px">{{ $m($summary['unapplied']) }}</strong><div class="muted small">{{ $summary['unapplied_count'] }} {{ \Illuminate\Support\Str::plural('receipt', $summary['unapplied_count']) }}</div></div>
    <div><div class="muted small">Overdue</div><strong style="font-size:22px;color:var(--danger)">{{ $m($summary['overdue']) }}</strong><div class="muted small">{{ $summary['overdue_count'] }} {{ \Illuminate\Support\Str::plural('invoice', $summary['overdue_count']) }}</div></div>
    <div><div class="muted small">Collected, {{ now()->format('F') }}</div><strong style="font-size:22px">{{ $m($summary['collected']) }}</strong></div>
</div></div>
