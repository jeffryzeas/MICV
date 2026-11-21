<x-layout titulo="Comprobante de correo" activo="correos">
    <div class="gestion">
        <header class="page-heading">
            <div><h2 class="page-title">Comprobante de correo</h2><p class="mt-1 text-sm text-gray-500">{{ $correo->cliente->nombreCompleto() }}</p></div>
            <x-ui.button :href="route('correos.index')" variant="secondary" icon="arrow-left">Volver a correos</x-ui.button>
        </header>
        @if(session('exito'))<p role="status" class="gestion-notice gestion-notice-success">{{ session('exito') }}</p>@endif
        <div class="gestion-detail-grid">
            <section class="gestion-surface gestion-form-section" aria-labelledby="correo-detalle">
                <h3 id="correo-detalle">Cuenta registrada</h3>
                <dl class="gestion-description">
                    <div><dt>Correo</dt><dd class="break-all">{{ $correo->correo }}</dd></div>
                    <div><dt>Proveedor</dt><dd>{{ $correo->proveedor }}</dd></div>
                    <div><dt>Celular vinculado</dt><dd>{{ $correo->celular }}</dd></div>
                    <div><dt>País</dt><dd>{{ \App\Support\Pais::de($correo->pais)['nombre'] }}</dd></div>
                    <div><dt>Registrado</dt><dd>{{ $correo->created_at->timezone('America/Costa_Rica')->format('d/m/Y H:i') }} (Costa Rica)</dd></div>
                </dl>
                <p class="gestion-notice gestion-notice-warning">El PDF y el QR incluyen la contraseña. El QR contiene nombre, correo, contraseña, celular y proveedor. Entregue la hoja únicamente al titular, no la comparta y recomiende cambiar la contraseña después de la entrega.</p>
                <div class="gestion-actions"><x-ui.button :href="route('correos.pdf', $correo)" icon="file-down">Descargar comprobante · Carta</x-ui.button></div>
                <div class="gestion-related-links">
                    @if(auth()->user()->tienePermiso('clientes.editar'))<a class="gestion-link" href="{{ route('clientes.edit',$correo->cliente) }}">Completar ficha y CV</a>@endif
                    @if(auth()->user()->esAdmin() || (auth()->user()->esEncargado() && auth()->user()->tienePermiso('clientes.acceso')))<a class="gestion-link" href="{{ route('clientes.acceso',$correo->cliente) }}">Acceso del cliente a la plataforma</a>@endif
                </div>
            </section>
            <form method="POST" action="{{ route('correos.actualizar',$correo) }}" class="gestion-surface gestion-form-section">
                @csrf @method('PUT')
                <h3>Actualizar datos</h3>
                <div class="space-y-4">
                    <x-ui.field label="Celular vinculado" for="correo-celular"><input id="correo-celular" class="campo" type="tel" name="celular" required maxlength="40" value="{{ old('celular',$correo->celular) }}"></x-ui.field>
                    <x-ui.field label="Nueva contraseña del correo" for="correo-nueva-clave"><input id="correo-nueva-clave" class="campo" type="password" name="clave_correo" autocomplete="new-password" required maxlength="256"></x-ui.field>
                    <x-ui.button type="submit" icon="check">Guardar actualización</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-layout>
