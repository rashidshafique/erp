@extends('layouts.app')
@section('title', 'Change password')
@section('content')
<div class="page-heading"><div><h1>Change password</h1><p>At least 10 characters with letters and numbers.</p></div></div>
<div class="panel"><div class="panel-body"><form method="POST" action="{{ route('account.password.update') }}" style="max-width:380px">@csrf @method('PUT')
<label>Current password</label><input type="password" name="current_password" required>
<label>New password</label><input type="password" name="password" required>
<label>Repeat new password</label><input type="password" name="password_confirmation" required>
<p><button class="btn btn-primary">Change password</button></p></form></div></div>
@endsection
