<x-layout titulo="Video promocional" activo="video-promocional">
    <div class="page-heading">
        <div>
            <h2 class="page-title">Tutorial para crear un CV</h2>
            <p class="mt-1 text-sm text-gray-500">Genere un video vertical con las pantallas actuales de Mi CV.</p>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <x-ui.panel title="Contenido del tutorial" icon="list-checks">
            <ol class="ml-5 list-decimal space-y-3 text-sm text-gray-700">
                <li>Abrir la página e iniciar el registro.</li>
                <li>Crear la cuenta y verificar el correo.</li>
                <li>Completar los datos, los estudios y la experiencia.</li>
                <li>Elegir una plantilla y descargar el CV en PDF.</li>
            </ol>
            <p class="mt-5 border-t border-gray-200 pt-4 text-sm text-gray-500">Demostración con datos ficticios de Nicaragua. MP4 9:16 con voz en español, subtítulos y botones resaltados.</p>

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
                data-abierto="{{ $abierto ? '1' : '0' }}">
                <p id="video-estado" class="mb-4 text-sm text-gray-700" role="status" aria-live="polite">
                    @switch($actual['estado'] ?? null)
                        @case('pendiente') Video en cola. Puede volver a esta página más tarde. @break
                        @case('procesando') Capturando pantallas y preparando la voz. @break
                        @case('listo') Su video está listo. @break
                        @case('error') Falló la generación. Revise el servicio de cola, Chromium, la voz y FFmpeg, o vuelva a intentarlo. @break
                        @default Aquí aparecerá el video cuando termine la generación.
                    @endswitch
                </p>
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
        <script src="{{ asset('js/video-promocional.js') }}" defer></script>
    @endpush
</x-layout>
