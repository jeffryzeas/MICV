@auth
    {{-- Dentro de la app: con el menú y el logo de siempre. --}}
    <x-layout :titulo="$titulo" activo="legal-ver">
        <div class="max-w-4xl">@include('legal._contenido', ['conMarco' => true])</div>
    </x-layout>
@else
    {{-- Sin sesión: pantalla pública con el logo de la marca. --}}
    <x-publico :titulo="$titulo" ancho="max-w-3xl">
        @include('legal._contenido', ['conMarco' => false])
        <p class="text-center text-sm mt-3"><a href="{{ route('login') }}" class="font-semibold text-[#0B3C74] underline">Ir al inicio de sesión</a></p>
    </x-publico>
@endauth
