{{-- Insignia de cuenta verificada. Uso: @include('partials.verificado', ['tam' => 18, 'texto' => false]) --}}
@php
    $tam = $tam ?? 18;
    $texto = $texto ?? false;
    $titulo = $titulo ?? 'Cuenta verificada';
@endphp
<span class="inline-flex items-center gap-1 align-middle shrink-0" title="{{ $titulo }}" aria-label="{{ $titulo }}">
    <svg width="{{ $tam }}" height="{{ $tam }}" viewBox="0 0 24 24" aria-hidden="true" style="display:block">
        <path fill="#1D9BF0" d="M22.5 12.5c0-1.58-.875-2.95-2.148-3.6.154-.435.238-.905.238-1.4 0-2.21-1.71-3.998-3.818-3.998-.47 0-.92.084-1.336.25C14.818 2.415 13.51 1.5 12 1.5s-2.816.917-3.437 2.25c-.415-.165-.866-.25-1.336-.25-2.11 0-3.818 1.79-3.818 4 0 .494.083.964.237 1.4-1.272.65-2.147 2.018-2.147 3.6 0 1.495.782 2.798 1.942 3.486-.02.17-.032.34-.032.514 0 2.21 1.708 4 3.818 4 .47 0 .92-.086 1.335-.25.62 1.334 1.926 2.25 3.437 2.25 1.512 0 2.818-.916 3.437-2.25.415.163.865.248 1.336.248 2.11 0 3.818-1.79 3.818-4 0-.174-.012-.344-.033-.513 1.158-.687 1.943-1.99 1.943-3.484z"/>
        <path fill="#fff" d="M10.54 16.48l-3.73-3.73 1.41-1.41 2.32 2.32 5.32-5.32 1.41 1.41z"/>
    </svg>
    @if($texto)
        <span class="text-xs font-semibold text-[#1D9BF0]">Verificado</span>
    @endif
</span>
