@if ($paginator->hasPages())
<nav class="post-pagination" aria-label="Paginação de artigos">
    <span class="post-pagination__summary">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>
    <div class="post-pagination__links">
        @if ($paginator->onFirstPage())
            <span class="post-pagination__link is-disabled" aria-disabled="true">Anterior</span>
        @else
            <a class="post-pagination__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="post-pagination__ellipsis" aria-hidden="true">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page === $paginator->currentPage())
                        <span class="post-pagination__link is-current" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="post-pagination__link" href="{{ $url }}" aria-label="Ir para a página {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="post-pagination__link" href="{{ $paginator->nextPageUrl() }}" rel="next">Próxima</a>
        @else
            <span class="post-pagination__link is-disabled" aria-disabled="true">Próxima</span>
        @endif
    </div>
</nav>
@endif
