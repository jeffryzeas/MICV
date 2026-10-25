@php $paisActual = old('pais', $cliente->pais ?: 'NI'); @endphp
<x-layout titulo="Mis datos" activo="mi-datos">
    <p class="text-gray-500 mb-4 text-sm">Llene sus datos con calma, paso a paso o saltando al que quiera. Puede guardar en cualquier momento con el botón de abajo.</p>
    @include('partials.aviso-identidad', ['cliente' => $cliente])

    @php($edicion=\App\Support\EdicionCliente::permitidos(auth()->user()))
    <div class="mb-5 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 flex items-start gap-2"><x-icono name="shield-check" class="size-5 shrink-0"/><p>{{ $edicion ? 'Puede editar las secciones habilitadas. Las demás se muestran en modo de consulta.' : 'Su cuenta tiene acceso de consulta. Solicite al administrador o al encargado que habilite la edición.' }}</p></div>
    <form id="form-mi-cv" method="POST" action="{{ route('mi-cv.actualizar') }}" enctype="multipart/form-data" onsubmit="return confSave(event)" data-pasos="si">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow p-4 sm:p-6">
            <nav data-indice></nav>
            <section class="jz-seccion" data-titulo="Foto" id="sec-foto">
                <h3 class="text-sm font-bold uppercase text-azul-800 flex items-center gap-2 mb-3"><x-icono name="camera" class="size-4 text-oro-500" /> Mi foto</h3>
                @include('partials.foto-campo', ['cliente' => $cliente])
            </section>
            <div class="border-t border-gray-200 pt-5 mt-5 jz-separador"></div>
            @include('partials.documento-editable', ['cliente' => $cliente])
            <div class="border-t border-gray-200 pt-5 mt-5 jz-separador"></div>
            @include('partials.campos-registro-cliente', ['cliente' => $cliente, 'paisActual' => $paisActual, 'notas' => false])
            <x-cv-form-fields :cliente="$cliente" />

            @if($edicion)@include('partials.barra-guardar', ['texto' => 'Guardar mis datos'])@endif
        </div>
    </form>

    @push('scripts')
    <script type="application/json" id="portal-edicion">@json(\App\Support\EdicionCliente::mapa(auth()->user()))</script>
    <script src="{{ asset('js/portal-edicion.js') }}" defer></script>
    <script>
    function confSave(e){e.preventDefault();Swal.fire({title:'¿Guardar sus datos?',icon:'question',showCancelButton:true,confirmButtonColor:'#0B3C74',confirmButtonText:'Sí, guardar',cancelButtonText:'Cancelar'}).then(r=>{if(r.isConfirmed)e.target.submit();});return false;}
    </script>
    @endpush
</x-layout>
