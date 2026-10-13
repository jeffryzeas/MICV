@php $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5'; $lb = 'block text-xs font-semibold uppercase text-azul-800 mb-1'; @endphp
<x-layout titulo="Avisos por correo" activo="avisos">
    @unless($correoListo)
        <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">Primero configure el correo en <a href="{{ route('admin.correo.edit') }}" class="underline font-semibold">Correo Gmail</a>.</div>
    @endunless
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 bg-white rounded-xl shadow p-5 sm:p-6">
            <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><x-icono name="megaphone" class="size-[18px] text-oro-500" /> Enviar un aviso</h2>
            <form method="GET" id="fGrupo" class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4 p-3 bg-gray-50 rounded-lg">
                <div class="sm:col-span-2"><label class="{{ $lb }}">¿A quién?</label>
                    <select name="grupo" class="{{ $in }}" onchange="this.form.submit()">
                        @foreach(\App\Http\Controllers\Admin\AvisoController::GRUPOS as $k => $t)<option value="{{ $k }}" @selected($grupo === $k)>{{ $t }}</option>@endforeach
                    </select></div>
                @if($grupo === 'incompletos')
                    <div><label class="{{ $lb }}">CV completo menos de</label>
                        <select name="minimo" class="{{ $in }}" onchange="this.form.submit()">@foreach([50, 70, 80, 90, 100] as $m)<option value="{{ $m }}" @selected($minimo === $m)>{{ $m }}%</option>@endforeach</select></div>
                @elseif($grupo === 'uno')
                    <div><label class="{{ $lb }}">Correo</label><input type="email" name="correo" value="{{ $correo }}" onchange="this.form.submit()" class="{{ $in }}" placeholder="persona@correo.com"></div>
                @endif
            </form>
            <form method="POST" action="{{ route('admin.avisos.enviar') }}" class="space-y-4" onsubmit="return confAviso(event)">
                @csrf
                <input type="hidden" name="grupo" value="{{ $grupo }}"><input type="hidden" name="minimo" value="{{ $minimo }}"><input type="hidden" name="correo" value="{{ $correo }}">
                <div><label class="{{ $lb }}">Asunto</label>
                    <input type="text" name="asunto" required maxlength="120" value="{{ old('asunto', '{nombre}, su CV está al {avance}: termínelo en 5 minutos') }}" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">Mensaje</label>
                    <textarea name="mensaje" rows="6" required maxlength="2000" class="{{ $in }}">{{ old('mensaje', "Vimos que su currículum está al {avance}. Un CV completo (con foto, experiencia y habilidades) le da muchas más oportunidades.\n\nEntre cuando pueda: le mostramos paso a paso lo que le falta, y al terminar puede descargarlo en PDF o enviárselo a su correo.") }}</textarea>
                    <p class="text-[11px] text-gray-400 mt-1">Deje una línea en blanco entre párrafos. Puede usar <code>{nombre}</code> y <code>{avance}</code>.</p></div>
                <div><label class="{{ $lb }}">Texto del botón</label>
                    <input type="text" name="boton" maxlength="40" value="{{ old('boton', 'Completar mi CV') }}" class="{{ $in }}"></div>
                <button class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg disabled:opacity-40" @disabled(!$cantidad || !$correoListo)>
                    <x-icono name="send" class="size-4" /> Enviar a {{ min($cantidad, \App\Http\Controllers\Admin\AvisoController::MAXIMO_POR_ENVIO) }} persona(s)
                </button>
            </form>
        </div>
        <div class="bg-white rounded-xl shadow p-5 sm:p-6">
            <h3 class="font-semibold text-azul-950 mb-1">Quiénes lo recibirán</h3>
            <p class="text-3xl font-bold text-azul-800">{{ $cantidad }}</p>
            <p class="text-xs text-gray-500 mb-3">Solo usuarios activos y con correo verificado. Máximo {{ \App\Http\Controllers\Admin\AvisoController::MAXIMO_POR_ENVIO }} por envío (Gmail permite unos 500 correos al día).</p>
            <ul class="text-sm text-gray-600 space-y-1">
                @foreach($muestra as $u)<li class="truncate">• {{ $u->name }} <span class="text-gray-400">({{ $u->cliente?->porcentajeCompleto() ?? 0 }}%)</span></li>@endforeach
                @if($cantidad > count($muestra))<li class="text-gray-400">… y {{ $cantidad - count($muestra) }} más</li>@endif
            </ul>
        </div>
    </div>
    <script>
    function confAviso(e) { e.preventDefault(); Swal.fire({ icon: 'question', title: '¿Enviar el aviso?', text: 'Se enviará a {{ min($cantidad, \App\Http\Controllers\Admin\AvisoController::MAXIMO_POR_ENVIO) }} persona(s). Puede tardar unos minutos.', showCancelButton: true, confirmButtonText: 'Sí, enviar', cancelButtonText: 'Cancelar', confirmButtonColor: '#0B3C74' })
        .then(r => { if (r.isConfirmed) { jzMostrarCargando && jzMostrarCargando('Enviando avisos…'); e.target.submit(); } }); return false; }
    </script>
</x-layout>
