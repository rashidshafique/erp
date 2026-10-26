{{-- Sand system a pattern was made for, the others it works on, and a note on taper. $pattern may be new. --}}
@php($stationList = \App\Models\MouldingStation::where('status', '!=', 'INACTIVE')->orderBy('code')->get())
<div class="field" data-keep>
    <label for="preferred_moulding_station_id">Preferred moulding station</label>
    <select id="preferred_moulding_station_id" name="preferred_moulding_station_id" style="max-width:260px">
        <option value="">None</option>
        @foreach ($stationList as $st)<option value="{{ $st->id }}" data-sand="{{ $st->default_sand_system }}" @selected(old('preferred_moulding_station_id', $pattern->preferred_moulding_station_id) === $st->id)>{{ $st->code }} — {{ $st->name }}</option>@endforeach
    </select>
    <div class="help">A new run of this pattern starts on this station.</div>
</div>
<div class="field" data-keep>
    <label for="sand_system">Made for sand system <a href="{{ route('sand-systems.index') }}" class="qd-add" data-quick="qd-sand" data-quick-target="sand_system" data-quick-value="label">+ Add</a></label>
    @include('partials.quick-dialog', ['id' => 'qd-sand', 'title' => 'Add a sand system', 'action' => route('sand-systems.store'), 'submit' => 'Add sand system', 'fields' => 'sand-systems._quick-fields'])
    <select id="sand_system" name="sand_system" style="max-width:260px">
        <option value="" id="sand-follows-station">Not recorded (suits any)</option>
        @foreach ($sands as $sd)<option value="{{ $sd }}" @selected(old('sand_system', $pattern->sand_system) === $sd)>{{ $sd }}</option>@endforeach
    </select>
    <div class="help">Left on the first option, the sand follows the preferred station's default. Choose a sand here to use that one instead.</div>
</div>
<script>
(function () {
    var station = document.getElementById('preferred_moulding_station_id'), first = document.getElementById('sand-follows-station');
    function label() {
        var sand = station.selectedOptions[0] ? station.selectedOptions[0].dataset.sand : '';
        first.textContent = station.value ? (sand ? 'Station default (' + sand + ')' : 'Station default (no sand set)') : 'Not recorded (suits any)';
    }
    station.addEventListener('change', label); label();
})();
</script>
<div class="field" data-keep>
    <label for="flasks_freed">Flasks freed</label>
    <select id="flasks_freed" name="flasks_freed" style="max-width:260px">
        <option value="">As its sand system says</option>
        @foreach (\App\Services\Production\FlaskManager::FREED as $key => $text)<option value="{{ $key }}" @selected(old('flasks_freed', $pattern->flasks_freed) === $key)>{{ $text }}</option>@endforeach
    </select>
    <div class="help">At pour: the flask is stripped and the sand cake handled without it. Work lists, runs and the pour start from this and can change it.</div>
</div>
<div class="field">
    <label>Also works on</label>
    <div style="display:flex;gap:14px;flex-wrap:wrap">
        @foreach ($sands as $sd)
            <label style="font-weight:400;display:inline-flex;gap:6px;align-items:center"><input type="checkbox" name="compatible_sands[]" value="{{ $sd }}" @checked(in_array($sd, old('compatible_sands', $pattern->compatible_sands ?? []), true))>{{ $sd }}</label>
        @endforeach
    </div>
</div>
<div class="field field-full">
    <label for="sand_note">Sand note <span class="muted small">(taper allowance, why it does not suit another sand)</span></label>
    <input id="sand_note" name="sand_note" maxlength="1000" value="{{ old('sand_note', $pattern->sand_note) }}" style="max-width:520px">
</div>
