@extends('layouts.app')

@section('title', 'How you work')
@section('breadcrumb', 'Settings / How you work')

@section('content')
<div class="page-heading">
    <div><h1>How you work</h1><p>A few questions about your foundry. Your answers set the process rules, which you can still change one by one on the <a href="{{ route('settings.process-depth.edit') }}">process depth</a> page.</p></div>
</div>
@if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

<form method="POST" action="{{ $changes === null ? route('settings.process-setup.review') : route('settings.process-setup') }}">
    @csrf @if ($changes !== null) @method('PUT') @endif
    <div class="panel"><div class="panel-body">
        <div class="rule-grid">
            @foreach ($questions as $key => [$question, $options])
                <fieldset>
                    <legend>{{ $question }}</legend>
                    @foreach ($options as $value => [$label])
                        <label><input type="radio" name="{{ $key }}" value="{{ $value }}" @checked(old($key, $answers[$key] ?? null) === $value) required> {{ $label }}</label>
                    @endforeach
                </fieldset>
            @endforeach
        </div>
    </div></div>

    @if ($changes !== null)
        <div class="panel">
            <div class="panel-header"><h2>What your answers set</h2><span class="muted small">{{ collect($changes)->where('changes', true)->count() }} of {{ count($changes) }} rules change</span></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Rule</th><th>Now</th><th>After</th></tr></thead>
                <tbody>@foreach ($changes as $change)
                    <tr @if ($change['changes']) style="font-weight:600" @endif><td>{{ $change['label'] }}</td><td>{{ $change['now'] }}</td><td>{{ $change['then'] }}</td></tr>
                @endforeach</tbody>
            </table></div>
            <div class="panel-body muted small">Change any of these later on the process depth page.</div>
        </div>
        <div class="actions"><button class="btn btn-primary" type="submit">Set these rules</button><a class="btn" href="{{ route('settings.process-setup.edit') }}">Start again</a></div>
    @else
        <div class="actions"><button class="btn btn-primary" type="submit">See what this sets</button></div>
    @endif
</form>
@endsection
