@php
    $yo = auth()->user();
    $cyberColores = ($yo->esAdmin() ? null : $yo->cyber) ?? $cliente->cybers->first();
@endphp
<x-layout titulo="Ficha del cliente" activo="clientes">

    <a href="{{ route('clientes.index') }}" class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 hover:bg-azul-800 hover:text-white text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg shadow-sm transition-colors mb-5">
        <x-icono name="arrow-left" class="size-4" /> Volver a la lista
    </a>

    @if($yo->esAdmin() || ($yo->esEncargado() && $yo->tienePermiso('clientes.acceso')))
    <a href="{{ route('clientes.acceso',$cliente) }}" class="inline-flex border bg-white rounded-lg px-4 py-3 mb-4 text-azul-800 font-semibold">Cuenta personal y acceso del cliente →</a>
    @endif
    <div class="bg-white rounded-xl shadow p-6 mb-5">
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5">
            @if($cliente->tieneFoto())
                <img src="{{ route('clientes.foto', $cliente) }}" class="size-32 rounded-lg object-cover border-4 border-oro-500 shrink-0" alt="">
            @else
                <div class="size-32 rounded-lg bg-white border-2 border-dashed border-gray-300 text-gray-400 flex flex-col items-center justify-center shrink-0" title="Sin foto">
                    <x-icono name="user-round" class="size-12" /><span class="text-[10px] font-semibold uppercase mt-1">Sin foto</span>
                </div>
            @endif

            <div class="flex-1 text-center sm:text-left">
                <div class="flex items-center gap-2 justify-center sm:justify-start flex-wrap">
                    <h1 class="text-2xl font-bold text-azul-950">{{ $cliente->nombreCompleto() }}</h1>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $cliente->activo ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                        {{ $cliente->activo ? 'Activo' : 'En mantenimiento' }}
                    </span>
                </div>
                <p class="text-gray-500 mt-1">{{ $cliente->tipoDocumentoLegible() }} · <span class="font-mono">{{ $cliente->documentoFormateado() }}</span></p>
                @if($cliente->profesion)<p class="text-sm text-gray-400 mt-0.5">{{ $cliente->profesion }}</p>@endif
                @if($yo->esAdmin())
                    <p class="text-xs text-gray-400 mt-1">Cyber: {{ $cliente->cybers->pluck('nombre')->implode(', ') ?: 'ninguno' }}
                        @if($cliente->creador) · registrado por {{ $cliente->creador->usuario }}@endif</p>
                @endif

                <div class="flex flex-wrap gap-2 justify-center sm:justify-start mt-4">
                    <a href="{{ route('clientes.edit', $cliente) }}" class="inline-flex items-center gap-1.5 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                        <x-icono name="pencil" class="size-4" /> Editar
                    </a>
                    <a href="#generar-cv" class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                        <x-icono name="file-text" class="size-4" /> CV (PDF)
                    </a>
                    <a href="{{ route('tarjetas.editar', $cliente) }}" class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                        <x-icono name="contact" class="size-4" /> Tarjeta de presentación
                    </a>
                    <a href="{{ route('fotos.index', ['cliente' => $cliente->id]) }}" class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                        <x-icono name="camera" class="size-4" /> Fotos carné
                    </a>
                    <a href="{{ route('pos.vender', ['cliente' => $cliente->id]) }}" class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                        <x-icono name="banknote" class="size-4" /> Cobrar
                    </a>
                    @if($yo->esAdmin() && $cliente->usuario)
                        <a href="{{ route('admin.avisos.index', ['grupo' => 'uno', 'correo' => $cliente->usuario->email]) }}" class="inline-flex items-center gap-1.5 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                            <x-icono name="megaphone" class="size-4" /> Enviar recordatorio
                        </a>
                    @endif
                    @if($yo->esAdmin())
                        <form method="POST" action="{{ route('clientes.destroy', $cliente) }}" onsubmit="return confBorrar(event)">
                            @csrf @method('DELETE')
                            <button type="submit" class="inline-flex items-center gap-1.5 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 text-sm font-semibold px-4 py-2 rounded-lg">
                                <x-icono name="trash-2" class="size-4" /> Eliminar
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('partials.generador-cv', ['cliente' => $cliente, 'cv' => $cv, 'paletas' => $paletas, 'cyberColores' => $cyberColores])

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><x-icono name="id-card" class="size-[18px] text-oro-500" /> Datos personales</h2>
            <table class="w-full text-sm">
                <tr class="border-b"><td class="py-2 text-gray-500 w-2/5">{{ $cliente->datosPais()['seguro_largo'] }}</td><td class="py-2 font-medium">{{ $cliente->inss_nss ?? '—' }}</td></tr>
                <tr class="border-b"><td class="py-2 text-gray-500">Fecha nacimiento</td><td class="py-2 font-medium">{{ $cliente->fecha_nacimiento?->format('d/m/Y') ?? '—' }}</td></tr>
                <tr class="border-b"><td class="py-2 text-gray-500">Edad</td><td class="py-2 font-medium">{{ $cliente->edad() ?? '—' }}</td></tr>
                <tr class="border-b"><td class="py-2 text-gray-500">Sexo</td><td class="py-2 font-medium">{{ $cliente->sexoLegible() ?? '—' }}</td></tr>
                <tr class="border-b"><td class="py-2 text-gray-500">Nacionalidad</td><td class="py-2 font-medium">{{ $cliente->nacionalidad ?? '—' }}</td></tr>
                <tr class="border-b"><td class="py-2 text-gray-500">{{ $cliente->datosPais()['municipio'] }} / {{ $cliente->datosPais()['departamento'] }}</td><td class="py-2 font-medium">{{ $cliente->municipio_nacimiento ?? '—' }} / {{ $cliente->departamento_nacimiento ?? '—' }}</td></tr>
                <tr class="border-b"><td class="py-2 text-gray-500">Estado civil</td><td class="py-2 font-medium">{{ $cliente->estado_civil ?? '—' }}</td></tr>
                <tr class="border-b"><td class="py-2 text-gray-500">Número de hijos</td><td class="py-2 font-medium">{{ $cliente->numero_hijos ?? '—' }}</td></tr>
                <tr><td class="py-2 text-gray-500">Profesión u oficio</td><td class="py-2 font-medium">{{ $cliente->profesion ?? '—' }}</td></tr>
            </table>
        </div>

        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><x-icono name="phone" class="size-[18px] text-oro-500" /> Contacto</h2>
            <table class="w-full text-sm">
                <tr class="border-b"><td class="py-2 text-gray-500 w-2/5">Correo</td><td class="py-2 font-medium">{{ $cliente->correo ?? '—' }}</td></tr>
                <tr class="border-b"><td class="py-2 text-gray-500">Celular</td><td class="py-2 font-medium">{{ $cliente->celulares() ?? '—' }}</td></tr>
                <tr><td class="py-2 text-gray-500">Dirección</td><td class="py-2 font-medium">{{ $cliente->direccion ?? '—' }}</td></tr>
            </table>
            @if($cliente->notas)
                <p class="text-xs font-semibold uppercase text-azul-800 mt-4 mb-1">Notas</p>
                <p class="text-sm text-gray-600">{{ $cliente->notas }}</p>
            @endif
        </div>

        @if($cliente->nombre_padre || $cliente->nombre_madre)
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><x-icono name="users" class="size-[18px] text-oro-500" /> Familia</h2>
            <table class="w-full text-sm">
                <tr class="border-b"><td class="py-2 text-gray-500 w-2/5">Nombre del padre</td><td class="py-2 font-medium">{{ $cliente->nombre_padre ?? '—' }}</td></tr>
                <tr><td class="py-2 text-gray-500">Nombre de la madre</td><td class="py-2 font-medium">{{ $cliente->nombre_madre ?? '—' }}</td></tr>
            </table>
        </div>
        @endif

        @if($cliente->perfil_profesional)
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><x-icono name="user-round-pen" class="size-[18px] text-oro-500" /> Perfil profesional</h2>
            <p class="text-sm text-gray-700">{{ $cliente->perfil_profesional }}</p>
        </div>
        @endif

        @if($cliente->estudios->isNotEmpty())
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><x-icono name="graduation-cap" class="size-[18px] text-oro-500" /> Formación académica</h2>
            <ul class="space-y-2 text-sm">
                @foreach($cliente->estudios as $e)
                    <li><span class="font-semibold text-azul-800">{{ $e->nivelLegible() }}:</span> {{ $e->lineaCv() }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        @if($cliente->experiencias->isNotEmpty())
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><x-icono name="briefcase" class="size-[18px] text-oro-500" /> Experiencia laboral</h2>
            <ul class="space-y-2 text-sm">
                @foreach($cliente->experiencias as $exp)<li>{{ $exp->lineaCv() }}</li>@endforeach
            </ul>
        </div>
        @endif

        @if($cliente->habilidades->isNotEmpty())
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><x-icono name="sparkles" class="size-[18px] text-oro-500" /> Habilidades</h2>
            <div class="flex flex-wrap gap-2">
                @foreach($cliente->habilidades as $h)<span class="px-2.5 py-1 rounded-full bg-oro-500/15 text-oro-700 text-xs font-semibold">{{ $h->habilidad }}</span>@endforeach
            </div>
        </div>
        @endif

        @if($cliente->referencias->isNotEmpty())
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><x-icono name="contact" class="size-[18px] text-oro-500" /> Referencias</h2>
            <ul class="space-y-2 text-sm">
                @foreach($cliente->referencias as $ref)<li>{{ $ref->lineaCv() }}</li>@endforeach
            </ul>
        </div>
        @endif
    </div>

    @push('scripts')
    <script>
    function confBorrar(e){e.preventDefault();Swal.fire({title:'¿Eliminar a esta persona?',text:'Se borra del sistema con todo su CV. No se puede deshacer.',icon:'warning',showCancelButton:true,confirmButtonColor:'#c0392b',confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar'}).then(r=>{if(r.isConfirmed)e.target.submit();});return false;}
    </script>
    @endpush
</x-layout>
