@extends('layouts.app')

@section('title', 'Camera setup')
@section('breadcrumb', 'Cameras / Setup')

@section('content')
<div class="page-heading">
    <div><h1>Camera setup</h1><p>The recorder the pictures come from, and the names of its cameras. The app reads the pictures itself, so the recorder password stays on the server.</p></div>
    <div class="actions"><a class="btn" href="{{ route('cameras.index') }}">Live view</a></div>
</div>

@foreach ($dvrs as $dvr)
<div class="panel">
    <div class="panel-header"><h2>{{ $dvr->name }} <span class="muted small">{{ $dvr->baseUrl() }} · {{ $dvr->cameras->count() }} {{ $dvr->cameras->count() === 1 ? 'camera' : 'cameras' }}</span></h2>
        <span class="status {{ $dvr->active ? 'status-completed' : 'status-cancelled' }}">{{ $dvr->active ? 'ON' : 'OFF' }}</span></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('cameras.dvrs.update', $dvr) }}" class="cs-grid">@csrf @method('PUT')
            <div class="field"><label>Name</label><input name="name" required maxlength="100" value="{{ old('name', $dvr->name) }}"></div>
            <div class="field"><label>Address</label><input name="host" required maxlength="120" value="{{ old('host', $dvr->host) }}" placeholder="192.168.1.108"></div>
            <div class="field"><label>Port</label><input name="port" type="number" min="1" max="65535" required value="{{ old('port', $dvr->port) }}"></div>
            <div class="field"><label>Protocol</label><select name="protocol"><option value="http" @selected($dvr->protocol === 'http')>http</option><option value="https" @selected($dvr->protocol === 'https')>https</option></select></div>
            <div class="field"><label>User name</label><input name="username" required maxlength="100" value="{{ old('username', $dvr->username) }}" autocomplete="off"></div>
            <div class="field"><label>Password</label><input name="password" type="password" maxlength="200" placeholder="Saved. Type to change it" autocomplete="new-password"></div>
            <div class="field cs-wide"><label>Notes</label><input name="notes" maxlength="500" value="{{ old('notes', $dvr->notes) }}"></div>
            <div class="field"><label class="check-field"><input type="checkbox" name="active" value="1" @checked($dvr->active)> Recorder is on</label></div>
            <div class="cs-actions"><button class="btn btn-primary" type="submit">Save recorder</button></div>
        </form>
        <div class="cs-actions" style="margin-top:8px">
            <form method="POST" action="{{ route('cameras.dvrs.test', $dvr) }}">@csrf<button class="btn" type="submit">Test the connection</button></form>
            <form method="POST" action="{{ route('cameras.dvrs.destroy', $dvr) }}" onsubmit="return confirm(@js('Remove '.$dvr->name.' and all its cameras?'))">@csrf @method('DELETE')<button class="btn" type="submit">Remove</button></form>
        </div>

        <h3 style="margin:18px 0 8px;font-size:15px">Cameras</h3>
        <form method="POST" action="{{ route('cameras.channels.store', $dvr) }}" class="cs-actions" style="margin-bottom:10px">@csrf
            <span class="muted small">Add channels</span>
            <input name="from" type="number" min="1" max="128" value="1" style="width:80px" aria-label="First channel"> <span class="muted small">to</span>
            <input name="to" type="number" min="1" max="128" value="{{ max(8, $dvr->cameras->max('channel') ?? 0) }}" style="width:80px" aria-label="Last channel">
            <button class="btn" type="submit">Add</button>
            <span class="muted small">Channels already there are skipped.</span>
        </form>
        @if ($dvr->cameras->isNotEmpty())
        <div class="table-wrap"><table>
            <thead><tr><th>Channel</th><th>Name</th><th>Area</th><th>Order</th><th>Shown</th><th></th></tr></thead>
            <tbody>
            @foreach ($dvr->cameras as $c)
                <tr>
                    <td>{{ $c->channel }}</td>
                    <td><input form="cam-{{ $c->id }}" name="name" required maxlength="100" value="{{ $c->name }}" aria-label="Name"></td>
                    <td><input form="cam-{{ $c->id }}" name="area" maxlength="100" value="{{ $c->area }}" placeholder="e.g. Melting shop" aria-label="Area"></td>
                    <td><input form="cam-{{ $c->id }}" name="sort_order" type="number" min="0" max="9999" value="{{ $c->sort_order }}" style="width:80px" aria-label="Order"></td>
                    <td><input form="cam-{{ $c->id }}" type="checkbox" name="active" value="1" @checked($c->active) style="width:auto;min-height:0" aria-label="Shown"></td>
                    <td class="cs-actions">
                        <form id="cam-{{ $c->id }}" method="POST" action="{{ route('cameras.update', $c) }}">@csrf @method('PUT')<button class="btn btn-small" type="submit">Save</button></form>
                        <form method="POST" action="{{ route('cameras.destroy', $c) }}" onsubmit="return confirm(@js('Remove '.$c->name.'?'))">@csrf @method('DELETE')<button class="btn btn-small" type="submit">Remove</button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @endif
    </div>
</div>
@endforeach

<div class="panel">
    <div class="panel-header"><h2>{{ $dvrs->isEmpty() ? 'Add the recorder' : 'Add another recorder' }}</h2></div>
    <div class="panel-body">
        @if ($errors->any())<div class="alert alert-error">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
        <p class="muted small" style="margin-top:0">For a Dahua recorder, use its web address (the one you open in a browser), usually port 80, and a user that may view live pictures. A read-only user is enough.</p>
        <form method="POST" action="{{ route('cameras.dvrs.store') }}" class="cs-grid">@csrf
            <div class="field"><label>Name</label><input name="name" required maxlength="100" value="{{ old('name') }}" placeholder="Plant recorder"></div>
            <div class="field"><label>Address</label><input name="host" required maxlength="120" value="{{ old('host') }}" placeholder="192.168.1.108"></div>
            <div class="field"><label>Port</label><input name="port" type="number" min="1" max="65535" required value="{{ old('port', 80) }}"></div>
            <div class="field"><label>Protocol</label><select name="protocol"><option value="http">http</option><option value="https" @selected(old('protocol') === 'https')>https</option></select></div>
            <div class="field"><label>User name</label><input name="username" required maxlength="100" value="{{ old('username') }}" autocomplete="off"></div>
            <div class="field"><label>Password</label><input name="password" type="password" required maxlength="200" autocomplete="new-password"></div>
            <div class="cs-actions"><button class="btn btn-primary" type="submit">Add recorder</button></div>
        </form>
    </div>
</div>
<style>
    .cs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px 14px; align-items: end; }
    .cs-grid .field { margin: 0; }
    .cs-grid .cs-wide { grid-column: span 2; }
    .cs-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .cs-actions form { margin: 0; }
</style>
@endsection
