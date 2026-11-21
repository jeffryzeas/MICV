<x-layout titulo="Registrar correo" activo="correos">
    <div class="gestion">
        <header class="page-heading">
            <div><h2 class="page-title">Registrar correo</h2><p class="mt-1 text-sm text-gray-500">Complete los datos del titular y de la cuenta creada.</p></div>
            <x-ui.button :href="route('correos.index')" variant="secondary" icon="arrow-left">Volver a correos</x-ui.button>
        </header>
        <form method="POST" action="{{ route('correos.guardar') }}" id="correo-form" class="gestion-form gestion-surface">
            @csrf
            <section class="gestion-form-section" aria-labelledby="correo-titular">
                <h3 id="correo-titular">Datos del titular</h3>
                <div class="form-grid">
                    @if(auth()->user()->esAdmin())
                        <x-ui.field label="Negocio" for="correo-negocio"><select id="correo-negocio" name="cyber_id" required class="campo">@foreach($cybers as $cy)<option value="{{ $cy->id }}" @selected(old('cyber_id')==$cy->id)>{{ $cy->nombre }}</option>@endforeach</select></x-ui.field>
                    @endif
                    <x-ui.field label="País" for="correo-pais"><select name="pais" id="correo-pais" required class="campo">@foreach($paises as $codigo=>$p)<option value="{{ $codigo }}" @selected(old('pais','NI')===$codigo)>{{ $p['nombre'] }}</option>@endforeach</select></x-ui.field>
                    <x-ui.field label="Tipo de documento" for="correo-tipo"><select name="tipo_documento" id="correo-tipo" required class="campo"></select></x-ui.field>
                    <x-ui.field label="Número de documento" for="correo-numero">
                        <div class="gestion-input-action"><input id="correo-numero" name="numero_documento" value="{{ old('numero_documento') }}" maxlength="40" required class="campo"><x-ui.button id="correo-consultar" variant="secondary" icon="search">Consultar</x-ui.button></div>
                    </x-ui.field>
                    <p id="correo-resultado" role="status" class="text-xs text-gray-500 sm:col-span-2">Consulte el documento para recuperar una ficha existente. Verifique el nombre con el titular.</p>
                    @foreach(['primer_nombre'=>'Primer nombre','segundo_nombre'=>'Segundo nombre','primer_apellido'=>'Primer apellido','segundo_apellido'=>'Segundo apellido'] as $k=>$label)
                        <x-ui.field :label="$label" :for="'correo-'.$k"><input id="correo-{{ $k }}" name="{{ $k }}" value="{{ old($k) }}" maxlength="60" @required(in_array($k,['primer_nombre','primer_apellido'])) class="campo"></x-ui.field>
                    @endforeach
                </div>
            </section>
            <section class="gestion-form-section" aria-labelledby="correo-cuenta">
                <h3 id="correo-cuenta">Cuenta y recuperación</h3>
                <div class="form-grid">
                    <x-ui.field label="Celular de recuperación" for="correo-celular"><input id="correo-celular" name="celular" value="{{ old('celular') }}" type="tel" placeholder="+506 8888 8888" required maxlength="40" class="campo"></x-ui.field>
                    <x-ui.field label="Proveedor" for="correo-proveedor"><select id="correo-proveedor" name="proveedor" required class="campo">@foreach(['Gmail','Outlook','Yahoo','Proton','iCloud','Otro'] as $p)<option @selected(old('proveedor')===$p)>{{ $p }}</option>@endforeach</select></x-ui.field>
                    <x-ui.field label="Correo creado" for="correo-direccion"><input id="correo-direccion" name="correo" value="{{ old('correo') }}" type="email" required maxlength="150" autocomplete="off" class="campo"></x-ui.field>
                    <x-ui.field label="Contraseña de este correo" for="correo-clave" hint="No es la contraseña de acceso a la plataforma.">
                        <input id="correo-clave" name="clave_correo" type="password" required maxlength="256" autocomplete="new-password" class="campo">
                        <button type="button" id="correo-ver-clave" class="gestion-link mt-2"><x-icono name="eye" class="size-4"/>Mostrar contraseña</button>
                    </x-ui.field>
                </div>
                <p class="gestion-notice gestion-notice-warning">El QR del comprobante incluye la contraseña. Entréguelo únicamente al titular.</p>
                <label class="gestion-checkbox"><input class="casilla" type="checkbox" name="confirmado" value="1" @checked(old('confirmado')) required><span>Confirmo con el titular que el correo funciona, su celular de recuperación está vinculado y autorizó registrar estos datos para entregarle el comprobante.</span></label>
            </section>
            <div class="gestion-form-footer"><x-ui.button :href="route('correos.index')" variant="secondary">Cancelar</x-ui.button><x-ui.button type="submit" icon="check">Guardar y preparar comprobante</x-ui.button></div>
        </form>
    </div>
    @push('scripts')
        <script>window.correoPaises=@json($paises);window.correoTipo=@json(old('tipo_documento'));window.correoConsulta=@json(route('api.consultar-documento'));</script>
        <script src="{{ asset('js/correos-clientes.js') }}" defer></script>
    @endpush
</x-layout>
