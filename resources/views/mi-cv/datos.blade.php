@php $paisActual = old('pais', $cliente->pais ?: 'NI'); @endphp
<x-layout titulo="Mis datos" activo="mi-datos">
    <p class="text-gray-500 mb-5">Llene sus datos con calma. Todo lo que escriba aquí sale en su CV y en sus cartas.</p>

    <form method="POST" action="{{ route('mi-cv.actualizar') }}" enctype="multipart/form-data" onsubmit="return confSave(event)">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow p-6 mb-5">
            <nav data-indice class="sticky top-14 z-10 flex gap-2 overflow-x-auto py-2 mb-4 bg-white/95 backdrop-blur"></nav>
            <section class="jz-seccion" data-titulo="Foto" id="sec-foto">
            <h3 class="text-sm font-bold uppercase text-azul-800 flex items-center gap-2 mb-3"><i data-lucide="camera" class="size-4 text-oro-500"></i> Mi foto</h3>
            <div class="flex flex-wrap items-center gap-4">
                @if($cliente->tieneFoto())
                    <img src="{{ route('clientes.foto', $cliente) }}" class="size-20 rounded-lg object-cover border-2 border-oro-500" alt="">
                @else
                    <div class="size-20 rounded-lg bg-azul-800 text-oro-300 flex items-center justify-center text-2xl font-bold">{{ $cliente->iniciales() }}</div>
                @endif
                <div>
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Mi foto (tipo carné, fondo claro)</label>
                    <input type="file" name="foto" accept="image/*" class="text-sm">
                    @if($cliente->tieneFoto())<label class="flex items-center gap-2 text-sm mt-1"><input type="checkbox" name="quitar_foto" value="1"> Quitar foto</label>@endif
                </div>
            </div>
            </section>
            <div class="border-t border-gray-200 pt-5 mt-5"></div>
            @include('partials.documento-editable', ['cliente' => $cliente])
            <div class="border-t border-gray-200 pt-5 mt-5"></div>
            @include('partials.campos-registro-cliente', ['cliente' => $cliente, 'paisActual' => $paisActual, 'notas' => false])
            <x-cv-form-fields :cliente="$cliente" />
        </div>

        <button type="submit" class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-6 py-3 rounded-lg transition-colors">
            <i data-lucide="check" class="size-4"></i> Guardar mis datos
        </button>
    </form>

    @push('scripts')
    <script>
    function confSave(e){e.preventDefault();Swal.fire({title:'¿Guardar sus datos?',icon:'question',showCancelButton:true,confirmButtonColor:'#0B3C74',confirmButtonText:'Sí, guardar',cancelButtonText:'Cancelar'}).then(r=>{if(r.isConfirmed)e.target.submit();});return false;}
    </script>
    @endpush
</x-layout>
