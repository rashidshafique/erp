@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="pager-item disabled">‹</span>
        @else
            <a class="pager-item" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹</a>
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
                        <a class="pager-item" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="pager-item" href="{{ $paginator->nextPageUrl() }}" rel="next">›</a>
        @else
            <span class="pager-item disabled">›</span>
        @endif
    </nav>
@endif
