@extends('layouts.app')
@section('title', 'Account')
@section('content')
@php($me = auth()->user())
<div class="page-heading"><div><h1>Account</h1><p>Your profile picture and password.</p></div></div>
<div class="panel"><div class="panel-header"><h2>Profile picture</h2></div><div class="panel-body"><form method="POST" action="{{ route('account.profile.update') }}" enctype="multipart/form-data" style="max-width:380px">@csrf @method('PUT')
<p>@include('partials.avatar', ['user' => $me, 'size' => 64])</p>
<label>Upload an image (JPG, PNG, GIF or WebP, up to 2 MB)</label><input type="file" name="profile_image" accept="image/*">
@error('profile_image')<p class="field-error" style="color:var(--danger)">{{ $message }}</p>@enderror
@if($me->profile_image_path)<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="remove_image" value="1"> Remove my uploaded image</label>@endif
<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="use_gravatar" value="1" @checked(old('use_gravatar', $me->use_gravatar))> Use my Gravatar when no image is uploaded</label>
<p class="muted small">Gravatar is looked up from your email address on gravatar.com. If none exists, your initials are shown.</p>
<p><button class="btn btn-primary">Save profile</button></p></form></div></div>
<div class="page-heading"><div><h1>Change password</h1><p>At least 10 characters with letters and numbers.</p></div></div>
<div class="panel"><div class="panel-body"><form method="POST" action="{{ route('account.password.update') }}" style="max-width:380px">@csrf @method('PUT')
<label>Current password</label><input type="password" name="current_password" required>
<label>New password</label><input type="password" name="password" required>
<label>Repeat new password</label><input type="password" name="password_confirmation" required>
<p><button class="btn btn-primary">Change password</button></p></form></div></div>
@endsection
