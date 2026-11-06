<?php

use Illuminate\Notifications\DatabaseNotification;
use Livewire\Component;

/**
 * The bell in the top bar and the pop-up toasts. It polls every few seconds: the badge and list follow the signed-in user's
 * unread notices, and any notice that arrived since the last poll is also pushed as a toast. The place the last toast was
 * shown up to is kept in the session, so moving between pages neither repeats a toast nor drops one.
 *
 * Notices sit in three groups by tone: Urgent (danger), To do (warning) and For information (info, success). While an urgent
 * notice is unread the badge turns dark with a "!". Mark all read leaves urgent notices alone: they have to be opened, or they
 * settle themselves when the work is done (a late charge goes in).
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

    /** Marks every unread notice read except the urgent ones. */
    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->reject(fn (DatabaseNotification $n) => self::group($n) === 'urgent')->each->markAsRead();
    }

    /** The group a notice sits in: urgent, todo or info. */
    public static function group(DatabaseNotification $notice): string
    {
        return match ($notice->data['tone'] ?? 'info') {
            'danger' => 'urgent',
            'warning' => 'todo',
            default => 'info',
        };
    }

    /** @return array<string, mixed> */
    public function with(): array
    {
        $user = auth()->user();
        $unread = $user->unreadNotifications()->latest()->get();
        $read = $user->readNotifications()->latest()->limit(max(0, 12 - $unread->count()))->get();
        $groups = collect(['urgent' => 'Urgent', 'todo' => 'To do', 'info' => 'For information'])
            ->map(fn (string $label, string $key) => ['label' => $label, 'notices' => $unread->filter(fn ($n) => self::group($n) === $key)->values()])
            ->filter(fn (array $g) => $g['notices']->isNotEmpty());

        return [
            'unread' => $unread->count(),
            'urgent' => $unread->filter(fn ($n) => self::group($n) === 'urgent')->count(),
            'groups' => $groups,
            'read' => $read,
        ];
    }
};
?>

<div class="notice-center" wire:poll.10s="pollForNew"
     x-data="{ toasts: Array(),
        push(list) {
            const touch = window.matchMedia('(pointer: coarse)').matches;
            const stays = n => n.tone === 'danger' || n.tone === 'warning';
            const urgent = list.filter(stays), rest = list.filter(n => ! stays(n));
            const shown = rest.length > 2 ? urgent.concat([{ id: null, folded: true, tone: 'info', title: rest.length + ' new notices', body: rest.map(n => n.title).slice(0, 3).join(' · ') }]) : list;
            shown.forEach(n => { n.key = (n.id || 'fold') + Math.random(); this.toasts.push(n); if (! stays(n)) { setTimeout(() => this.drop(n.key), touch ? 8000 : 6000); } });
            this.toasts = this.toasts.slice(-4);
        },
        drop(key) { this.toasts = this.toasts.filter(t => t.key !== key); },
        tap(t) { if (t.folded) { $wire.open || $wire.toggle(); } else { $wire.openNotice(t.id); } this.drop(t.key); } }"
     x-on:app-toast.window="push($event.detail.notices || Array())"
     x-on:click.outside="$wire.open && $wire.toggle()">
    <style>
        .notice-center { position: relative; display: inline-block; margin-right: 10px; vertical-align: middle; }
        .notice-bell { position: relative; background: none; border: 0; cursor: pointer; padding: 6px; border-radius: 8px; color: inherit; }
        .notice-bell:hover { background: rgba(0,0,0,.06); }
        .notice-badge.is-urgent { background: #111; }
        .notice-badge { position: absolute; top: 0; right: 0; min-width: 17px; height: 17px; padding: 0 4px; border-radius: 9px; background: #d93025; color: #fff; font-size: 11px; line-height: 17px; text-align: center; font-weight: 600; }
        .notice-panel { position: absolute; right: 0; top: 100%; z-index: 60; width: 340px; max-height: 70vh; overflow: auto; background: #fff; color: #222; border: 1px solid #d8dde3; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,.18); }
        .notice-panel header { display: flex; justify-content: space-between; align-items: center; padding: 10px 12px; border-bottom: 1px solid #eceff2; font-weight: 600; }
        .notice-group { padding: 8px 12px 4px; font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #667; }
        .notice-group.is-urgent { color: #b3261e; }
        .notice-item.tone-danger.unread { background: #fdf1f0; font-weight: 500; }
        .notice-empty { padding: 14px 12px 6px; }
        .notice-empty strong { display: block; }
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
        @media (pointer: coarse) and (max-width: 1366px) {
            .notice-item { padding-top: 14px; padding-bottom: 14px; }
            .toast-stack { top: 0; bottom: auto; left: 0; right: 0; width: auto; max-width: none; gap: 0; }
            .app-toast { border-radius: 0; border-width: 0 0 1px 6px; min-height: 52px; box-shadow: 0 4px 12px rgba(0,0,0,.15); }
        }
        .app-toast button { position: absolute; top: 6px; right: 8px; background: none; border: 0; font-size: 16px; cursor: pointer; color: #889; }
    </style>

    <button type="button" class="notice-bell" wire:click="toggle" aria-label="Notifications" aria-expanded="{{ $open ? 'true' : 'false' }}">
        {!! \App\Support\NavIcons::get('bell') !!}
        @if ($unread > 0)<span class="notice-badge{{ $urgent > 0 ? ' is-urgent' : '' }}"{!! $urgent > 0 ? ' title="'.(int) $urgent.' urgent"' : '' !!}>{{ $unread > 99 ? '99+' : $unread }}{{ $urgent > 0 ? ' !' : '' }}</span>@endif
    </button>

    @if ($open)
        <div class="notice-panel" role="dialog" aria-label="Notifications">
            <header><span>Notifications{{ $unread > 0 ? ' · '.$unread.' unread' : '' }}</span>@if ($unread > $urgent)<button type="button" class="btn btn-sm" wire:click="markAllRead" title="{{ $urgent > 0 ? 'Urgent notices stay until you open them' : '' }}">Mark all read</button>@endif</header>
            @if ($unread === 0)
                <div class="notice-empty"><strong>You're up to date</strong><small class="muted">New notices show here and pop up as they arrive.</small></div>
            @endif
            @foreach ($groups as $key => $group)
                <div class="notice-group {{ $key === 'urgent' ? 'is-urgent' : '' }}">{{ $group['label'] }} · {{ $group['notices']->count() }}</div>
                @foreach ($group['notices'] as $notice)
                    @include('components.notification-row', ['notice' => $notice])
                @endforeach
            @endforeach
            @if ($read->isNotEmpty())
                <div class="notice-group">Earlier</div>
                @foreach ($read as $notice)
                    @include('components.notification-row', ['notice' => $notice])
                @endforeach
            @endif
        </div>
    @endif

    <div class="toast-stack" aria-live="polite">
        <template x-for="toast in toasts" :key="toast.key">
            <div class="app-toast" :class="'tone-' + (toast.tone || 'info')" role="status" x-transition
                 @click="tap(toast)">
                <strong x-text="toast.title"></strong>
                <small x-text="toast.body"></small>
                <button type="button" aria-label="Dismiss" @click.stop="toast.id && $wire.markRead(toast.id); drop(toast.key)">×</button>
            </div>
        </template>
    </div>
</div>
