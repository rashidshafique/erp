@extends('layouts.app')

@section('title', 'Requirement profiles')
@section('breadcrumb', 'Requirement profiles')

@section('content')
<div class="page-heading">
    <div><h1>Requirement profiles</h1><p>What each customer or standard demands. A customer holds one or more profiles and the strictest value applies. Values shown are starting points; edit them to match the customer's supplier manual.</p></div>
    <div class="actions"><a class="btn btn-primary" href="{{ route('requirements.create') }}">+ Add profile</a></div>
</div>
<div class="panel"><div class="table-wrap"><table>
    <thead><tr><th>Profile</th><th>Region</th><th>PPAP</th><th>Cpk / Ppk</th><th>Certificate</th><th>Customers</th><th></th></tr></thead>
    <tbody>
    @foreach ($profiles as $profile)
        <tr @unless ($profile->active) class="muted" @endunless>
            <td><strong>{{ $profile->name }}</strong> <span class="mono muted small">{{ $profile->code }}</span><div class="muted small">{{ \Illuminate\Support\Str::limit($profile->description, 110) }}</div></td>
            <td>{{ $regions[$profile->region] }}</td>
            <td>{{ $profile->value('ppap_level') ?: '—' }}</td>
            <td>{{ $profile->value('cpk_ongoing') ?: '—' }} / {{ $profile->value('ppk_initial') ?: '—' }}</td>
            <td>{{ \App\Domains\Compliance\Models\RequirementProfile::CERTIFICATES[$profile->value('delivery_certificate')] }}</td>
            <td>{{ $profile->customers_count }}</td>
            <td><a href="{{ route('requirements.edit', $profile) }}">Edit</a></td>
        </tr>
    @endforeach
    </tbody>
</table></div></div>
@endsection
