<x-postulaciones-shell titulo="Gmail para enviar CV" tab="gmail">
    <header class="page-heading"><div><h2 class="page-title">Gmail para enviar CV</h2><p class="mt-1 text-sm text-gray-500">Envíe postulaciones desde su propia cuenta de Gmail o Google Workspace.</p></div></header>
    @if(auth()->user()->esAdmin())
        <div class="post-account-picker"><label class="label-campo" for="gmail-propietario">Cuenta del sistema</label><select id="gmail-propietario" class="campo" onchange="location.assign(this.value)">@foreach($usuarios as $usuario)<option value="{{ route('postulaciones.gmail',$usuario) }}" @selected($usuario->id===$propietario->id)>{{ $usuario->name }} · {{ $usuario->usuario }}</option>@endforeach</select></div>
    @endif
    <div class="gestion-settings-grid">
        <div class="gestion-surface gestion-form-section">
            <h3>{{ $propietario->name }}</h3>
            <p class="post-connection" role="status">{{ $cuenta?->lista() ? 'Conexión comprobada · '.$cuenta->correo : 'Pendiente de guardar o comprobar la conexión' }}</p>
            <form method="POST" action="{{ route('postulaciones.gmail.guardar',$propietario) }}" class="space-y-4">@csrf @method('PUT')
                <x-ui.field label="Dirección de Gmail" for="gmail-correo"><input id="gmail-correo" class="campo" type="email" name="correo" required maxlength="150" autocomplete="email" placeholder="nombre@gmail.com" value="{{ old('correo',$cuenta?->correo) }}"></x-ui.field>
                <x-ui.field label="Nombre del remitente" for="gmail-nombre"><input id="gmail-nombre" class="campo" name="nombre" required maxlength="150" value="{{ old('nombre',$cuenta?->nombre ?? $propietario->name) }}"></x-ui.field>
                <x-ui.field label="Contraseña de aplicación de Google" for="gmail-clave" error="gmail_clave" :hint="$cuenta?->getRawOriginal('clave') ? 'Hay una contraseña guardada. Deje este campo vacío para conservarla.' : 'Son 16 letras. Puede pegarlas con los espacios que muestra Google.'">
                    <input id="gmail-clave" type="password" name="gmail_clave" autocomplete="new-password" class="campo" placeholder="abcd efgh ijkl mnop" maxlength="30" @required(!$cuenta?->getRawOriginal('clave'))>
                </x-ui.field>
                <label class="gestion-checkbox"><input type="checkbox" name="activo" value="1" class="casilla" @checked(old('activo',$cuenta?->activo ?? true))><span>Habilitar esta cuenta para enviar CV a empresas.</span></label>
                <p class="text-xs text-gray-500">La contraseña se guarda cifrada. La administración puede gestionar esta cuenta y utilizarla para los envíos de CV registrados en el sistema.</p>
                <x-ui.button type="submit" icon="check">Guardar cuenta</x-ui.button>
            </form>
            @if($cuenta?->getRawOriginal('clave'))
                <div class="post-connection-actions">
                    <form method="POST" action="{{ route('postulaciones.gmail.comprobar',$propietario) }}">@csrf<x-ui.button type="submit" variant="secondary" icon="plug">Comprobar conexión</x-ui.button></form>
                    <form method="POST" action="{{ route('postulaciones.gmail.desconectar',$propietario) }}" onsubmit="return confirm('¿Desconectar Gmail y eliminar la contraseña guardada?')">@csrf @method('DELETE')<x-ui.button type="submit" variant="danger">Desconectar</x-ui.button></form>
                </div>
            @endif
        </div>
        <aside class="gestion-settings-help"><h3>Cómo obtener las 16 letras</h3><ol class="list-decimal pl-5 space-y-3"><li>Entre en su cuenta de Google y active la verificación en dos pasos.</li><li>Abra <a class="gestion-link" href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener noreferrer">Contraseñas de aplicaciones</a>.</li><li>Cree una contraseña para «Mi CV» y péguela aquí.</li><li>Guarde y pulse «Comprobar conexión». Esa prueba autentica la cuenta y no envía mensajes.</li></ol><p class="mt-4">Si Google no ofrece contraseñas de aplicaciones, revise las políticas de su cuenta. Una cuenta de empresa puede requerir que su administrador habilite esta opción.</p><p class="mt-4">Los correos de verificación y recuperación del sistema mantienen su configuración independiente.</p></aside>
    </div>
</x-postulaciones-shell>
