<x-layout titulo="Editar cliente" activo="clientes">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <p class="text-gray-500">
            {{ $cliente->datosPais()['bandera'] }} {{ $cliente->datosPais()['nombre'] }} · {{ $cliente->tipoDocumentoLegible() }}: <strong class="text-azul-800 font-mono">{{ $cliente->documentoFormateado() ?: '—' }}</strong>

        </p>
        <a href="{{ route('clientes.show', $cliente) }}" class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors">
            <i data-lucide="file-text" class="size-4"></i> Ver ficha y generar CV
        </a>
    </div>

    <form method="POST" action="{{ route('clientes.update', $cliente) }}" enctype="multipart/form-data" onsubmit="return confSave(event)" data-pasos="no">
    <div class="bg-white rounded-xl shadow p-4 sm:p-6">
            @csrf @method('PUT')

            <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
                <div class="flex-1 min-w-[260px]">@include('partials.foto-campo', ['cliente' => $cliente])</div>
                <div class="text-right">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1 flex items-center justify-end gap-1.5">
                        <i data-lucide="toggle-right" class="size-3.5"></i> Estado
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-normal border border-gray-300 rounded-lg px-3 py-2">
                        <input type="checkbox" name="activo" value="1" @checked(old('activo', $cliente->activo))>
                        {{ $cliente->activo ? 'Activo' : 'En mantenimiento' }}
                    </label>
                </div>
            </div>

            @if(auth()->user()->esAdmin() && $cybers->isNotEmpty())
                <input type="hidden" name="cybers_enviados" value="1">
                <div class="mb-5 p-4 bg-gray-50 rounded-lg">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-2 flex items-center gap-1.5">
                        <i data-lucide="building-2" class="size-3.5"></i> Cybers que ven a esta persona
                    </label>
                    <div class="flex flex-wrap gap-3">
                        @php $mios = old('cybers', $cliente->cybers->pluck('id')->all()); @endphp
                        @foreach($cybers as $cy)
                            <label class="inline-flex items-center gap-2 text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white">
                                <input type="checkbox" name="cybers[]" value="{{ $cy->id }}" @checked(in_array($cy->id, $mios))> {{ $cy->nombre }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <nav data-indice></nav>
            @include('partials.documento-editable', ['cliente' => $cliente])
            <div class="border-t border-gray-200 pt-5 mt-5 jz-separador"></div>
            @include('partials.campos-registro-cliente', ['cliente' => $cliente])

            <x-cv-form-fields :cliente="$cliente" />

            @include('partials.barra-guardar', ['texto' => 'Guardar cambios'])
    </div>
    </form>

    @push('scripts')
    <script>
    function confSave(e){e.preventDefault();Swal.fire({title:'¿Guardar cambios?',icon:'question',showCancelButton:true,confirmButtonColor:'#0B3C74',confirmButtonText:'Sí',cancelButtonText:'Cancelar'}).then(r=>{if(r.isConfirmed)e.target.submit();});return false;}
    </script>
    @endpush
</x-layout>
