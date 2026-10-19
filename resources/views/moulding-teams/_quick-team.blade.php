{{-- Fields for the Add team dialog. --}}
@if ($part === 'required')
<input type="hidden" name="status" value="ACTIVE">
@if ($hrAvailable)<input type="hidden" name="employee_ids_sent" value="1">@endif
<div class="field"><label for="qd-team-code">Code <span class="required">*</span></label><input id="qd-team-code" name="code" required maxlength="30" pattern="[A-Za-z0-9._-]+" title="Use letters, numbers, dots, underscores or hyphens." placeholder="T1" value="{{ $old('code') }}"></div>
<div class="field"><label for="qd-team-name">Team name <span class="required">*</span></label><input id="qd-team-name" name="name" required maxlength="100" value="{{ $old('name') }}"></div>
@if ($hrAvailable)
<div class="field"><label for="qd-team-lead-employee">Team lead <span class="required">*</span></label><select id="qd-team-lead-employee" name="lead_employee_id" required data-team-lead-select><option value="">Select a linked member</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" data-employee-option data-full-name="{{ $employee->full_name }}" @selected($old('lead_employee_id') === $employee->id)>{{ $employee->code }} — {{ $employee->full_name }}</option>@endforeach</select><div class="help">Only employees selected below can be team lead.</div></div>
@else
<div class="field"><label for="qd-team-lead-name">Team lead <span class="required">*</span></label><input id="qd-team-lead-name" name="lead_name" required maxlength="150" value="{{ $old('lead_name') }}"></div>
<div class="field"><label for="qd-team-members">Members <span class="required">*</span></label><input id="qd-team-members" name="member_count" type="number" min="1" max="200" required value="{{ $old('member_count', 1) }}"></div>
@endif

@else
<div class="field"><label for="qd-team-phone">Lead phone</label><input id="qd-team-phone" name="lead_phone" type="tel" maxlength="40" autocomplete="tel" value="{{ $old('lead_phone') }}"></div>
@if ($hrAvailable)
<div class="field field-full"><label>Team members from HR</label>
    <input type="search" class="member-filter" data-member-filter placeholder="Filter by employee name, code, department…" aria-label="Filter HR employees">
    <div class="qd-checklist member-options" data-member-list>
        @forelse($employees as $employee)
            <label data-member-row><input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" data-team-member-option @checked(in_array($employee->id, (array) $old('employee_ids', []), true))>
                <span><strong>{{ $employee->code }} — {{ $employee->full_name }}</strong><small>{{ $employee->designation?->name ?? 'No designation' }} · {{ $employee->department?->name ?? 'No department' }} · {{ $employee->status }}</small></span>
            </label>
        @empty
            <span class="muted">No current employees are available in HR. Add them under HR → Employees first.</span>
        @endforelse
    </div>
    <div class="help"><span data-member-selected-count>0</span> selected. The configured member count is calculated from these HR links; exited employees cannot be assigned.</div>
</div>
@endif
<div class="field field-full"><label for="qd-team-notes">Notes</label><textarea id="qd-team-notes" name="notes" maxlength="1000" rows="3">{{ $old('notes') }}</textarea></div>
@endif
