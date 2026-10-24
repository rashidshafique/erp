@if ($items->isEmpty())
    <p class="muted" style="margin:8px 0">The default plan has no rows yet. Add them under <a href="{{ route('settings.process') }}">Settings → Our process</a>.</p>
@else
    <div class="table-wrap"><table>
        <thead><tr><th>Step</th><th>What is controlled</th><th>Limits</th><th>How measured</th><th>How often</th><th>If out of limits</th></tr></thead>
        <tbody>@foreach ($items as $item)
            <tr><td>{{ $item->stageDefinition?->name ?? 'Any' }}</td><td>{{ $item->parameter_name }}</td>
                <td>{{ $item->minimum_value !== null ? rtrim(rtrim((string) $item->minimum_value, '0'), '.') : '' }}{{ $item->target_value !== null ? ' / '.rtrim(rtrim((string) $item->target_value, '0'), '.') : '' }}{{ $item->maximum_value !== null ? ' / '.rtrim(rtrim((string) $item->maximum_value, '0'), '.') : '' }} {{ $item->unit }}</td>
                <td>{{ $item->control_method }}</td><td>{{ $item->monitoring_frequency }}</td><td>{{ $item->reaction_plan }}</td></tr>
        @endforeach</tbody>
    </table></div>
@endif
