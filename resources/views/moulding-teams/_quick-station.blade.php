{{-- Fields of the quick "Add a station" dialog (partials.quick-dialog). The sand system is set on Teams and stations. --}}
@if ($part === 'required')
<input type="hidden" name="status" value="ACTIVE">
<div class="field"><label for="qd-station-code">Code</label><input id="qd-station-code" name="code" required maxlength="30" placeholder="MP-1" value="{{ $old('code') }}"></div>
<div class="field"><label for="qd-station-name">Name</label><input id="qd-station-name" name="name" required maxlength="100" value="{{ $old('name') }}"></div>
<div class="field field-full"><label for="qd-station-type">Type</label><select id="qd-station-type" name="station_type" required>@foreach (\App\Models\MouldingStation::TYPES as $k => $l)<option value="{{ $k }}" @selected($old('station_type') === $k)>{{ $l }}</option>@endforeach</select></div>
@else
<div class="field"><label for="qd-station-max">Patterns at once</label><input id="qd-station-max" type="number" name="max_patterns" min="1" max="20" value="{{ $old('max_patterns') }}" title="An automated line is always one"></div>
<div class="field"><label for="qd-station-team">Team on this line</label><select id="qd-station-team" name="default_moulding_team_id" data-quick-list="team"><option value="">—</option>@foreach (\App\Models\MouldingTeam::where('status', 'ACTIVE')->orderBy('code')->get() as $t)<option value="{{ $t->id }}" @selected($old('default_moulding_team_id') === $t->id)>{{ $t->code }} — {{ $t->lead_name }}</option>@endforeach</select></div>
<div class="field field-full"><label for="qd-station-notes">Notes</label><input id="qd-station-notes" name="notes" maxlength="1000" value="{{ $old('notes') }}"></div>
@endif
