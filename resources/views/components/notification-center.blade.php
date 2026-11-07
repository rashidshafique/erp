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
 *
 * On a tablet the panel is a sheet from the right with large rows, and new notices show as a strip under the header with Open
 * and Later, so keypads and Save buttons are never covered. Opening one while a typed number is unsaved asks first.
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
        session(['notices_others_read' => now()->toIso8601String()]);
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

        $urgent = $unread->filter(fn ($n) => self::group($n) === 'urgent')->count();
        $othersRead = $urgent > 0 && $urgent === $unread->count() && session('notices_others_read')
            ? \Illuminate\Support\Carbon::parse(session('notices_others_read')) : null;

        return [
            'unread' => $unread->count(),
            'urgent' => $urgent,
            'groups' => $groups,
            'read' => $read,
            'othersRead' => $othersRead,
        ];
    }
};
?>

<div class="notice-center" wire:poll.10s="pollForNew"
     x-data="{ toasts: Array(), ask: null,
        push(list) {
            const touch = window.matchMedia('(pointer: coarse)').matches;
            const stays = n => n.tone === 'danger' || n.tone === 'warning';
            const urgent = list.filter(stays), rest = list.filter(n => ! stays(n));
            const shown = rest.length >= 2 ? urgent.concat([{ id: null, folded: true, tone: 'info', title: rest.length + ' new notices', body: rest.map(n => n.title).slice(0, 3).join(' · ') }]) : list;
            shown.forEach(n => { n.key = (n.id || 'fold') + Math.random(); this.toasts.push(n); if (! stays(n)) { setTimeout(() => this.drop(n.key), touch ? 8000 : 6000); } });
            this.toasts = this.toasts.slice(-3);
        },
        drop(key) { this.toasts = this.toasts.filter(t => t.key !== key); if (this.ask === key) { this.ask = null; } },
        unsaved() {
            const fields = document.querySelectorAll('main input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=search]), main textarea');
            return Array.from(fields).find(f => f.value !== f.defaultValue && ! f.closest('[data-no-unsaved]')) || null;
        },
        openToast(t, force) {
            if (! t.folded && ! force && this.unsaved()) { this.ask = t.key; return; }
            if (t.folded) { $wire.open || $wire.toggle(); } else { $wire.openNotice(t.id); }
            this.drop(t.key);
        },
        later(t) { this.drop(t.key); } }"
     x-on:app-toast.window="push($event.detail.notices || Array())"
     x-on:keydown.escape.window="$wire.open && $wire.toggle()"
     x-on:click.outside="$wire.open && $wire.toggle()">
    <style>
        .notice-center { position: relative; display: inline-block; margin-right: 10px; vertical-align: middle; }
        .notice-bell { position: relative; background: none; border: 1px solid transparent; cursor: pointer; padding: 6px; border-radius: 8px; color: inherit; }
        .notice-bell:hover { background: rgba(0,0,0,.05); }
        .notice-badge { position: absolute; top: -3px; right: -4px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: #fff; color: #1c1d1f; border: 1.5px solid #1c1d1f; font-size: 11px; line-height: 15px; text-align: center; font-weight: 600; }
        .notice-badge.is-urgent { background: #111; color: #fff; border-color: #111; }
        .notice-panel { position: absolute; right: 0; top: calc(100% + 6px); z-index: 60; width: 430px; max-height: 75vh; overflow: auto; background: #fff; color: #1c1d1f; border: 1px solid #dfe2e6; border-radius: 10px; box-shadow: 0 12px 32px rgba(0,0,0,.14); }
        .notice-panel header { position: sticky; top: 0; z-index: 1; background: #fff; display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 12px 14px; border-bottom: 1px solid #eceff2; font-weight: 600; font-size: 15px; }
        .notice-panel header small { font-weight: 400; color: #6b7280; }
        .notice-close, .notice-foot { display: none; }
        .notice-group { padding: 12px 14px 6px; font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: #6b7280; }
        .notice-item { display: flex; gap: 12px; align-items: flex-start; width: 100%; text-align: left; background: none; border: 0; border-bottom: 1px solid #f0f2f4; padding: 12px 14px; cursor: pointer; color: inherit; font: inherit; }
        .notice-item:hover { background: #f8f9fa; }
        .notice-item:not(.unread) { opacity: .62; }
        .notice-item.tone-danger.unread { background: #fdf1f0; border: 1px solid #f3c4bf; border-radius: 8px; margin: 2px 10px 8px; width: calc(100% - 20px); }
        .notice-icon { flex: none; width: 22px; height: 22px; border-radius: 6px; display: grid; place-items: center; font-size: 13px; font-weight: 700; margin-top: 1px; border: 1.5px solid currentColor; }
        .tone-danger .notice-icon { background: #c5221f; border-color: #c5221f; color: #fff; }
        .tone-warning .notice-icon { color: #c27c0e; }
        .tone-success .notice-icon { color: #1e8e3e; border-radius: 50%; }
        .tone-info .notice-icon { color: #2f6fdd; border-radius: 50%; font-style: italic; font-family: Georgia, serif; }
        .notice-text { flex: 1; min-width: 0; }
        .notice-text strong { display: block; font-weight: 600; }
        .notice-text small { display: block; color: #4b5563; margin-top: 2px; }
        .notice-link { display: block; margin-top: 4px; color: #2f6fdd; font-size: 13px; }
        .notice-for, .notice-stays { font-size: 12px; color: #6b7280 !important; }
        .notice-meta { flex: none; display: flex; flex-direction: column; align-items: flex-end; gap: 8px; font-size: 12px; color: #6b7280; }
        .notice-dot { width: 7px; height: 7px; border-radius: 50%; background: #2f6fdd; display: block; }
        .notice-empty { padding: 26px 14px 14px; text-align: center; }
        .notice-empty .notice-icon { margin: 0 auto 8px; width: 30px; height: 30px; color: #1e8e3e; border-radius: 50%; }
        .notice-empty strong { display: block; font-size: 15px; }
        .notice-empty small { color: #6b7280; }
        .notice-skeleton { height: 54px; margin: 6px 14px; border-radius: 8px; background: linear-gradient(90deg, #f1f3f5, #f8f9fa, #f1f3f5); }
        .toast-stack { position: fixed; right: 16px; top: 60px; z-index: 100; display: flex; flex-direction: column; gap: 8px; width: 360px; max-width: calc(100vw - 32px); }
        .app-toast { display: flex; gap: 12px; align-items: flex-start; background: #fff; color: #1c1d1f; border: 1px solid #dfe2e6; border-radius: 10px; box-shadow: 0 10px 28px rgba(0,0,0,.16); padding: 12px 12px 12px 14px; }
        .app-toast.tone-danger { border-color: #f3c4bf; background: #fdf1f0; }
        .app-toast .notice-text small { color: #4b5563; }
        .toast-actions { flex: none; display: flex; gap: 6px; align-items: center; }
        .toast-actions button { background: #fff; border: 1px solid #d1d5db; border-radius: 7px; padding: 5px 11px; cursor: pointer; font: inherit; font-size: 13px; color: inherit; }
        .toast-actions .toast-x { border: 0; background: none; font-size: 18px; color: #6b7280; padding: 2px 6px; }
        .toast-later { display: none !important; }
        .toast-ask { display: block; margin-top: 6px; font-size: 13px; font-weight: 600; color: #8a5a00; }
        @media (pointer: coarse) and (max-width: 1366px) {
            .notice-panel { position: fixed; top: 0; right: 0; bottom: 28px; width: min(440px, 100vw); max-height: none; border-radius: 0 0 0 12px; display: flex; flex-direction: column; }
            .notice-panel .notice-body { flex: 1; overflow: auto; }
            .notice-close { display: inline-block; min-width: 48px; min-height: 48px; }
            .notice-mark-top { display: none; }
            .notice-foot { display: block; padding: 10px 14px; border-top: 1px solid #eceff2; }
            .notice-foot button { width: 100%; min-height: 48px; }
            .notice-item { min-height: 64px; padding-top: 14px; padding-bottom: 14px; }
            .toast-stack { top: 56px; left: 0; right: 0; width: auto; max-width: none; gap: 0; }
            .app-toast { border-radius: 0; border-width: 0 0 1px; min-height: 64px; align-items: center; box-shadow: 0 6px 14px rgba(0,0,0,.12); }
            .toast-actions button { min-height: 44px; padding: 0 16px; }
            .toast-later { display: inline-block !important; }
            .toast-actions .toast-x { display: none; }
        }
    </style>

    <button type="button" class="notice-bell" wire:click="toggle" aria-label="Notifications" aria-expanded="{{ $open ? 'true' : 'false' }}">
        {!! \App\Support\NavIcons::get('bell') !!}
        @if ($unread > 0)<span class="notice-badge{{ $urgent > 0 ? ' is-urgent' : '' }}"{!! $urgent > 0 ? ' title="'.(int) $urgent.' urgent"' : '' !!}>{{ $unread > 99 ? '99+' : $unread }}{{ $urgent > 0 ? ' !' : '' }}</span>@endif
    </button>

    @unless ($open)
        <div class="notice-panel" wire:loading.flex wire:target="toggle" aria-hidden="true" style="flex-direction:column">
            <header><span>Notifications{{ $unread > 0 ? ' · '.$unread.' unread' : '' }}</span><small>loading…</small></header>
            @if ($urgent > 0)<div class="notice-group">Urgent · {{ $urgent }}</div><div class="notice-skeleton"></div>@endif
            @if ($unread > $urgent)<div class="notice-group">To do</div><div class="notice-skeleton"></div><div class="notice-skeleton"></div>@endif
        </div>
    @endunless

    @if ($open)
        <div class="notice-panel" role="dialog" aria-label="Notifications">
            <header>
                <span>Notifications{{ $unread > 0 ? ' · '.$unread.' unread' : '' }}</span>
                <span>
                    @if ($unread > $urgent)<button type="button" class="btn btn-sm notice-mark-top" wire:click="markAllRead" title="{{ $urgent > 0 ? 'Urgent notices stay until you open them' : '' }}">Mark all read</button>
                    @elseif ($othersRead)<small>Others read {{ $othersRead->format('H:i') }}</small>@endif
                    <button type="button" class="btn btn-sm notice-close" wire:click="toggle">Close</button>
                </span>
            </header>
            <div class="notice-body">
                @if ($unread === 0)
                    <div class="notice-empty"><span class="notice-icon" aria-hidden="true">✓</span><strong>You're up to date</strong><small>New notices appear here, and pop up as they arrive.</small></div>
                @endif
                @foreach ($groups as $key => $group)
                    <div class="notice-group">{{ $group['label'] }} · {{ $group['notices']->count() }}</div>
                    @foreach ($group['notices'] as $notice)
                        @include('components.notification-row', ['notice' => $notice, 'stays' => $othersRead !== null])
                    @endforeach
                @endforeach
                @if ($read->isNotEmpty())
                    <div class="notice-group">Read · {{ $read->count() }}</div>
                    @foreach ($read as $notice)
                        @include('components.notification-row', ['notice' => $notice])
                    @endforeach
                @endif
            </div>
            @if ($unread > $urgent)
                <div class="notice-foot"><button type="button" class="btn" wire:click="markAllRead">Mark all read</button></div>
            @endif
        </div>
    @endif

    <div class="toast-stack" aria-live="polite">
        <template x-for="toast in toasts" :key="toast.key">
            <div class="app-toast" :class="'tone-' + (toast.tone || 'info')" role="status" x-transition>
                <span class="notice-icon" aria-hidden="true" x-text="toast.tone === 'success' ? '✓' : (toast.tone === 'info' || ! toast.tone ? 'i' : '!')"></span>
                <span class="notice-text">
                    <strong x-text="toast.title"></strong>
                    <small x-text="toast.body"></small>
                    <span class="toast-ask" x-show="ask === toast.key">You typed something that is not saved. Save it first?</span>
                </span>
                <span class="toast-actions">
                    <template x-if="ask !== toast.key">
                        <span class="toast-actions">
                            <button type="button" @click="openToast(toast)" x-text="toast.folded ? 'Open bell' : 'Open'"></button>
                            <button type="button" class="toast-later" @click="later(toast)">Later</button>
                        </span>
                    </template>
                    <template x-if="ask === toast.key">
                        <span class="toast-actions">
                            <button type="button" @click="ask = null">Stay</button>
                            <button type="button" @click="openToast(toast, true)">Open anyway</button>
                        </span>
                    </template>
                    <button type="button" class="toast-x" aria-label="Dismiss" @click="later(toast)">×</button>
                </span>
            </div>
        </template>
    </div>
</div>
