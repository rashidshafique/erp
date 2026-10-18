@extends('layouts.app')
@section('title', 'Users')
@section('content')
<div class="page-heading"><div><h1>Users</h1><p>Login is {{ config('foundry.require_login') ? 'ON' : 'OFF (set FOUNDRY_REQUIRE_LOGIN=true to enforce)' }}.</p></div><a class="btn btn-primary" href="{{ route('users.create') }}">+ New user</a></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Roles</th><th>Last login</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($users as $u)<tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td style="max-width:300px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis" title="{{ $u->roles->pluck('name')->join(', ') }}">{{ $u->roles->pluck('name')->join(', ') ?: '—' }}</td><td>{{ $u->last_login_at?->format('d M Y H:i') ?? '—' }}</td><td>{{ $u->is_active ? 'Active' : 'Switched off' }}</td><td>@include('partials.icon-button', ['type' => 'edit', 'name' => $u->name, 'href' => route('users.edit', $u)])</td></tr>
@empty<tr><td colspan="6" class="muted">No users yet. Run <code>php artisan foundry:make-admin you@example.com</code>.</td></tr>@endforelse</tbody></table></div></div>
@endsection
