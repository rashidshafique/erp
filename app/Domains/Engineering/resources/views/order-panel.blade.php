{{-- What a production order needs of the items on its revision's bill of materials, against stock on hand. $order. --}}
@php
    $need = app(\App\Domains\Engineering\Services\BomExploder::class)->forOrder($order);
    $bases = \App\Domains\Engineering\Models\BomLine::BASES;
    $n = fn ($v) => \App\Support\Formats::number((float) $v, 3);
@endphp
@if ($need->isNotEmpty())
<section class="panel">
    <div class="panel-header"><h2>Materials needed</h2><span class="muted small">From the bill of materials of revision {{ $order->productRevision?->revision }}, for {{ \App\Support\Formats::number((float) $order->planned_qty, 0) }} {{ $order->uom }}. Metal is planned separately.</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Item</th><th>Per</th><th>Needed</th><th>In stock</th><th>Short</th></tr></thead>
        <tbody>
        @foreach ($need as $row)
            <tr>
                <td>{{ $row['line']->material?->code }} — {{ $row['line']->material?->name }}</td>
                <td>{{ $n($row['line']->quantity) }} {{ $row['line']->material?->base_uom }} {{ $bases[$row['line']->basis] }}</td>
                <td>{{ $row['required'] === null ? '—' : $n($row['required']) }}</td>
                <td>{{ $n($row['on_hand']) }}</td>
                <td>@if ($row['note'])<span class="muted small">{{ $row['note'] }}</span>@elseif ($row['short'] > 0)<strong style="color:var(--danger)">{{ $n($row['short']) }}</strong>@else — @endif</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</section>
@endif
