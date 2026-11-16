@props(['count' => 0])
{{-- The Filters menu of a list (design 29d): a funnel icon with the count of filters on as a badge; the labelled selects go inside. --}}
<details class="ls-menu">
    <summary @class(['ls-tool', 'ls-ico', 'on' => $count > 0]) title="Filters" aria-label="Filters"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8L3 5z"/></svg>@if ($count > 0)<span class="ls-badge">{{ $count }}</span>@endif</summary>
    <div class="ls-pop ls-filters">{{ $slot }}</div>
</details>
