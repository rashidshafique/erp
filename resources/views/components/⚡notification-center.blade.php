<?php

use Illuminate\Notifications\DatabaseNotification;
use Livewire\Component;

/**
 * The bell in the top bar and the pop-up toasts. It polls every few seconds: the badge and list follow the signed-in user's
 * unread notices, and any notice that arrived since the last poll is also pushed as a toast. The place the last toast was
 * shown up to is kept in the session, so moving between pages neither repeats a toast nor drops one.
 */
new class extends Component
{
    public bool $open = false;

    public function mount(): void
    {
        if (! session()->has('notice_cursor')) {
            session(['notice_cursor' => now()->toIso8601String()]);
        }
    }

    /** Pushes the notices that arrived since the last poll as toasts. */
    public function pollForNew(): void
    {
        $cursor = \Illuminate\Support\Carbon::parse(session('notice_cursor', now()));
        $fresh = auth()->user()->unreadNotifications()->where('created_at', '>', $cursor)->oldest()->limit(5)->get();

        if ($fresh->isNotEmpty()) {
            session(['notice_cursor' => $fresh->last()->created_at->toIso8601String()]);
            $this->dispatch('app-toast', notices: $fresh->map(fn (DatabaseNotification $n) => ['id' => $n->id] + $n->data)->all());
        }
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    /** Marks the notice read and goes to the page it points at. */
    public function openNotice(string $id): void
    {
        $notice = auth()->user()->notifications()->findOrFail($id);
        $notice->markAsRead();

        if ($notice->data['url'] ?? null) {
            $this->redirect($notice->data['url']);
        }
    }

    public function markRead(string $id): void
    {
        auth()->user()->unreadNotifications()->whereKey($id)->first()?->markAsRead();
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $user = auth()->user();

        return [
            'unread' => $user->unreadNotifications()->count(),
            'notices' => $user->notifications()->latest()->limit(12)->get(),
        ];
    }
};
?>

<div class="notice-center" wire:poll.10s="pollForNew"
     x-data="{ toasts: Array(), push(list) { list.forEach(n => { n.key = n.id + Math.random(); this.toasts.push(n); setTimeout(() => this.drop(n.key), 9000); }); this.toasts = this.toasts.slice(-4); }, drop(key) { this.toasts = this.toasts.filter(t => t.key !== key); } }"
     x-on:app-toast.window="push($event.detail.notices || Array())"
     x-on:click.outside="$wire.open && $wire.toggle()">
    <style>
        .notice-center { position: relative; display: inline-block; margin-right: 10px; vertical-align: middle; }
        .notice-bell { position: relative; background: none; border: 0; cursor: pointer; padding: 6px; border-radius: 8px; color: inherit; }
        .notice-bell:hover { background: rgba(0,0,0,.06); }
        .notice-badge { position: absolute; top: 0; right: 0; min-width: 17px; height: 17px; padding: 0 4px; border-radius: 9px; background: #d93025; color: #fff; font-size: 11px; line-height: 17px; text-align: center; font-weight: 600; }
        .notice-panel { position: absolute; right: 0; top: 100%; z-index: 60; width: 340px; max-height: 70vh; overflow: auto; background: #fff; color: #222; border: 1px solid #d8dde3; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,.18); }
        .notice-panel header { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; border-bottom: 1px solid #eceff2; font-weight: 600; }
        .notice-item { display: block; width: 100%; text-align: left; background: none; border: 0; border-bottom: 1px solid #f0f2f4; padding: 10px 12px 10px 16px; cursor: pointer; border-left: 4px solid #8a94a0; }
        .notice-item.unread { background: #f6faf8; }
        .notice-item small { display: block; color: #667; margin-top: 2px; }
        .notice-item.tone-danger, .app-toast.tone-danger { border-left-color: #d93025; }
        .notice-item.tone-warning, .app-toast.tone-warning { border-left-color: #e8a317; }
        .notice-item.tone-success, .app-toast.tone-success { border-left-color: #24945a; }
        .notice-item.tone-info, .app-toast.tone-info { border-left-color: #2f6fdd; }
        .toast-stack { position: fixed; right: 18px; bottom: 18px; z-index: 100; display: flex; flex-direction: column; gap: 10px; width: 330px; max-width: calc(100vw - 36px); }
        .app-toast { background: #fff; color: #222; border: 1px solid #d8dde3; border-left-width: 5px; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,.2); padding: 11px 34px 11px 13px; position: relative; cursor: pointer; }
        .app-toast small { display: block; color: #556; margin-top: 3px; }
        .app-toast button { position: absolute; top: 6px; right: 8px; background: none; border: 0; font-size: 16px; cursor: pointer; color: #889; }
    </style>

    <button type="button" class="notice-bell" wire:click="toggle" aria-label="Notifications" aria-expanded="{{ $open ? 'true' : 'false' }}">
        {!! \App\Support\NavIcons::get('bell') !!}
        @if ($unread > 0)<span class="notice-badge">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
    </button>

    @if ($open)
        <div class="notice-panel" role="dialog" aria-label="Notifications">
            <header><span>Notifications</span>@if ($unread > 0)<button type="button" class="btn btn-sm" wire:click="markAllRead">Mark all read</button>@endif</header>
            @forelse ($notices as $notice)
                <button type="button" class="notice-item tone-{{ $notice->data['tone'] ?? 'info' }} {{ $notice->read_at ? '' : 'unread' }}" wire:key="notice-{{ $notice->id }}" wire:click="openNotice('{{ $notice->id }}')">
                    <strong>{{ $notice->data['title'] ?? '' }}</strong>
                    <small>{{ $notice->data['body'] ?? '' }}</small>
                    <small>{{ $notice->created_at->diffForHumans() }}</small>
                </button>
            @empty
                <p class="muted" style="padding:14px">Nothing to tell you yet.</p>
            @endforelse
        </div>
    @endif

    <div class="toast-stack" aria-live="polite">
        <template x-for="toast in toasts" :key="toast.key">
            <div class="app-toast" :class="'tone-' + (toast.tone || 'info')" role="status" x-transition
                 @click="$wire.openNotice(toast.id); drop(toast.key)">
                <strong x-text="toast.title"></strong>
                <small x-text="toast.body"></small>
                <button type="button" aria-label="Dismiss" @click.stop="$wire.markRead(toast.id); drop(toast.key)">×</button>
            </div>
        </template>
    </div>
</div>
