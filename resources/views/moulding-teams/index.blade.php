@extends('layouts.app')
@section('title', 'Teams and stations')
@section('content')
<div class="page-heading">
    <div><h1>Moulding teams and stations</h1><p>Records are read-only until you choose Edit. Team and station setup is maintained in dialogs; the figures are the last 30 days.</p></div>
    <div class="actions"><button type="button" class="btn" data-quick="qd-team">+ Add team</button><button type="button" class="btn btn-primary" data-quick="qd-station">+ Add station</button></div>
</div>

@include('partials.quick-dialog', ['id' => 'qd-team', 'title' => 'Add a team', 'action' => route('moulding-teams.teams.store'), 'submit' => 'Add team', 'fields' => 'moulding-teams._quick-team'])
@include('partials.quick-dialog', ['id' => 'qd-station', 'title' => 'Add a station', 'action' => route('moulding-teams.stations.store'), 'submit' => 'Add station', 'fields' => 'moulding-teams._quick-station'])
@include('partials.quick-dialog', ['id' => 'qd-sand', 'title' => 'Add a sand system', 'action' => route('sand-systems.store'), 'submit' => 'Add sand system', 'fields' => 'sand-systems._quick-fields'])

@if($errors->any() && !old('_quick'))<div class="alert alert-error">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

<div class="panel">
    <div class="panel-header"><h2>Teams</h2><span class="muted small">{{ $teams->count() }} teams</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Code</th><th>Name</th><th>Lead</th><th>Phone</th><th>HR members linked / configured</th><th>Status</th><th>Runs</th><th>Made</th><th>Accepted</th><th>Damaged %</th><th>Downtime min</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($teams as $t)
            @php($s = $teamStats[$t->id] ?? null)
            <tr>
                <td><strong>{{ $t->code }}</strong></td><td>{{ $t->name }}</td><td>{{ $t->leadEmployee?->full_name ?? $t->lead_name }}</td><td>{{ $t->lead_phone ?: '—' }}</td><td>{{ $t->employees_count }} / {{ $t->member_count }}</td><td><span class="status {{ $t->status === 'ACTIVE' ? 'status-completed' : '' }}">{{ $t->status }}</span></td>
                <td>{{ $s->runs ?? 0 }}</td><td>{{ $s->made ?? 0 }}</td><td>{{ $s->accepted ?? 0 }}</td><td>{{ ($s->made ?? 0) > 0 ? round(100 * $s->damaged / $s->made, 1) : '—' }}</td><td>{{ $s->downtime ?? 0 }}</td>
                <td><button type="button" class="btn btn-small" data-edit-team data-id="{{ $t->id }}" data-code="{{ $t->code }}" data-name="{{ $t->name }}" data-lead="{{ $t->lead_name }}" data-lead-employee-id="{{ $t->lead_employee_id }}" data-phone="{{ $t->lead_phone }}" data-members="{{ $t->member_count }}" data-employee-ids="{{ $t->employees->pluck('id')->implode(',') }}" data-status="{{ $t->status }}" data-notes="{{ $t->notes }}">Edit</button></td>
            </tr>
        @empty<tr><td colspan="12" class="muted">No teams yet. Use Add team to create the first one.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Stations</h2><span class="muted small">{{ $stations->count() }} stations</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Patterns at once</th><th>Default team</th><th>Sand system</th><th>Flasks</th><th>Status</th><th>Runs</th><th>Made</th><th>Damaged %</th><th>Downtime min</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($stations as $st)
            @php($s = $stationStats[$st->id] ?? null)
            <tr>
                <td><strong>{{ $st->code }}</strong></td><td>{{ $st->name }}</td><td>{{ $stationTypes[$st->station_type] ?? $st->station_type }}</td><td>{{ $st->capacity() }}</td><td>{{ $st->defaultTeam ? $st->defaultTeam->code.' — '.$st->defaultTeam->name : '—' }}</td><td>{{ $st->default_sand_system ?: 'Default sand' }}</td><td>{{ $st->makes_flaskless ? 'Flaskless' : ($st->flaskSizes->isEmpty() ? 'Any size' : $st->flaskSizes->pluck('name')->implode(', ')) }}</td>
                <td><span class="status {{ $st->status === 'ACTIVE' ? 'status-completed' : '' }}">{{ $st->status }}</span></td><td>{{ $s->runs ?? 0 }}</td><td>{{ $s->made ?? 0 }}</td><td>{{ ($s->made ?? 0) > 0 ? round(100 * $s->damaged / $s->made, 1) : '—' }}</td><td>{{ $s->downtime ?? 0 }}</td>
                <td><button type="button" class="btn btn-small" data-edit-station data-id="{{ $st->id }}" data-code="{{ $st->code }}" data-name="{{ $st->name }}" data-type="{{ $st->station_type }}" data-max-patterns="{{ $st->max_patterns }}" data-team="{{ $st->default_moulding_team_id }}" data-sand="{{ $st->default_sand_system }}" data-status="{{ $st->status }}" data-flaskless="{{ $st->makes_flaskless ? '1' : '0' }}" data-flasks="{{ $st->flaskSizes->pluck('id')->implode(',') }}" data-notes="{{ $st->notes }}">Edit</button></td>
            </tr>
        @empty<tr><td colspan="13" class="muted">No stations yet. Use Add station to create the first one.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
<p class="muted small">Sand systems are managed under Production → <a href="{{ route('sand-systems.index') }}">Sand systems</a>.</p>

{{-- Edit dialogs keep the table display-only and expose controls only on an explicit Edit action. --}}
<dialog id="edit-team-dialog" class="qd" aria-labelledby="edit-team-title">
    <form method="POST" id="edit-team-form">@csrf @method('PUT')@if ($hrAvailable)<input type="hidden" name="employee_ids_sent" value="1">@endif
        <div class="qd-head"><h2 id="edit-team-title">Edit team</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
        <div class="qd-body"><div class="qd-fields">
            <div class="field"><label for="et-code">Code <span class="required">*</span></label><input id="et-code" name="code" required maxlength="30" pattern="[A-Za-z0-9._-]+" title="Use letters, numbers, dots, underscores or hyphens."></div>
            <div class="field"><label for="et-name">Team name <span class="required">*</span></label><input id="et-name" name="name" required maxlength="100"></div>
            @if ($hrAvailable)
            <div class="field"><label for="et-lead-employee">Team lead <span class="required">*</span></label><select id="et-lead-employee" name="lead_employee_id" required data-team-lead-select><option value="">Select a linked member</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" data-employee-option data-full-name="{{ $employee->full_name }}">{{ $employee->code }} — {{ $employee->full_name }}</option>@endforeach</select><div class="help">Only employees selected below can be team lead.</div></div>
            @else
            <div class="field"><label for="et-lead-name">Team lead <span class="required">*</span></label><input id="et-lead-name" name="lead_name" required maxlength="150"></div>
            <div class="field"><label for="et-members">Members <span class="required">*</span></label><input id="et-members" name="member_count" type="number" min="1" max="200" required></div>
            @endif
            <div class="field"><label for="et-phone">Lead phone</label><input id="et-phone" name="lead_phone" type="tel" maxlength="40" autocomplete="tel"></div>
            @if ($hrAvailable)
            <div class="field field-full"><label>Team members from HR</label>
                <input type="search" class="member-filter" data-member-filter placeholder="Filter by employee name, code, department…" aria-label="Filter HR employees">
                <div class="qd-checklist member-options" data-member-list>
                    @forelse($employees as $employee)
                        <label data-member-row><input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" data-team-member-option>
                            <span><strong>{{ $employee->code }} — {{ $employee->full_name }}</strong><small>{{ $employee->designation?->name ?? 'No designation' }} · {{ $employee->department?->name ?? 'No department' }} · {{ $employee->status }}</small></span>
                        </label>
                    @empty
                        <span class="muted">No current employees are available in HR. Add them under HR → Employees first.</span>
                    @endforelse
                </div>
                <div class="help"><span data-member-selected-count>0</span> selected. Member count is calculated from the selected HR records.</div>
            </div>
            @endif
            <div class="field"><label for="et-status">Status <span class="required">*</span></label><select id="et-status" name="status" required><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select></div>
            <div class="field field-full"><label for="et-notes">Notes</label><textarea id="et-notes" name="notes" maxlength="1000" rows="3"></textarea></div>
        </div></div>
        <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
    </form>
</dialog>

<dialog id="edit-station-dialog" class="qd" aria-labelledby="edit-station-title">
    <form method="POST" id="edit-station-form">@csrf @method('PUT')
        <input type="hidden" name="flask_sizes_sent" value="1">
        <div class="qd-head"><h2 id="edit-station-title">Edit station</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
        <div class="qd-body"><div class="qd-fields">
            <div class="field"><label for="es-code">Code <span class="required">*</span></label><input id="es-code" name="code" required maxlength="30" pattern="[A-Za-z0-9._-]+" title="Use letters, numbers, dots, underscores or hyphens."></div>
            <div class="field"><label for="es-name">Name <span class="required">*</span></label><input id="es-name" name="name" required maxlength="100"></div>
            <div class="field field-full"><label for="es-type">Station type <span class="required">*</span></label><select id="es-type" name="station_type" required>@foreach($stationTypes as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="es-capacity">Patterns at once <span class="required">*</span></label><input id="es-capacity" name="max_patterns" type="number" min="1" max="20" step="1" required><div class="help">Automated lines always use one.</div></div>
            <div class="field"><label for="es-team">Team on this line</label><select id="es-team" name="default_moulding_team_id"><option value="">No default team</option>@foreach($teams as $t)<option value="{{ $t->id }}">{{ $t->code }} — {{ $t->name }}{{ $t->status === 'ACTIVE' ? '' : ' (inactive)' }}</option>@endforeach</select></div>
            <div class="field"><label for="es-sand">Default sand system</label><select id="es-sand" name="default_sand_system"><option value="">Use system default</option>@foreach($sands as $sd)<option value="{{ $sd }}">{{ $sd }}</option>@endforeach</select></div>
            <div class="field"><label for="es-status">Status <span class="required">*</span></label><select id="es-status" name="status" required><option value="ACTIVE">Active</option><option value="MAINTENANCE">Maintenance</option><option value="INACTIVE">Inactive</option></select></div>
            <div class="field field-full"><label class="check-field"><input id="es-flaskless" type="checkbox" name="makes_flaskless" value="1"> Makes flaskless moulds</label></div>
            <div class="field field-full" id="es-flask-group"><label>Flask sizes handled</label><div class="qd-checklist">@forelse($flaskSizes as $fs)<label><input type="checkbox" name="flask_size_ids[]" value="{{ $fs->id }}" data-flask-option> {{ $fs->name }}</label>@empty<span class="muted">No active flask sizes. Add them on the <a href="{{ route('flasks.index') }}">flask board</a>.</span>@endforelse</div></div>
            <div class="field field-full"><label for="es-notes">Notes</label><textarea id="es-notes" name="notes" maxlength="1000" rows="3"></textarea></div>
        </div></div>
        <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
    </form>
</dialog>

@push('styles')
<style>
.qd-checklist { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px 12px; padding:8px 10px; border:1px solid var(--border); border-radius:6px; }
.qd-checklist label, .check-field { display:flex!important; align-items:center; gap:8px; font-weight:500!important; }
.member-options { max-height:260px; overflow:auto; margin-top:8px; align-content:start; }
.member-options label { align-items:flex-start!important; }
.member-options label span { display:grid; gap:2px; min-width:0; }
.member-options small { color:var(--muted); font-size:12px; line-height:1.35; }
.member-filter { margin-top:6px; }
.qd-checklist input[type=checkbox], .check-field input[type=checkbox] { width:16px; min-height:16px; margin:0; }
@media(max-width:768px){.qd-checklist{grid-template-columns:1fr}}
</style>
@endpush
@push('dialogs')
<script>
(function () {
    function setValue(form, name, value) { var field = form.elements.namedItem(name); if (field) field.value = value || ''; }
    function updateLeadSelect(scope, preferredId) {
        var dlg = scope && scope.matches && scope.matches('dialog') ? scope : (scope && scope.closest ? scope.closest('dialog') : null);
        if (!dlg) return;
        var select = dlg.querySelector('[data-team-lead-select]');
        if (!select) return;
        var selected = new Set(Array.from(dlg.querySelectorAll('[data-team-member-option]:checked')).map(function (box) { return box.value; }));
        Array.from(select.options).forEach(function (option) {
            if (option.value) option.disabled = !selected.has(option.value);
        });
        if (select.value && !selected.has(select.value)) select.value = '';
        if (preferredId && selected.has(preferredId)) select.value = preferredId;
    }
    function refreshMemberCounts(scope) {
        function update(dlg) {
            var count = dlg.querySelector('[data-member-selected-count]');
            if (count) count.textContent = dlg.querySelectorAll('[data-team-member-option]:checked').length;
            updateLeadSelect(dlg);
        }
        if (scope && scope.closest) {
            var parentDialog = scope.matches('dialog') ? scope : scope.closest('dialog');
            if (parentDialog) update(parentDialog);
        }
        (scope || document).querySelectorAll('dialog').forEach(update);
    }
    document.addEventListener('input', function (event) {
        var filter = event.target.closest('[data-member-filter]'); if (!filter) return;
        var dlg = filter.closest('dialog'), term = filter.value.trim().toLowerCase();
        dlg.querySelectorAll('[data-member-row]').forEach(function (row) { row.hidden = !row.textContent.toLowerCase().includes(term); });
    });
    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-team-member-option]')) refreshMemberCounts(event.target.closest('dialog'));
    });
    document.addEventListener('DOMContentLoaded', function () { refreshMemberCounts(document); });
    document.addEventListener('click', function (event) {
        var teamButton = event.target.closest('[data-edit-team]');
        if (teamButton) {
            var form = document.getElementById('edit-team-form'), d = teamButton.dataset;
            form.action = @json(url('/moulding-teams/teams')) + '/' + encodeURIComponent(d.id);
            setValue(form, 'code', d.code); setValue(form, 'name', d.name); setValue(form, 'lead_phone', d.phone); setValue(form, 'lead_name', d.lead); setValue(form, 'member_count', d.members); setValue(form, 'status', d.status); setValue(form, 'notes', d.notes);
            var selectedEmployees = (d.employeeIds || '').split(',').filter(Boolean);
            form.querySelectorAll('[data-team-member-option]').forEach(function (box) { box.checked = selectedEmployees.includes(box.value); });
            form.querySelectorAll('[data-member-filter]').forEach(function (field) { field.value = ''; });
            refreshMemberCounts(form);
            var preferredLead = d.leadEmployeeId || '';
            if (!preferredLead && d.lead) {
                var matchingOption = Array.from(form.querySelectorAll('[data-team-lead-select] [data-employee-option]')).find(function (option) { return option.dataset.fullName === d.lead && selectedEmployees.includes(option.value); });
                if (matchingOption) preferredLead = matchingOption.value;
            }
            updateLeadSelect(form, preferredLead);
            document.getElementById('edit-team-dialog').showModal(); return;
        }
        var stationButton = event.target.closest('[data-edit-station]');
        if (stationButton) {
            var form = document.getElementById('edit-station-form'), d = stationButton.dataset;
            form.action = @json(url('/moulding-teams/stations')) + '/' + encodeURIComponent(d.id);
            setValue(form, 'code', d.code); setValue(form, 'name', d.name); setValue(form, 'station_type', d.type); setValue(form, 'max_patterns', d.maxPatterns); setValue(form, 'default_moulding_team_id', d.team); setValue(form, 'default_sand_system', d.sand); setValue(form, 'status', d.status); setValue(form, 'notes', d.notes);
            document.getElementById('es-flaskless').checked = d.flaskless === '1';
            var chosen = (d.flasks || '').split(',');
            form.querySelectorAll('[data-flask-option]').forEach(function (box) { box.checked = chosen.includes(box.value); box.disabled = d.flaskless === '1'; });
            document.getElementById('es-capacity').readOnly = d.type === 'AUTOMATED_LINE';
            if (d.type === 'AUTOMATED_LINE') document.getElementById('es-capacity').value = 1;
            document.getElementById('edit-station-dialog').showModal();
        }
        var close = event.target.closest('dialog.qd [data-close]');
        if (close) close.closest('dialog').close();
        if (event.target.matches('dialog.qd')) event.target.close();
    });
    refreshMemberCounts(document);
    document.querySelectorAll('#qd-team, #edit-team-dialog').forEach(function (dlg) { updateLeadSelect(dlg); });
    document.getElementById('qd-station-type').addEventListener('change', function () {
        var automated = this.value === 'AUTOMATED_LINE', capacity = document.getElementById('qd-station-max');
        capacity.readOnly = automated; if (automated) capacity.value = 1;
    });
    document.getElementById('qd-station-flaskless').addEventListener('change', function () {
        document.querySelectorAll('#qd-station [name="flask_size_ids[]"]').forEach(function (box) { box.disabled = this.checked; if (this.checked) box.checked = false; }, this);
    });
    document.getElementById('es-type').addEventListener('change', function () {
        var automated = this.value === 'AUTOMATED_LINE', capacity = document.getElementById('es-capacity');
        capacity.readOnly = automated; if (automated) capacity.value = 1;
    });
    document.getElementById('es-flaskless').addEventListener('change', function () {
        document.querySelectorAll('[data-flask-option]').forEach(function (box) { box.disabled = this.checked; if (this.checked) box.checked = false; }, this);
    });
})();
</script>
@endpush
@endsection
