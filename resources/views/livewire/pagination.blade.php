{{-- The pager of the Livewire lists: the same look as vendor.pagination.minimal, with buttons that change the page without a reload. --}}
@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="pager-item disabled">‹</span>
        @else
            <button type="button" class="pager-item" wire:click="previousPage('{{ $paginator->getPageName() }}')" rel="prev" aria-label="Previous page">‹</button>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="pager-item disabled">…</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="pager-item current" aria-current="page">{{ $page }}</span>
                    @else
                        <button type="button" class="pager-item" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" wire:key="page-{{ $page }}">{{ $page }}</button>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <button type="button" class="pager-item" wire:click="nextPage('{{ $paginator->getPageName() }}')" rel="next" aria-label="Next page">›</button>
        @else
            <span class="pager-item disabled">›</span>
        @endif
    </nav>
@endif
