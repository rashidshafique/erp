@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'New user')
@section('content')
<div class="page-heading"><div><h1>{{ $user->exists ? 'Edit '.$user->name : 'New user' }}</h1></div></div>
<div class="panel"><div class="panel-body"><form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" style="max-width:440px">@csrf @if($user->exists) @method('PUT') @endif
<label>Name</label><input name="name" value="{{ old('name', $user->name) }}" required>
<label>Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required>
<label>{{ $user->exists ? 'New password (leave blank to keep)' : 'Password' }}</label><input type="password" name="password" {{ $user->exists ? '' : 'required' }}>
<label><input type="checkbox" name="must_change_password" value="1" checked> Must change password at next sign-in</label>
@if($user->exists)<label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> Active</label>@else<input type="hidden" name="is_active" value="1">@endif
<fieldset style="margin-top:12px"><legend>Roles</legend>@foreach($roles as $r)<label style="display:block"><input type="checkbox" name="roles[]" value="{{ $r->id }}" @checked(in_array($r->id, old('roles', $user->roles->pluck('id')->all())))> {{ $r->name }}</label>@endforeach</fieldset>
<p><button class="btn btn-primary">Save</button> <a href="{{ route('users.index') }}">Cancel</a></p></form></div></div>
@endsection
