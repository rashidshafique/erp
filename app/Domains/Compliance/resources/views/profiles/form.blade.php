@extends('layouts.app')

@section('title', $profile->exists ? 'Edit '.$profile->name : 'Add profile')
@section('breadcrumb')<a href="{{ route('requirements.index') }}">Requirement profiles</a> / {{ $profile->exists ? $profile->name : 'New' }} @endsection

@section('content')
<div class="page-heading"><div><h1>{{ $profile->exists ? 'Edit '.$profile->name : 'Add profile' }}</h1></div><div class="actions"><a class="btn" href="{{ route('requirements.index') }}">Cancel</a></div></div>
@if ($errors->any())<div class="alert alert-error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ $profile->exists ? route('requirements.update', $profile) : route('requirements.store') }}">
    @csrf @if ($profile->exists) @method('PUT') @endif
    <div class="panel"><div class="panel-body"><div class="form-grid">
        <div class="field"><label for="name">Name *</label><input id="name" name="name" required maxlength="150" value="{{ old('name', $profile->name) }}"></div>
        @unless ($profile->is_system)<div class="field"><label for="code">Code *</label><input id="code" name="code" required maxlength="40" value="{{ old('code', $profile->code) }}" style="text-transform:uppercase"></div>@endunless
        <div class="field"><label for="region">Region</label><select id="region" name="region">@foreach (\App\Domains\Compliance\Models\RequirementProfile::REGIONS as $k => $l)<option value="{{ $k }}" @selected(old('region', $profile->region) === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label><input type="checkbox" name="active" value="1" @checked(old('active', $profile->active))> Active</label></div>
        <div class="field field-full"><label for="description">Description</label><textarea id="description" name="description" rows="2" maxlength="2000">{{ old('description', $profile->description) }}</textarea></div>
    </div></div></div>
    <div class="panel"><div class="panel-header"><h2>Requirements</h2></div><div class="panel-body"><div class="form-grid">
        @foreach (\App\Domains\Compliance\Models\RequirementProfile::SETTINGS as $key => [$label, $type, $options, $help])
            @php $value = old("settings.$key", $profile->value($key)); @endphp
            <div class="field">
                @if ($type === 'bool')
                    <label><input type="hidden" name="settings[{{ $key }}]" value="0"><input type="checkbox" name="settings[{{ $key }}]" value="1" @checked($value)> {{ $label }}</label>
                @elseif ($type === 'rank')
                    <label for="s_{{ $key }}">{{ $label }}</label><select id="s_{{ $key }}" name="settings[{{ $key }}]">@foreach ($options as $k => $l)<option value="{{ $k }}" @selected($value === $k)>{{ $l }}</option>@endforeach</select>
                @else
                    <label for="s_{{ $key }}">{{ $label }}</label><input id="s_{{ $key }}" type="number" step="0.01" min="0" name="settings[{{ $key }}]" value="{{ $value }}">
                @endif
                @if ($help)<div class="help">{{ $help }}</div>@endif
            </div>
        @endforeach
    </div></div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Save profile</button></div>
</form>
@endsection
