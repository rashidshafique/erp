{{-- Assets tab: the equipment register with status and meters. --}}
<div class="el-tools"><span class="el-note">{{ $equipment->count() }} {{ \Illuminate\Support\Str::plural('asset', $equipment->count()) }}</span><span style="display:flex;gap:8px"><button type="button" class="el-btn" data-quick="qd-meter">Add meter</button><button type="button" class="el-btn" data-quick="qd-equipment">New equipment</button></span></div>
<div class="el-table">
    @if ($equipment->isEmpty())
        <div class="el-note" style="padding:24px 12px">No equipment yet. Add the first asset to plan its maintenance.</div>
    @else
    <table>
        <thead><tr><th>Asset</th><th>Type</th><th>Location</th><th>Meters</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($equipment as $e)
            <tr>
                <td><strong>{{ $e->code }}</strong><div class="el-sub">{{ $e->name }}</div></td>
                <td>{{ $equipmentTypes[$e->equipment_type] ?? $e->equipment_type }}</td>
                <td>{{ $e->location_description ?: '—' }}</td>
                <td>@forelse ($e->meters as $m){{ $m->name }}: {{ $fmt($m->current_reading) }} {{ $m->shortUnit() }}<button type="button" class="el-code" style="margin-left:6px;color:var(--accent)" data-quick="qd-reading" data-act="{{ route('equipment-maintenance.meters.readings.store', $m) }}" data-title="Reading for {{ $e->code }} · {{ $m->name }}">Record</button><br>@empty — @endforelse</td>
                <td><span class="el-pill {{ $statusTone($e->status) }}">{{ $equipmentStatuses[$e->status] ?? $e->status }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>
