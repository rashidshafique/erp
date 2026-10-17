{{-- Fields of the quick "Add a sand system" dialog (partials.quick-dialog). The code is made from the name; any other sands it uses are set on Production → Sand systems. --}}
@if ($part === 'required')
<div class="field"><label for="qd-sand-name">Name</label><input id="qd-sand-name" name="name" required maxlength="100" placeholder="e.g. No-bake" value="{{ $old('name') }}"></div>
<div class="field"><label for="qd-sand-type">Type</label><select id="qd-sand-type" name="system_type" required>@foreach (\App\Models\SandSystem::TYPES as $k => $l)<option value="{{ $k }}" @selected($old('system_type') === $k)>{{ $l }}</option>@endforeach</select></div>
@else
<div class="field"><label for="qd-sand-main">Main sand</label><select id="qd-sand-main" name="main_sand_id"><option value="">—</option>@foreach (\App\Models\BaseSand::ordered()->where('status', 'ACTIVE')->get() as $b)<option value="{{ $b->id }}" @selected($old('main_sand_id') ? $old('main_sand_id') === $b->id : $b->code === 'SILICA')>{{ $b->name }}</option>@endforeach</select></div>
<div class="field"><label for="qd-sand-desc">Description</label><input id="qd-sand-desc" name="description" maxlength="1000" value="{{ $old('description') }}"></div>
<p class="muted small field-full" style="margin:0">The code is made from the name. Other sands it uses are added under Production → Sand systems.</p>
@endif
