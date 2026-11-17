<x-layout titulo="Video promocional" activo="video-promocional">
    <link rel="stylesheet" href="{{ asset('css/video-promocional.css') }}?v={{ @filemtime(public_path('css/video-promocional.css')) }}">
    <div class="page-heading">
        <div>
            <h2 class="page-title">Tutorial para crear un CV</h2>
            <p class="mt-1 text-sm text-gray-500">Un tutorial con CV lateral y foto, narración en español, movimiento por capas y transiciones suaves.</p>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <x-ui.panel title="Contenido del tutorial" icon="list-checks">
            <ol class="ml-5 list-decimal space-y-3 text-sm text-gray-700">
                <li>Abrir la página e iniciar el registro.</li>
                <li>Crear la cuenta y verificar el correo.</li>
                <li>Añadir y encuadrar la foto; completar perfil, estudios y experiencia.</li>
                <li>Elegir Lateral, revisar la vista previa y descargar el PDF con foto.</li>
            </ol>
            <p class="mt-5 border-t border-gray-200 pt-4 text-sm text-gray-500">Demostración con datos ficticios de Nicaragua. Full HD · 1080 × 1920 · 30 fps. Trece escenas con foto ficticia, formularios animados, composición por capas, subtítulos y ambiente musical original. Pocket TTS se utiliza cuando está instalado; también admite Piper.</p>

            @unless($abierto)
                <p class="mt-3 text-sm font-semibold text-amber-800 dark:text-amber-200">Abra el registro público para generar un tutorial que las personas puedan seguir.</p>
            @endunless
            <form class="mt-5" method="POST" action="{{ route('admin.video-promocional.store') }}">
                @csrf
                <x-ui.button id="generar-video" type="submit" icon="video" :disabled="!$abierto || ($actual && in_array($actual['estado'], ['pendiente', 'procesando'], true) && $actual['actualizado'] >= now()->subMinutes(20)->getTimestamp())">
                    Generar video
                </x-ui.button>
            </form>
        </x-ui.panel>

        <x-ui.panel title="Vista previa y descarga" icon="play">
            <div id="video-promocional"
                @if($actual) data-estado="{{ $actual['estado'] }}" data-url="{{ route('admin.video-promocional.estado', $actual['id']) }}" @endif
                data-abierto="{{ $abierto ? '1' : '0' }}" data-iniciado="{{ $actual['iniciado'] ?? '' }}">
                <p id="video-estado" class="mb-4 text-sm text-gray-700" role="status" aria-live="polite">
                    {{ $actual['mensaje'] ?? 'Aquí aparecerá el video cuando termine la generación.' }}
                </p>
                <div class="video-avance" aria-label="Avance de la generación">
                    <div class="video-avance-cabecera">
                        <strong id="video-porcentaje">{{ $actual['porcentaje'] ?? 0 }}%</strong>
                        <span id="video-tiempo">{{ ($actual['estado'] ?? null) === 'listo' ? 'Generación completada' : 'Puede salir y volver a esta página.' }}</span>
                    </div>
                    <progress id="video-progreso" max="100" value="{{ $actual['porcentaje'] ?? 0 }}" aria-label="Porcentaje de generación"></progress>
                    @php($ordenEtapas = ['cola', 'preparacion', 'capturas', 'voz', 'render', 'exportacion', 'completado'])
                    @php($pasoActual = array_search($actual['etapa'] ?? '', $ordenEtapas, true))
                    <ol class="video-etapas" aria-label="Etapas">
                        @foreach(['cola' => 'En cola', 'preparacion' => 'Preparación', 'capturas' => 'Pantallas', 'voz' => 'Narración', 'render' => 'Animación', 'exportacion' => 'MP4', 'completado' => 'Listo'] as $clave => $etiqueta)
                            <li data-etapa="{{ $clave }}" data-fase="{{ ($actual['estado'] ?? '') === 'error' ? 'error' : (($actual['etapa'] ?? '') === $clave ? 'actual' : ($pasoActual !== false && array_search($clave, $ordenEtapas, true) < $pasoActual ? 'completa' : 'pendiente')) }}">{{ $etiqueta }}</li>
                        @endforeach
                    </ol>
                    <p id="video-detalle" class="text-xs text-gray-500">El avance corresponde a las etapas del renderizado.</p>
                </div>
                <div id="video-resultado" @class(['hidden' => ($actual['estado'] ?? null) !== 'listo'])>
                    <video id="video-vista" class="mx-auto block max-h-[620px] w-full rounded-xl bg-gray-900" controls playsinline preload="metadata"
                        @if(($actual['estado'] ?? null) === 'listo') src="{{ route('admin.video-promocional.archivo', $actual['id']) }}" @endif></video>
                    <div class="mt-4">
                        <a id="video-descarga" class="btn btn-pri"
                           @if(($actual['estado'] ?? null) === 'listo') href="{{ route('admin.video-promocional.archivo', [$actual['id'], 'descargar' => 1]) }}" @endif>
                            <x-icono name="download" class="size-4" /> Descargar MP4
                        </a>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">Descárguelo antes de que se borre automáticamente después de dos días.</p>
                </div>
            </div>
        </x-ui.panel>
    </div>

    @push('scripts')
        <script src="{{ asset('js/video-promocional.js') }}?v={{ @filemtime(public_path('js/video-promocional.js')) }}" defer></script>
    @endpush
</x-layout>
