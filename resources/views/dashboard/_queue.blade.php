<div class="card" style="padding-bottom:6px">
    <div class="chead"><div><h2>Priority production queue</h2><p class="sub">Open orders, earliest required date first.</p></div><a class="btnx" href="{{ route('production-orders.index') }}">All orders</a></div>
    @if ($d['queue']->isNotEmpty())
        <div style="overflow-x:auto"><table class="q">
            <thead><tr><th>Order</th><th>Casting</th><th style="text-align:right">Qty</th><th>Due</th><th>Status</th></tr></thead>
            <tbody>
            @foreach ($d['queue'] as $o)
                @php $late = $o->required_date && $o->required_date->lt(today()); @endphp
                <tr>
                    <td class="mono"><a href="{{ route('production-orders.index', ['q' => $o->production_order_number]) }}">{{ $o->production_order_number }}</a></td>
                    <td><div class="cell">@if ($o->cover)<img class="thumb" src="{{ route('attachments.download', $o->cover) }}" alt="">@else<span class="thumb"></span>@endif<div><strong>{{ $o->product?->name ?? '—' }}</strong><div class="sub" style="font-size:12px;color:var(--dx-mute)">{{ $o->salesOrderLine?->salesOrder?->customer?->name ?? $o->product?->code }}</div></div></div></td>
                    <td class="mono" style="text-align:right">{{ \App\Support\Formats::number((float) $o->planned_qty) }}<div style="font-size:11px;color:var(--dx-mute)">{{ \App\Support\Formats::number((float) $o->completed_qty) }} done</div></td>
                    <td class="mono" style="white-space:nowrap;{{ $late ? 'color:#b91c1c;font-weight:650' : '' }}">{{ $o->required_date?->displayShortDate() ?? '—' }}</td>
                    <td><span class="pill {{ $o->status === 'IN_PROGRESS' ? 'blue' : ($late ? 'red' : '') }}">{{ str_replace('_', ' ', $o->status) }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div style="padding:10px 2px 6px;font-size:12.5px;color:var(--dx-mute)">Showing {{ $d['queue']->count() }} of {{ $d['queueTotal'] }} open orders.</div>
    @else
        <div class="empty">No open production orders.</div>
    @endif
</div>
