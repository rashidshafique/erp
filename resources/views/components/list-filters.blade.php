@props(['count' => 0])
{{-- The Filters menu of a 28c list: <x-list-filters :count="n"> with the labelled selects inside. --}}
<details class="ls-menu">
    <summary @class(['ls-tool', 'on' => $count > 0])><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8L3 5z"/></svg>Filters @if ($count > 0) <b>{{ $count }}</b>@endif</summary>
    <div class="ls-pop ls-filters">{{ $slot }}</div>
</details>
