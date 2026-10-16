@php $b = \App\Support\UiSettings::brand(); @endphp
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in — {{ \App\Support\UiSettings::brand()['name'] }}</title>
<style>body{font-family:system-ui,sans-serif;background:#f1f3f5;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
.card{background:#fff;padding:32px;border-radius:10px;width:340px;box-shadow:0 2px 12px #0002}h1{margin:0 0 18px;font-size:20px}
label{display:block;margin:12px 0 4px;font-size:13px}input[type=email],input[type=password]{width:100%;padding:9px;box-sizing:border-box;border:1px solid #bbb;border-radius:6px}
button{margin-top:18px;width:100%;padding:10px;background:{{ \App\Support\UiSettings::theme()['accent'] }};color:#fff;border:0;border-radius:6px;cursor:pointer}.err{color:#b00020;font-size:13px;margin-top:10px}</style></head>
<body><form class="card" method="POST" action="{{ route('login.attempt') }}">@csrf
@if($b['logo'])<img src="{{ $b['logo'] }}" alt="{{ $b['name'] }}" style="display:block;max-width:100%;height:{{ $b['height'] }}px;object-fit:contain;margin-bottom:12px">@endif<h1>{{ $b['name'] }} — sign in</h1>
<label>Email</label><input type="email" name="email" value="{{ old('email') }}" required autofocus>
<label>Password</label><input type="password" name="password" required>
<label><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
@error('email')<div class="err">{{ $message }}</div>@enderror<button type="submit">Sign in</button></form></body></html>
