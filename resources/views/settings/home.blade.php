@extends('layouts.app')
@section('title', 'Settings')
@section('breadcrumb', 'Settings')
@section('content')
<div class="page-heading"><div><h1>Settings</h1><p>Every area with its pages. Pick one to change it.</p></div></div>

@unless ($loginRequired)
<div class="panel"><div class="panel-body"><strong>Sign-in is off.</strong> Anyone at the screen can change every setting. Your developer can turn on <code>FOUNDRY_REQUIRE_LOGIN</code> so these pages use roles and permissions.</div></div>
@endunless

@foreach ($sections as $section => $links)
<div class="panel">
    <div class="panel-header"><h2>{{ $section }}</h2></div>
    <div class="table-wrap"><table>
        <tbody>
        @foreach ($links as $label => [$route])
            <tr>
                <td><a href="{{ route($route) }}"><strong>{{ $label }}</strong></a></td>
                <td class="muted">{{ $purpose[$label] ?? '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endforeach

<div class="panel">
    <div class="panel-header"><h2>Set by your developer</h2><span class="muted small">Read-only here. Changed in the server config.</span></div>
    <div class="table-wrap"><table>
        <tbody>
        @foreach ($developer as $label => $value)
            <tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endsection
