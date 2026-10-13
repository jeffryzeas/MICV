{{--
    Barra fija abajo con Anterior / Siguiente / Guardar (va DENTRO del <form>).
    Uso: @include('partials.barra-guardar', ['texto' => 'Guardar mis datos'])
--}}
<div data-barra class="sticky bottom-0 z-30 -mx-4 sm:-mx-6 -mb-4 sm:-mb-6 mt-6 bg-white/95 dark:bg-gray-900/95 backdrop-blur border-t border-gray-200 px-3 sm:px-5 py-2.5 rounded-b-xl shadow-[0_-6px_16px_rgba(0,0,0,.06)]" style="padding-bottom: max(.625rem, env(safe-area-inset-bottom));">
    <div class="flex items-center gap-2">
        <button type="button" data-ant class="inline-flex items-center justify-center gap-1 border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold size-10 sm:size-auto sm:px-3 sm:py-2.5 rounded-lg disabled:opacity-35 disabled:pointer-events-none" title="Paso anterior">
            <x-icono name="chevron-left" class="size-4" /><span class="hidden sm:inline">Anterior</span>
        </button>
        <div class="flex-1 min-w-0 px-1">
            <div class="flex items-center justify-between gap-2 text-[11px]">
                <span data-paso-texto class="font-semibold text-azul-800 truncate"></span>
                <button type="button" data-modo class="shrink-0 text-gray-500 hover:text-azul-800 underline"></button>
            </div>
            <div class="h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden"><div data-progreso class="h-full bg-oro-500 transition-all" style="width:0"></div></div>
        </div>
        <button type="button" data-sig class="inline-flex items-center justify-center gap-1 border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold size-10 sm:size-auto sm:px-3 sm:py-2.5 rounded-lg disabled:opacity-35 disabled:pointer-events-none" title="Paso siguiente">
            <span class="hidden sm:inline">Siguiente</span><x-icono name="chevron-right" class="size-4" />
        </button>
        <button type="submit" class="inline-flex items-center justify-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold h-10 px-4 sm:px-5 rounded-lg transition-colors shrink-0">
            <x-icono name="save" class="size-4" /><span class="hidden sm:inline">{{ $texto ?? 'Guardar' }}</span><span class="sm:hidden">Guardar</span>
        </button>
    </div>
</div>
