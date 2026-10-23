<x-layout titulo="Registrar correo de cliente" activo="correos">
<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('correos.index') }}" class="btn btn-sec">
            <x-icono name="arrow-left" class="size-4"/> Volver
        </a>
        <div>
            <h1 class="text-2xl font-bold text-azul-800 flex items-center gap-2">
                <x-icono name="mail-plus" class="size-7"/>
                Registrar correo del cliente
            </h1>
            <p class="text-gray-500 text-sm mt-0.5">Identifique al titular, registre la cuenta y entregue el comprobante personal con QR.</p>
        </div>
    </div>

    @if($errors->any())
        <div role="alert" class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-800">
            @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif

    <section class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-5 sm:p-6">
        {{-- Pasos visuales --}}
        <div class="flex flex-wrap gap-2 mb-6 text-sm">
            <span class="inline-flex items-center gap-1.5 bg-azul-800 text-white px-3 py-1.5 rounded-full font-semibold"><b>1</b> Identidad</span>
            <span class="inline-flex items-center gap-1.5 bg-gray-100 text-gray-700 px-3 py-1.5 rounded-full font-semibold"><b>2</b> Cuenta y recuperación</span>
            <span class="inline-flex items-center gap-1.5 bg-gray-100 text-gray-700 px-3 py-1.5 rounded-full font-semibold"><b>3</b> Entrega segura</span>
        </div>

        <form method="POST" action="{{ route('correos.guardar') }}" id="correo-form" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf

            <div class="md:col-span-2 flex items-center gap-2 pb-2 border-b border-gray-100">
                <x-icono name="contact" class="size-5 text-azul-800"/>
                <div>
                    <strong class="text-azul-950">Identidad del titular</strong>
                    <small class="block text-gray-500">Consulte el documento para recuperar una ficha existente.</small>
                </div>
            </div>

            @if(auth()->user()->esAdmin())
                <label class="block">
                    <span class="text-xs font-semibold uppercase text-azul-800">Cyber</span>
                    <select name="cyber_id" required class="campo w-full mt-1">
                        @foreach($cybers as $cy)
                            <option value="{{ $cy->id }}" @selected(old('cyber_id') == $cy->id)>{{ $cy->nombre }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="block">
                <span class="text-xs font-semibold uppercase text-azul-800">País</span>
                <select name="pais" id="correo-pais" required class="campo w-full mt-1">
                    @foreach($paises as $codigo => $p)
                        <option value="{{ $codigo }}" @selected(old('pais', 'NI') === $codigo)>{{ $p['nombre'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="text-xs font-semibold uppercase text-azul-800">Tipo de documento</span>
                <select name="tipo_documento" id="correo-tipo" required class="campo w-full mt-1"></select>
            </label>

            <label class="block md:col-span-2">
                <span class="text-xs font-semibold uppercase text-azul-800">Número de documento</span>
                <div class="flex gap-2 mt-1">
                    <input name="numero_documento" value="{{ old('numero_documento') }}" maxlength="40" required class="campo w-full">
                    <button type="button" id="correo-consultar" class="btn btn-sec shrink-0">
                        <x-icono name="search"/> Consultar
                    </button>
                </div>
            </label>

            <p id="correo-resultado" role="status" class="md:col-span-2 text-sm text-gray-600 min-h-[1.25rem]"></p>

            <div class="md:col-span-2 flex items-center gap-2 pt-3 pb-2 border-b border-gray-100">
                <x-icono name="mail" class="size-5 text-azul-800"/>
                <div>
                    <strong class="text-azul-950">Cuenta de correo</strong>
                    <small class="block text-gray-500">Datos del correo que se creó para el cliente.</small>
                </div>
            </div>

            <label class="block">
                <span class="text-xs font-semibold uppercase text-azul-800">Proveedor</span>
                <select name="proveedor" required class="campo w-full mt-1">
                    @foreach(['Gmail','Outlook','Yahoo','Proton','iCloud','Otro'] as $prov)
                        <option value="{{ $prov }}" @selected(old('proveedor') === $prov)>{{ $prov }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="text-xs font-semibold uppercase text-azul-800">Correo</span>
                <input name="correo" value="{{ old('correo') }}" type="email" required maxlength="150" autocomplete="off" class="campo w-full mt-1">
            </label>

            <label class="block md:col-span-2">
                <span class="text-xs font-semibold uppercase text-azul-800">Contraseña de este correo</span>
                <div class="relative mt-1">
                    <input name="clave_correo" type="password" required maxlength="256" autocomplete="new-password" class="campo w-full pr-28" id="clave-correo-input">
                    <button type="button" id="correo-ver-clave" class="absolute right-2 top-1/2 -translate-y-1/2 text-sm font-semibold text-azul-800 flex items-center gap-1">
                        <x-icono name="eye" class="size-4"/> Mostrar
                    </button>
                </div>
                <small class="text-gray-500">No es la contraseña de acceso a Mi CV.</small>
            </label>

            <div class="md:col-span-2 flex items-start gap-3 p-4 bg-blue-50 rounded-xl border border-blue-100">
                <x-icono name="qr-code" class="size-5 text-azul-800 shrink-0 mt-0.5"/>
                <p class="text-sm text-azul-900">
                    El comprobante incluye un <strong>QR</strong> con el correo, la contraseña y los datos del titular.
                    Entréguelo únicamente al cliente para que lo escanee y guarde de forma segura.
                </p>
            </div>

            <label class="md:col-span-2 flex gap-3 items-start p-3 border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50">
                <input type="checkbox" name="confirmado" value="1" required class="mt-1">
                <span class="text-sm">
                    Confirmo con el titular que el correo funciona, el celular de recuperación está vinculado y autorizó registrar sus datos para entregarle el comprobante.
                </span>
            </label>

            <div class="md:col-span-2 flex flex-wrap gap-3 pt-2">
                <button type="submit" class="btn btn-pri">
                    <x-icono name="save"/> Guardar y preparar comprobante
                </button>
                <a href="{{ route('correos.index') }}" class="btn btn-sec">Cancelar</a>
            </div>
        </form>
    </section>
</div>

@push('scripts')
<script>
    window.correoPaises = @json($paises);
    window.correoTipo = @json(old('tipo_documento'));
    window.correoConsulta = @json(route('api.consultar-documento'));
</script>
<script src="{{ asset('js/correos-clientes.js') }}" defer></script>
@endpush
</x-layout>
