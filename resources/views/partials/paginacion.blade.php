@if ($p->hasPages())
<div class="mt-4 flex items-center justify-between text-sm">
    <span class="text-gray-500">Página {{ $p->currentPage() }} de {{ $p->lastPage() }} · {{ $p->total() }} registros</span>
    <div class="flex gap-2">
        @if ($p->onFirstPage())
            <span class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-300">‹ Anterior</span>
        @else
            <a href="{{ $p->previousPageUrl() }}" class="px-3 py-1.5 rounded-lg border border-gray-300 text-azul-800 hover:border-azul-800">‹ Anterior</a>
        @endif
        @if ($p->hasMorePages())
            <a href="{{ $p->nextPageUrl() }}" class="px-3 py-1.5 rounded-lg border border-gray-300 text-azul-800 hover:border-azul-800">Siguiente ›</a>
        @else
            <span class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-300">Siguiente ›</span>
        @endif
    </div>
</div>
@endif
