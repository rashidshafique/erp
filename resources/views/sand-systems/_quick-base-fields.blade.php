{{-- Fields of the "Add a sand" dialog (base sands such as silica, chromite, zircon). --}}
<div class="field"><label for="qb-name">Name *</label><input id="qb-name" name="name" required maxlength="100" placeholder="e.g. Reclaimed silica" value="{{ old('name') }}"></div>
<div class="field"><label for="qb-material">Inventory material</label>
    <select id="qb-material" name="material_id"><option value="">— not linked —</option>@foreach($sandMaterials as $m)<option value="{{ $m->id }}" @selected(old('material_id') === $m->id)>{{ $m->code }} · {{ $m->name }}</option>@endforeach</select>
    <div class="help">@if($sandMaterials->isEmpty())No raw materials of type Sand yet. Add one under Materials, then link it here.@else Only raw materials of type Sand are listed; each can back one sand.@endif</div></div>
<div class="field field-full" data-keep><label for="qb-desc">Description</label><input id="qb-desc" name="description" maxlength="1000" value="{{ old('description') }}"></div>
