{{-- What the charge is expected to melt to, worked out from the chemistry on its materials, against the iron grade and the latest reading.
     Needs $heat and $plannedChargeItems (the recipe's items with planned_calculated_qty_kg). Shows nothing until some material has chemistry. --}}
@php
    $plannedKg = $plannedChargeItems->groupBy('material_id')->map(fn ($items) => (float) $items->sum('planned_calculated_qty_kg'))->all();
    $forecast = app(\App\Services\Quality\ChargePredictor::class)->forHeat($heat, $plannedKg);
    $basis = $forecast['charged'] ?? $forecast['plan'];
    $anyChemistry = $basis && collect($basis['elements'])->contains(fn ($e) => $e['nominal'] !== null);
    $verdicts = [
        'CONFORMS' => ['On spec', 'var(--success)'], 'AT_RISK' => ['Warning: close to a limit', 'var(--warning)'], 'DEVIATES' => ['Warning: predicted off spec', 'var(--danger)'],
        'PARTIAL' => ['On spec where known', 'var(--muted)'], 'INSUFFICIENT' => ['Not enough chemistry on file', 'var(--muted)'], 'NO_GRADE' => ['No iron grade', 'var(--muted)'],
    ];
    $statusText = ['OK' => 'within', 'AT_RISK' => 'close to a limit', 'OUT' => 'outside', 'NO_DATA' => 'no data', 'NOT_LIMITED' => ''];
    $fmt = fn ($v, $d = 3) => $v === null ? '—' : \App\Support\Formats::number((float) $v, $d);
@endphp
@if ($anyChemistry)
<div class="panel">
    <div class="panel-header">
        <h2>Predicted composition</h2>
        <strong style="color:{{ $verdicts[$basis['verdict']][1] }}">{{ $verdicts[$basis['verdict']][0] }}@if ($forecast['charged']) <span class="muted small">· from what is charged</span>@else <span class="muted small">· from the recipe</span>@endif</strong>
    </div>
    <div class="panel-body">
        <p class="muted small">A warning only; nothing is held or refused because of it. Worked out from the chemistry on each material and its recovery, over the {{ $fmt($basis['covered_kg'], 0) }} kg of metal that has chemistry ({{ round($basis['coverage'] * 100) }} % of {{ $fmt($basis['metal_kg'], 0) }} kg).@foreach ($basis['notes'] as $note) {{ $note }}@endforeach</p>
        <div class="table-wrap"><table>
            <thead><tr><th>Element</th><th>Grade window %</th><th>Recipe %</th><th>Charged % (range)</th><th>Predicted</th><th>Measured %{{ $forecast['measured_label'] ? ' '.$forecast['measured_label'] : '' }}</th></tr></thead>
            <tbody>
            @foreach ([...$basis['elements'], ...($basis['ce'] ? ['ce' => $basis['ce']] : [])] as $key => $element)
                @continue($element['nominal'] === null && $element['status'] === 'NOT_LIMITED')
                @php
                    $planned = $forecast['plan']['elements'][$key] ?? ($key === 'ce' ? $forecast['plan']['ce'] ?? null : null);
                    $charged = $forecast['charged']['elements'][$key] ?? ($key === 'ce' ? $forecast['charged']['ce'] ?? null : null);
                    $color = ['OK' => 'var(--success)', 'AT_RISK' => 'var(--warning)', 'OUT' => 'var(--danger)'][$element['status']] ?? 'inherit';
                    $deviation = $forecast['deviations'][$key] ?? null;
                @endphp
                <tr>
                    <td><strong>{{ $element['label'] }}</strong></td>
                    <td>@if ($element['grade_min'] !== null || $element['grade_max'] !== null){{ $fmt($element['grade_min'], 2) }} – {{ $fmt($element['grade_max'], 2) }}@else<span class="muted">no limit</span>@endif</td>
                    <td>{{ $fmt($planned['nominal'] ?? null) }}</td>
                    <td>{{ $fmt($charged['nominal'] ?? null) }}@if ($charged && $charged['nominal'] !== null && $charged['low'] != $charged['high']) <span class="muted small">({{ $fmt($charged['low']) }} – {{ $fmt($charged['high']) }})</span>@endif</td>
                    <td style="color:{{ $color }}">@if ($element['status'] === 'OUT'){{ $element['gap'] < 0 ? 'short by '.$fmt(abs($element['gap'])) : 'over by '.$fmt($element['gap']) }} %@else{{ $statusText[$element['status']] }}@endif</td>
                    <td>@if ($key !== 'ce' && ($forecast['measured'][$key] ?? null) !== null){{ $fmt($forecast['measured'][$key]) }}@if ($deviation) <strong style="color:var(--danger)">· {{ $deviation }} the prediction</strong>@endif @else<span class="muted">—</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @if ($forecast['deviations'])
            <p class="muted small">The melt came out {{ collect($forecast['deviations'])->map(fn ($d, $k) => \App\Models\IronGrade::ELEMENTS[$k].' '.$d)->implode(', ') }} the range the charge predicted. Check the charge weights, the lots used and the sample.</p>
        @endif
    </div>
</div>
@endif
