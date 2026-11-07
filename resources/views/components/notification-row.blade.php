@php
    $data = $notice->data;
    $tone = in_array($data['tone'] ?? 'info', \App\Notifications\AppNotice::TONES, true) ? $data['tone'] : 'info';
    $icon = ['danger' => '!', 'warning' => '!', 'success' => '✓', 'info' => 'i'][$tone];
    $link = \App\Notifications\AppNotice::linkLabel($data);
    $at = $notice->created_at;
    $mins = (int) $at->diffInMinutes(now());
    $age = match (true) {
        $mins < 1 => 'now',
        $mins < 60 => $mins.' min',
        $mins < 360 => intdiv($mins, 60).' h',
        $at->isToday() => $at->format('H:i'),
        $at->gt(now()->subDays(6)->startOfDay()) => $at->format('D'),
        default => $at->format('j M'),
    };
@endphp
<button type="button" class="notice-item tone-{{ $tone }}{{ $notice->read_at ? '' : ' unread' }}" wire:key="notice-{{ $notice->id }}" wire:click="openNotice('{{ $notice->id }}')">
    <span class="notice-icon" aria-hidden="true">{{ $icon }}</span>
    <span class="notice-text">
        <strong>{{ $data['title'] ?? '' }}</strong>
        @if (($data['body'] ?? '') !== '')<small>{{ $data['body'] }}</small>@endif
        @if ($link)<span class="notice-link">→ {{ $link }}</span>@endif
        @if (! empty($data['for']))<small class="notice-for">For you as: {{ implode(' · ', $data['for']) }}</small>@endif
        @if (($stays ?? false) && ! $notice->read_at && $tone === 'danger')
            <small class="notice-stays">Stays until opened{{ ($data['kind'] ?? '') === 'charge-overdue' ? ' or the charge goes in' : '' }}</small>
        @endif
    </span>
    <span class="notice-meta"><time datetime="{{ $at->toIso8601String() }}" title="{{ $at->displayShortDateTime() }}">{{ $age }}</time>@unless ($notice->read_at)<i class="notice-dot" aria-label="unread"></i>@endunless</span>
</button>
