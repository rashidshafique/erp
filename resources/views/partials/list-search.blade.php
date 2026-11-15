{{-- The search box of a 28c list. $placeholder; $model (default 'search'). --}}
<label class="ls-search">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#55585d" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
    <input type="search" wire:model.live.debounce.300ms="{{ $model ?? 'search' }}" placeholder="{{ $placeholder }}" autocomplete="off" aria-label="{{ $placeholder }}">
</label>
