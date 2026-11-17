@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-3 text-sm" aria-label="Səhifələmə">
        <p class="text-ink-500">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }}
        </p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn-ghost cursor-not-allowed border border-ink-100 opacity-50">Əvvəlki</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-ghost border border-ink-200">Əvvəlki</a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-ghost border border-ink-200">Növbəti</a>
            @else
                <span class="btn-ghost cursor-not-allowed border border-ink-100 opacity-50">Növbəti</span>
            @endif
        </div>
    </nav>
@endif
