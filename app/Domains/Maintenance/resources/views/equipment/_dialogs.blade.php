{{-- The forms of the maintenance screen. Each posts to its own route and reopens with its errors when refused. A button with data-act
     points the shared dialog (complete, restore, reading) at one record. --}}
@include('partials.quick-dialog-assets')
@php
    $dlg = fn (string $id) => old('_quick') === $id;
    $o = fn (string $id, string $key, $default = null) => $dlg($id) ? old($key, $default) : $default;
@endphp
@pushOnce('dialogs', 'maintenance-dialogs')
@foreach ([
    ['qd-equipment', 'New equipment', route('equipment-maintenance.equipment.store'), 'Add equipment'],
    ['qd-meter', 'Add a meter', route('equipment-maintenance.meters.store'), 'Add meter'],
    ['qd-reading', 'Meter reading', '#', 'Save reading'],
    ['qd-plan', 'New plan', route('equipment-maintenance.plans.store'), 'Add plan'],
    ['qd-work-order', 'New work order', route('equipment-maintenance.work-orders.store'), 'Create work order'],
    ['qd-complete', 'Complete work order', '#', 'Complete'],
    ['qd-breakdown', 'Log breakdown', route('equipment-maintenance.breakdowns.store'), 'Log breakdown'],
    ['qd-restore', 'Restore asset', '#', 'Mark restored'],
] as [$id, $title, $action, $submit])
<dialog id="{{ $id }}" class="qd mt-modal" aria-labelledby="{{ $id }}-title" @if ($dlg($id) && $errors->any()) data-reopen="{{ json_encode($errors->getMessages()) }}" @endif>
    <form method="POST" action="{{ $action }}">@csrf<input type="hidden" name="_quick" value="{{ $id }}">
        <div class="qd-head"><h2 id="{{ $id }}-title">{{ $title }}</h2><button type="button" class="qd-x" data-close aria-label="Close">&times;</button></div>
        <div class="qd-body">
            <div class="alert alert-error qd-errors" role="alert" @unless ($dlg($id) && $errors->any()) hidden @endunless>@if ($dlg($id))@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach @endif</div>
            <div class="qd-fields">
            @switch($id)
                @case('qd-equipment')
                    <div class="field"><label>Code</label><input name="code" required maxlength="40" value="{{ $o($id, 'code') }}"></div>
                    <div class="field"><label>Name</label><input name="name" required maxlength="255" value="{{ $o($id, 'name') }}"></div>
                    <div class="field"><label>Type</label><select name="equipment_type">@foreach ($equipmentTypes as $k => $l)<option value="{{ $k }}" @selected($o($id, 'equipment_type') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label>Status</label><select name="status">@foreach ($equipmentStatuses as $k => $l)<option value="{{ $k }}" @selected($o($id, 'status', 'AVAILABLE') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label>Manufacturer</label><input name="manufacturer" maxlength="100" value="{{ $o($id, 'manufacturer') }}"></div>
                    <div class="field"><label>Model</label><input name="model" maxlength="100" value="{{ $o($id, 'model') }}"></div>
                    <div class="field"><label>Serial number</label><input name="serial_number" maxlength="100" value="{{ $o($id, 'serial_number') }}"></div>
                    <div class="field"><label>Location</label><input name="location_description" maxlength="1000" value="{{ $o($id, 'location_description') }}"></div>
                    @break
                @case('qd-meter')
                    <div class="field field-full"><label>Asset</label><select name="equipment_id" required><option value="">Select asset</option>@foreach ($equipment as $e)<option value="{{ $e->id }}" @selected($o($id, 'equipment_id') === $e->id)>{{ $e->code }} · {{ $e->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Meter code</label><input name="meter_code" required maxlength="40" value="{{ $o($id, 'meter_code') }}"></div>
                    <div class="field"><label>Name</label><input name="name" required maxlength="150" value="{{ $o($id, 'name') }}" placeholder="Moulds made"></div>
                    <div class="field"><label>Counts</label><select name="unit">@foreach ($meterUnits as $k => $l)<option value="{{ $k }}" @selected($o($id, 'unit') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label>Reading now</label><input type="number" step="any" min="0" name="current_reading" value="{{ $o($id, 'current_reading') }}"></div>
                    @break
                @case('qd-reading')
                    <div class="field"><label>Reading</label><input type="number" step="any" min="0" name="reading_value" required value="{{ $o($id, 'reading_value') }}"></div>
                    <div class="field"><label>Taken at</label><input type="datetime-local" name="recorded_at" value="{{ $o($id, 'recorded_at') }}"></div>
                    <div class="field field-full"><label>Notes</label><input name="notes" maxlength="500" value="{{ $o($id, 'notes') }}"></div>
                    @break
                @case('qd-plan')
                    <div class="field field-full"><label>Asset</label><select name="equipment_id" required><option value="">Select asset</option>@foreach ($equipment as $e)<option value="{{ $e->id }}" @selected($o($id, 'equipment_id') === $e->id)>{{ $e->code }} · {{ $e->name }}</option>@endforeach</select></div>
                    <div class="field field-full"><label>Plan name</label><input name="name" required maxlength="255" value="{{ $o($id, 'name') }}" placeholder="Weekly hydraulic check"></div>
                    <div class="field"><label>Type</label><select name="maintenance_type">@foreach ($planTypes as $k => $l)<option value="{{ $k }}" @selected($o($id, 'maintenance_type') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label>Runs by</label><select name="trigger_type">@foreach ($triggers as $k => $l)<option value="{{ $k }}" @selected($o($id, 'trigger_type') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label>Every, days</label><input type="number" min="1" name="interval_days" value="{{ $o($id, 'interval_days') }}"></div>
                    <div class="field"><label>Every, meter units</label><input type="number" step="any" min="0" name="interval_meter_units" value="{{ $o($id, 'interval_meter_units') }}"></div>
                    <div class="field"><label>First due</label><input type="date" name="next_due_at" value="{{ $o($id, 'next_due_at') }}"></div>
                    <div class="field"><label>Hours needed</label><input type="number" step="any" min="0" name="estimated_duration_hours" value="{{ $o($id, 'estimated_duration_hours') }}"></div>
                    <div class="field field-full"><label>Instructions</label><textarea name="instructions" rows="2" maxlength="2000">{{ $o($id, 'instructions') }}</textarea></div>
                    <label class="field field-full" style="flex-direction:row;gap:8px"><input type="hidden" name="requires_shutdown" value="0"><input type="checkbox" name="requires_shutdown" value="1" @checked($o($id, 'requires_shutdown'))> Needs the asset shut down</label>
                    @break
                @case('qd-work-order')
                    <div class="field field-full"><label>Asset</label><select name="equipment_id" required><option value="">Select asset</option>@foreach ($equipment as $e)<option value="{{ $e->id }}" @selected($o($id, 'equipment_id') === $e->id)>{{ $e->code }} · {{ $e->name }}</option>@endforeach</select></div>
                    <div class="field field-full"><label>From plan (optional)</label><select name="maintenance_plan_id"><option value="">None, a one-off job</option>@foreach ($plans as $p)<option value="{{ $p->id }}" @selected($o($id, 'maintenance_plan_id') === $p->id)>{{ $p->code }} · {{ $p->name }} · {{ $p->equipment?->code }}</option>@endforeach</select></div>
                    <div class="field"><label>Type</label><select name="maintenance_type">@foreach ($workOrderTypes as $k => $l)<option value="{{ $k }}" @selected($o($id, 'maintenance_type') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label>Priority</label><select name="priority">@foreach ($priorities as $k => $l)<option value="{{ $k }}" @selected($o($id, 'priority', 'NORMAL') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label>Scheduled for</label><input type="datetime-local" name="scheduled_start_at" value="{{ $o($id, 'scheduled_start_at') }}"></div>
                    <div class="field"><label>Assigned to</label><input name="assigned_to" maxlength="150" value="{{ $o($id, 'assigned_to') }}"></div>
                    <div class="field field-full"><label>Problem or job</label><textarea name="problem_description" rows="2" maxlength="2000">{{ $o($id, 'problem_description') }}</textarea></div>
                    @break
                @case('qd-complete')
                    <div class="field field-full"><label>Work performed</label><textarea name="work_performed" rows="3" required maxlength="4000">{{ $o($id, 'work_performed') }}</textarea></div>
                    <div class="field field-full"><label>Findings</label><textarea name="findings" rows="2" maxlength="4000">{{ $o($id, 'findings') }}</textarea></div>
                    <div class="field"><label>Labour hours</label><input type="number" step="any" min="0" name="labor_hours" value="{{ $o($id, 'labor_hours') }}"></div>
                    <div class="field"><label>Parts cost</label><input type="number" step="any" min="0" name="parts_cost" value="{{ $o($id, 'parts_cost') }}"></div>
                    <div class="field"><label>Contractor and other cost</label><input type="number" step="any" min="0" name="other_cost" value="{{ $o($id, 'other_cost') }}"></div>
                    @break
                @case('qd-breakdown')
                    <div class="field field-full"><label>Asset</label><select name="equipment_id" required><option value="">Select asset</option>@foreach ($equipment as $e)<option value="{{ $e->id }}" @selected($o($id, 'equipment_id') === $e->id)>{{ $e->code }} · {{ $e->name }}</option>@endforeach</select></div>
                    <div class="field"><label>Severity</label><select name="severity">@foreach ($severities as $k => $l)<option value="{{ $k }}" @selected($o($id, 'severity') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label>Went down at</label><input type="datetime-local" name="started_at" required value="{{ $o($id, 'started_at', now()->format('Y-m-d\TH:i')) }}"></div>
                    <div class="field"><label>Kind of fault</label><select name="failure_category"><option value="">Not known yet</option>@foreach ($categories as $k => $l)<option value="{{ $k }}" @selected($o($id, 'failure_category') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label>Reported by</label><input name="reported_by" maxlength="150" value="{{ $o($id, 'reported_by') }}"></div>
                    <div class="field field-full"><label>What happened</label><textarea name="failure_description" rows="2" required maxlength="2000">{{ $o($id, 'failure_description') }}</textarea></div>
                    @break
                @case('qd-restore')
                    <div class="field"><label>Back up at (blank: now)</label><input type="datetime-local" name="restored_at" value="{{ $o($id, 'restored_at') }}"></div>
                    <div class="field field-full"><label>Root cause</label><textarea name="root_cause" rows="2" maxlength="2000">{{ $o($id, 'root_cause') }}</textarea></div>
                    <div class="field field-full"><label>What was done</label><textarea name="corrective_action" rows="2" maxlength="2000">{{ $o($id, 'corrective_action') }}</textarea></div>
                    @break
            @endswitch
            </div>
        </div>
        <div class="qd-foot"><button type="button" class="btn" data-close>Cancel</button><button class="btn btn-primary" type="submit">{{ $submit }}</button></div>
    </form>
</dialog>
@endforeach
<script>
// A button with data-act points the shared dialog (complete, restore, reading) at its record before the dialog opens.
document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-act]'); if (!b) return;
    var d = document.getElementById(b.dataset.quick); if (!d) return;
    d.querySelector('form').action = b.dataset.act;
    if (b.dataset.title) d.querySelector('.qd-head h2').textContent = b.dataset.title;
});
</script>
@endPushOnce
