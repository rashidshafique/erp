@extends('layouts.app')

@section('title', 'Requirements · '.$customer->name)
@section('breadcrumb')<a href="{{ route('customers.index') }}">Customers</a> / {{ $customer->code }} / Requirements @endsection

@section('content')
<div class="page-heading"><div><h1>{{ $customer->name }}</h1><p>Which requirement profiles apply to this customer. Where profiles differ, the strictest value applies.</p></div></div>
<form method="POST" action="{{ route('customer-requirements.update', $customer) }}">
    @csrf @method('PUT')
    <div class="panel"><div class="panel-header"><h2>Profiles</h2></div><div class="panel-body">
        @foreach ($profiles as $profile)
            <p><label><input type="checkbox" name="profiles[]" value="{{ $profile->id }}" @checked(in_array($profile->id, old('profiles', $held), true))> <strong>{{ $profile->name }}</strong></label> <span class="muted small">{{ \Illuminate\Support\Str::limit($profile->description, 120) }}</span></p>
        @endforeach
    </div></div>
    <div class="actions"><button class="btn btn-primary" type="submit">Save</button></div>
</form>
<div class="panel"><div class="panel-header"><h2>What applies today</h2></div><div class="panel-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
    @foreach (\App\Domains\Compliance\Models\RequirementProfile::SETTINGS as $key => [$label, $type, $options])
        @php $v = $merged[$key]; @endphp
        <div><div class="muted small">{{ $label }}</div><strong>{{ $type === 'bool' ? ($v ? 'Yes' : 'No') : ($type === 'rank' ? $options[$v] : ($v ?: '—')) }}</strong></div>
    @endforeach
</div></div>
@endsection
