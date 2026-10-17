{{-- Sand system a pattern was made for, the others it works on, and a note on taper. $pattern may be new. --}}
<div class="field" data-keep>
    <label for="sand_system">Made for sand system <a href="{{ route('sand-systems.index') }}" class="qd-add" data-quick="qd-sand" data-quick-target="sand_system" data-quick-value="label">+ Add</a></label>
    @include('partials.quick-dialog', ['id' => 'qd-sand', 'title' => 'Add a sand system', 'action' => route('sand-systems.store'), 'submit' => 'Add sand system', 'fields' => 'sand-systems._quick-fields'])
    <select id="sand_system" name="sand_system" style="max-width:260px">
        <option value="">Not recorded (suits any)</option>
        @foreach ($sands as $sd)<option value="{{ $sd }}" @selected(old('sand_system', $pattern->sand_system) === $sd)>{{ $sd }}</option>@endforeach
    </select>
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
