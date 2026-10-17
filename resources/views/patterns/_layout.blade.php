{{-- How one revision is moulded: a summary of castings and metal per mould, and the form to set it. $revision, $pattern,
     $assembled (moulds already made with it: a correction then needs a reason, and those moulds keep their castings). --}}
@php
    $metal = app(\App\Services\PatternCavityMap::class)->metalPerMould($revision);
    $levels = $revision->castingLevels();
    $sections = (int) ($revision->layers_per_mould ?? 2);
    $kg = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $mine = old('_layout') === $revision->id;
    $val = fn ($field) => $mine ? old($field) : $revision->{$field};
@endphp
<div class="layout-row">
<div class="layout-fig">@include('patterns._mould-diagram', ['revision' => $revision, 'size' => 'sm'])</div>
<div class="layout-text">
<div class="layout-summary">
    <strong>{{ $revision->arrangementLabel() }}</strong>
    · {{ $sections }} flask {{ $sections === 1 ? 'section' : 'sections' }}
    · {{ $levels }} {{ $levels === 1 ? 'level' : 'levels' }} × {{ $revision->cavityMap->count() }} {{ $revision->cavityMap->count() === 1 ? 'cavity' : 'cavities' }}
    = <strong>{{ $levels * $revision->cavityMap->count() }} {{ $levels * $revision->cavityMap->count() === 1 ? 'casting' : 'castings' }} per mould</strong>
    @if ($metal)
        · metal per mould <strong>{{ $kg($metal['kg']) }} kg</strong>
        @if ($metal['weighed'])<span class="muted">(weighed)</span>@else<span class="muted">(castings {{ $kg($metal['castings_kg']) }} + own gating {{ $kg($metal['own_gating_kg']) }} + runners {{ $kg($metal['runners_kg']) }} + sprue {{ $kg($metal['sprue_kg']) }})</span>@endif
        @if ($metal['yield'] !== null) · yield {{ $metal['yield'] }} %@endif
        @unless ($metal['complete'])<span class="status status-on-hold" title="Gating, runner or sprue weights are missing, so this is the castings only">castings only</span>@endunless
    @endif
</div>
<details class="layout-form" @if ($mine) open @endif>
    <summary>Mould layout</summary>
    @if ($assembled)
        <div class="alert alert-warning" style="margin:0 0 8px">Moulds have been assembled with this layout. Correct it only if it was entered wrongly: those moulds keep the castings they recorded, and the reason is kept in the audit trail. If the tooling itself changed, record a new revision instead.</div>
    @endif
        <form method="POST" action="{{ route('patterns.revisions.layout', [$pattern, $revision]) }}" class="form-grid">
            @csrf @method('PUT')<input type="hidden" name="_layout" value="{{ $revision->id }}">
            <div class="field"><label for="ar-{{ $revision->id }}">Arrangement</label><select id="ar-{{ $revision->id }}" name="mould_arrangement">@foreach (\App\Models\PatternRevision::ARRANGEMENTS as $k => $l)<option value="{{ $k }}" @selected($val('mould_arrangement') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field"><label for="se-{{ $revision->id }}">Flask sections per mould</label><input id="se-{{ $revision->id }}" type="number" name="layers_per_mould" min="1" max="50" value="{{ $val('layers_per_mould') }}"><div class="help">2 for cope and drag; a stack counts every section, bottom and top included.</div></div>
            <div class="field"><label for="lv-{{ $revision->id }}">Casting levels</label><input id="lv-{{ $revision->id }}" type="number" name="casting_levels" min="1" max="50" value="{{ $val('casting_levels') }}" placeholder="sections − 1"><div class="help">Joints holding the layout. Left blank: sections less one.</div></div>
            <div class="field"><label for="cv-{{ $revision->id }}">Cavities on the layout</label><input id="cv-{{ $revision->id }}" type="number" name="cavities_per_mould" min="1" max="10000" value="{{ $val('cavities_per_mould') }}"><div class="help">On one level.</div></div>
            <div class="field"><label for="ru-{{ $revision->id }}">Runners per level (kg)</label><input id="ru-{{ $revision->id }}" type="number" name="runner_weight_per_level_kg" min="0" step="0.001" value="{{ $val('runner_weight_per_level_kg') }}"></div>
            <div class="field"><label for="sp-{{ $revision->id }}">Sprue and basin (kg)</label><input id="sp-{{ $revision->id }}" type="number" name="sprue_weight_kg" min="0" step="0.001" value="{{ $val('sprue_weight_kg') }}"><div class="help">Shared by the whole mould.</div></div>
            <div class="field"><label for="wt-{{ $revision->id }}">Weighed total per mould (kg)</label><input id="wt-{{ $revision->id }}" type="number" name="poured_weight_per_mould_kg" min="0.001" step="0.001" value="{{ $val('poured_weight_per_mould_kg') }}"><div class="help">Optional: the whole tree or stack on the scale. Used instead of the sum.</div></div>
            @include('patterns._flask-sizes-field', ['revision' => $revision, 'mine' => $mine])
            @if ($assembled)<div class="field field-full"><label for="cr-{{ $revision->id }}">Why is the layout being corrected? *</label><input id="cr-{{ $revision->id }}" name="correction_reason" required minlength="5" maxlength="500" value="{{ $mine ? old('correction_reason') : '' }}" placeholder="e.g. Cavities were entered as 4, the plate has 6"></div>@endif
            <div class="field" style="align-self:end"><button class="btn btn-primary" type="submit">{{ $assembled ? 'Correct layout' : 'Save layout' }}</button></div>
        </form>
</details>
</div>
</div>
