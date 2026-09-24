@if ($paginator->hasPages())
    <nav class="row" style="justify-content:center">
        @if ($paginator->onFirstPage())
            <span class="btn btn-ghost btn-sm" aria-disabled="true">قبلی</span>
        @else
            <a class="btn btn-ghost btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">قبلی</a>
        @endif
        <span class="muted small">صفحه {{ fa_digits($paginator->currentPage()) }} از {{ fa_digits($paginator->lastPage()) }}</span>
        @if ($paginator->hasMorePages())
            <a class="btn btn-ghost btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">بعدی</a>
        @else
            <span class="btn btn-ghost btn-sm" aria-disabled="true">بعدی</span>
        @endif
    </nav>
@endif
