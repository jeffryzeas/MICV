@php
    $editando = $usuario->exists;
    $rolActual = old('rol', $usuario->rol);
    $perfilActual = old('perfil_acceso', $usuario->encargado_id ? 'cajero' : 'encargado');
    $esCuentaPropia = $usuario->is(auth()->user());
    $lista = route('admin.usuarios.index');
    $valorAnterior = static function (string $campo, $predeterminado): string {
        $valor = old($campo, $predeterminado);
        return is_scalar($valor) ? (string) $valor : '';
    };
@endphp

<x-layout :titulo="$editando ? 'Editar usuario' : 'Crear usuario'" activo="usuarios">
    <div class="page-heading">
        <div class="min-w-0">
            <h2 class="page-title break-words">{{ $editando ? $usuario->name : 'Nueva cuenta' }}</h2>
            @if($editando)<p class="mt-1 break-words text-sm text-gray-500">{{ $usuario->usuario }} · {{ $usuario->rolLegible() }}</p>@endif
        </div>
        <x-ui.button :href="$lista" variant="secondary" icon="arrow-left">Volver a usuarios</x-ui.button>
    </div>

    <form id="form-usuario" method="POST" action="{{ $editando ? route('admin.usuarios.update', $usuario) : route('admin.usuarios.store') }}" class="max-w-4xl space-y-4">
        @csrf
        @if($editando) @method('PUT') @endif

        <x-ui.panel title="Datos de la cuenta" icon="user-round">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Nombre completo" for="usuario-nombre" error="name" class="sm:col-span-2">
                    <input id="usuario-nombre" name="name" value="{{ $valorAnterior('name', $usuario->name) }}" required maxlength="150" autocomplete="name" class="campo" @error('name') aria-invalid="true" aria-describedby="usuario-nombre-error" @enderror>
                </x-ui.field>
                <x-ui.field label="Usuario" for="usuario-login" error="usuario" hint="Use letras, números, punto, guion o guion bajo.">
                    <input id="usuario-login" name="usuario" value="{{ $valorAnterior('usuario', $usuario->usuario) }}" required minlength="3" maxlength="40" autocomplete="off" autocapitalize="characters" spellcheck="false" class="campo uppercase" @error('usuario') aria-invalid="true" aria-describedby="usuario-login-error usuario-login-ayuda" @else aria-describedby="usuario-login-ayuda" @enderror>
                </x-ui.field>
                <x-ui.field label="Correo electrónico" for="usuario-email" error="email"
                    :hint="$usuario->esCliente() && $usuario->verificado() ? 'Si cambia el correo, el cliente deberá verificar la nueva dirección.' : null">
                    <input id="usuario-email" type="email" name="email" value="{{ $valorAnterior('email', $usuario->email) }}" required maxlength="150" autocomplete="email" class="campo" @error('email') aria-invalid="true" aria-describedby="usuario-email-error" @enderror>
                </x-ui.field>
            </div>
        </x-ui.panel>

        <x-ui.panel title="Rol y permisos" icon="shield-check">
            @if($usuario->esCliente())
                <input type="hidden" name="rol" value="cliente">
                <x-permisos-cliente :usuario="$usuario"/>
            @else
                <x-ui.field label="Rol de la cuenta" for="usuario-rol" error="rol">
                    <select id="usuario-rol" name="rol" required class="campo" @error('rol') aria-invalid="true" aria-describedby="usuario-rol-error" @enderror>
                        <option value="cyber" @selected($rolActual === 'cyber')>Usuario de negocio</option>
                        <option value="admin" @selected($rolActual === 'admin')>Administrador general</option>
                    </select>
                </x-ui.field>
                <p id="usuario-aviso-admin" @class(['mt-3 text-sm text-gray-600', 'hidden' => $rolActual !== 'admin'])>El administrador general tiene acceso a todos los negocios y módulos.</p>

                <fieldset id="usuario-acceso-negocio" @disabled($rolActual !== 'cyber') @class(['mt-4 border-t border-gray-200 pt-4', 'hidden' => $rolActual !== 'cyber'])>
                    <legend class="sr-only">Acceso al negocio</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Negocio" for="usuario-negocio" error="cyber_id" class="sm:col-span-2">
                            <select id="usuario-negocio" name="cyber_id" required class="campo" @error('cyber_id') aria-invalid="true" aria-describedby="usuario-negocio-error" @enderror>
                                <option value="">Seleccione un negocio</option>
                                @foreach($cybers as $cyber)
                                    <option value="{{ $cyber->id }}" @selected($valorAnterior('cyber_id', $usuario->cyber_id) === (string) $cyber->id)>{{ $cyber->nombre }}{{ $cyber->activo ? '' : ' (inactivo)' }}</option>
                                @endforeach
                            </select>
                            @if($cybers->isEmpty())
                                <a href="{{ route('admin.cybers.create') }}" class="mt-2 inline-block text-sm font-semibold text-azul-800 underline">Registrar un negocio</a>
                            @endif
                        </x-ui.field>
                        <x-ui.field label="Perfil de acceso" for="usuario-perfil" error="perfil_acceso">
                            <select id="usuario-perfil" name="perfil_acceso" class="campo" @error('perfil_acceso') aria-invalid="true" aria-describedby="usuario-perfil-error" @enderror>
                                <option value="encargado" @selected($perfilActual === 'encargado')>Encargado del negocio</option>
                                <option value="cajero" @selected($perfilActual === 'cajero')>Cajero / empleado</option>
                            </select>
                        </x-ui.field>
                        <x-ui.field label="Encargado" for="usuario-encargado" error="encargado_id" id="usuario-encargado-campo" :class="$perfilActual === 'cajero' ? '' : 'hidden'">
                            <select id="usuario-encargado" name="encargado_id" class="campo" @disabled($perfilActual !== 'cajero') @required($perfilActual === 'cajero') @error('encargado_id') aria-invalid="true" aria-describedby="usuario-encargado-error" @enderror>
                                <option value="">Seleccione un encargado</option>
                                @foreach($encargados as $encargado)
                                    <option data-cyber="{{ $encargado->cyber_id }}" value="{{ $encargado->id }}" @selected($valorAnterior('encargado_id', $usuario->encargado_id) === (string) $encargado->id)>{{ $encargado->name }}</option>
                                @endforeach
                            </select>
                            <p id="usuario-sin-encargados" class="mt-1.5 hidden text-xs text-gray-600">El negocio seleccionado necesita un encargado activo para asignarle cajeros.</p>
                        </x-ui.field>
                    </div>
                    @include('partials.permisos-selector', ['permisosExpandidos' => false])
                </fieldset>
            @endif
        </x-ui.panel>

        <x-ui.panel title="Contraseña y estado" icon="key-round">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field :label="$editando ? 'Nueva contraseña' : 'Contraseña'" for="usuario-clave" error="password"
                    :hint="$editando ? 'Deje el campo vacío para conservar la contraseña actual.' : 'Use al menos 8 caracteres.'">
                    <input id="usuario-clave" type="password" name="password" @required(!$editando) minlength="8" maxlength="200" autocomplete="new-password" class="campo" @error('password') aria-invalid="true" aria-describedby="usuario-clave-error usuario-clave-ayuda" @else aria-describedby="usuario-clave-ayuda" @enderror>
                </x-ui.field>
                <x-ui.field label="Confirmar contraseña" for="usuario-clave-confirmacion">
                    <input id="usuario-clave-confirmacion" type="password" name="password_confirmation" @required(!$editando) maxlength="200" autocomplete="new-password" class="campo">
                </x-ui.field>
            </div>
            <div class="mt-4 border-t border-gray-200 pt-4">
                <input type="hidden" name="activo" value="{{ $esCuentaPropia ? 1 : 0 }}">
                <label class="flex items-start gap-2 text-sm font-semibold text-gray-800">
                    <input type="checkbox" name="activo" value="1" @checked($esCuentaPropia || old('activo', $usuario->activo)) @disabled($esCuentaPropia) class="casilla mt-0.5">
                    Cuenta activa
                </label>
                <p class="ml-6 mt-1 text-xs text-gray-500">{{ $esCuentaPropia ? 'Su cuenta debe permanecer activa.' : 'La cuenta activa puede iniciar sesión.' }}</p>
            </div>
        </x-ui.panel>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button :href="$lista" variant="secondary">Cancelar</x-ui.button>
            <x-ui.button type="submit" :icon="$editando ? 'check' : 'user-plus'">{{ $editando ? 'Guardar cambios' : 'Crear usuario' }}</x-ui.button>
        </div>
    </form>

    @unless($usuario->esCliente())
        @push('scripts')
            <script>
                (() => {
                    const rol = document.getElementById('usuario-rol');
                    const aviso = document.getElementById('usuario-aviso-admin');
                    const acceso = document.getElementById('usuario-acceso-negocio');
                    const negocio = document.getElementById('usuario-negocio');
                    const perfil = document.getElementById('usuario-perfil');
                    const encargado = document.getElementById('usuario-encargado');
                    const campoEncargado = document.getElementById('usuario-encargado-campo');
                    const sinEncargados = document.getElementById('usuario-sin-encargados');
                    const opciones = Array.from(encargado.querySelectorAll('option[data-cyber]'));

                    function actualizarAcceso() {
                        const esNegocio = rol.value === 'cyber';
                        const esCajero = perfil.value === 'cajero';
                        acceso.hidden = !esNegocio;
                        acceso.classList.toggle('hidden', !esNegocio);
                        acceso.disabled = !esNegocio;
                        aviso.classList.toggle('hidden', esNegocio);
                        campoEncargado.classList.toggle('hidden', !esCajero);
                        encargado.disabled = !esCajero;
                        encargado.required = esCajero;
                        let disponibles = 0;

                        opciones.forEach(opcion => {
                            const pertenece = negocio.value !== '' && opcion.dataset.cyber === negocio.value;
                            opcion.hidden = !pertenece;
                            opcion.disabled = !pertenece;
                            if (pertenece) disponibles++;
                            if (!pertenece && opcion.selected) encargado.value = '';
                        });
                        sinEncargados.classList.toggle('hidden', !negocio.value || disponibles > 0);
                    }

                    rol.addEventListener('change', actualizarAcceso);
                    negocio.addEventListener('change', actualizarAcceso);
                    perfil.addEventListener('change', () => {
                        acceso.querySelector('[data-perfil="' + perfil.value + '"]')?.click();
                        actualizarAcceso();
                    });
                    actualizarAcceso();
                })();
            </script>
        @endpush
    @endunless
</x-layout>
