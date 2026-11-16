{{-- The details-panel icon on a plain (non-Livewire) list (design 29d): posts to lists.details, which flips the shared choice and comes back. --}}
@php $details = \App\Support\ListLayout::details(); @endphp
<form method="POST" action="{{ route('lists.details') }}" style="display:inline-flex;margin:0">@csrf
    <button type="submit" class="ls-hide" aria-pressed="{{ $details ? 'true' : 'false' }}" title="{{ $details ? 'Hide' : 'Show' }} details panel" aria-label="{{ $details ? 'Hide' : 'Show' }} details panel"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="14.5" y1="4" x2="14.5" y2="20"/></svg></button>
</form>
