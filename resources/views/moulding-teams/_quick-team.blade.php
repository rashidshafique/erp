{{-- Fields of the quick "Add a team" dialog (partials.quick-dialog). --}}
@if ($part === 'required')
<input type="hidden" name="status" value="ACTIVE">
<div class="field"><label for="qd-team-code">Code</label><input id="qd-team-code" name="code" required maxlength="30" placeholder="T1" value="{{ $old('code') }}"></div>
<div class="field"><label for="qd-team-name">Team name</label><input id="qd-team-name" name="name" required maxlength="100" value="{{ $old('name') }}"></div>
<div class="field"><label for="qd-team-lead">Team lead</label><input id="qd-team-lead" name="lead_name" required maxlength="100" value="{{ $old('lead_name') }}"></div>
<div class="field"><label for="qd-team-members">Members</label><input id="qd-team-members" type="number" name="member_count" required min="1" max="200" value="{{ $old('member_count', 4) }}"></div>
@else
<div class="field"><label for="qd-team-phone">Lead phone</label><input id="qd-team-phone" name="lead_phone" maxlength="30" value="{{ $old('lead_phone') }}"></div>
<div class="field field-full"><label for="qd-team-notes">Notes</label><input id="qd-team-notes" name="notes" maxlength="1000" value="{{ $old('notes') }}"></div>
@endif
