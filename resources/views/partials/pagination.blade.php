@if($paginator->hasPages())
    <nav class="actions" aria-label="Pagination" style="justify-content:center">
        @if($paginator->onFirstPage())
            <span class="btn ghost sm" aria-disabled="true" style="opacity:.4">← Précédent</span>
        @else
            <a class="btn ghost sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Précédent</a>
        @endif
        <span class="muted">Page {{ $paginator->currentPage() }} sur {{ $paginator->lastPage() }}</span>
        @if($paginator->hasMorePages())
            <a class="btn ghost sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Suivant →</a>
        @else
            <span class="btn ghost sm" aria-disabled="true" style="opacity:.4">Suivant →</span>
        @endif
    </nav>
@endif
