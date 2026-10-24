@php $editando = $usuario->exists; $rolSel = old('rol', $usuario->rol); @endphp
<x-layout :titulo="$editando ? 'Editar usuario ' . $usuario->usuario : 'Crear usuario'" activo="usuarios">
    <a href="{{ route('admin.usuarios.index') }}" class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg shadow-sm transition-colors mb-5">
        <x-icono name="arrow-left" class="size-4" /> Volver
    </a>
    <div class="bg-white rounded-xl shadow p-6 max-w-3xl">
        <form method="POST" action="{{ $editando ? route('admin.usuarios.update', $usuario) : route('admin.usuarios.store') }}">
            @csrf @if($editando) @method('PUT') @endif

            @if($usuario->esCliente())
                <input type="hidden" name="rol" value="cliente">
                <div class="mb-5 rounded-lg bg-purple-50 border border-purple-200 text-purple-800 px-4 py-3 text-sm">Esta es una <b>persona que se registró sola</b> para hacer su propio CV. Puede cambiar sus datos de acceso o su contraseña.</div>
            @else
            <p class="text-xs font-semibold uppercase text-azul-800 mb-2">Tipo de usuario</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
                @foreach(['admin' => \App\Models\User::ROLES['admin'], 'cyber' => \App\Models\User::ROLES['cyber']] as $k => $t)
                    <label class="flex items-start gap-3 border-2 rounded-lg p-3 cursor-pointer {{ $rolSel === $k ? 'border-azul-800 bg-blue-50' : 'border-gray-200' }}">
                        <input type="radio" name="rol" value="{{ $k }}" @checked($rolSel === $k) class="mt-1" onchange="jzRol()">
                        <span><b class="text-azul-950">{{ $t }}</b><br><span class="text-xs text-gray-500">{{ $k === 'admin' ? 'Ve todos los Negocios y clientes, crea Negocios y usuarios.' : 'Registra clientes y genera CV. Solo ve los de su Negocio.' }}</span></span>
                    </label>
                @endforeach
            </div>
            @endif

            <div id="bloqueCyber" class="mb-4 {{ $rolSel !== 'cyber' ? 'hidden' : '' }}">
                <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Negocio</label>
                @if($cybers->isEmpty())
                    <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
                        Primero <a href="{{ route('admin.cybers.create') }}" class="font-semibold underline">registre un Negocio</a>.
                    </div>
                @else
                    <select name="cyber_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                        <option value="">— Seleccione —</option>
                        @foreach($cybers as $cy)
                            <option value="{{ $cy->id }}" @selected((string) old('cyber_id', $usuario->cyber_id) === (string) $cy->id)>{{ $cy->nombre }}{{ $cy->activo ? '' : ' (mantenimiento)' }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            @unless($usuario->esCliente())
            <div id="bloqueAccesoCyber" class="{{ $rolSel !== 'cyber' ? 'hidden' : '' }}">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <label class="text-sm">Perfil del usuario de Negocio<select name="perfil_acceso" class="w-full border rounded-lg p-2"><option value="encargado" @selected(old('perfil_acceso',$usuario->encargado_id?'cajero':'encargado')==='encargado')>Encargado del negocio</option><option value="cajero" @selected(old('perfil_acceso',$usuario->encargado_id?'cajero':'encargado')==='cajero')>Cajero / empleado</option></select></label>
                <label class="text-sm">Encargado (para cajeros)<select name="encargado_id" class="w-full border rounded-lg p-2"><option value="">Seleccione</option>@foreach($encargados as $enc)<option data-cyber="{{ $enc->cyber_id }}" value="{{ $enc->id }}" @selected(old('encargado_id',$usuario->encargado_id)==$enc->id)>{{ $enc->name }} — {{ $enc->cyber?->nombre }}</option>@endforeach</select></label>
            </div>
            @include('partials.permisos-selector')
            </div>
            @endunless
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Nombre completo</label>
                    <input type="text" name="name" value="{{ old('name', $usuario->name) }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Usuario (para entrar)</label>
                    <input type="text" name="usuario" value="{{ old('usuario', $usuario->usuario) }}" required autocomplete="off" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 uppercase">
                    <p class="text-[11px] text-gray-400 mt-1">Sin espacios. Se guarda en MAYÚSCULAS.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Correo</label>
                    <input type="email" name="email" value="{{ old('email', $usuario->email) }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">{{ $editando ? 'Nueva contraseña (opcional)' : 'Contraseña' }}</label>
                    <input type="password" name="password" {{ $editando ? '' : 'required' }} autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    <p class="text-[11px] text-gray-400 mt-1">Mínimo 8 caracteres.{{ $editando ? ' Vacía = no se cambia.' : '' }}</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Repetir contraseña</label>
                    <input type="password" name="password_confirmation" autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                </div>
                <div class="sm:col-span-2">
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="activo" value="1" @checked(old('activo', $usuario->activo ?? true))> Usuario activo (puede iniciar sesión)</label>
                </div>
            </div>
            @if($usuario->esCliente())<x-permisos-cliente :usuario="$usuario"/>@endif
            <button type="submit" class="mt-6 inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                <x-icono name="check" class="size-4" /> {{ $editando ? 'Guardar cambios' : 'Crear usuario' }}
            </button>
        </form>
    </div>
    @push('scripts')
    <script>
    function jzRol(){
        const r = document.querySelector('input[name=rol]:checked');
        document.getElementById('bloqueAccesoCyber')?.classList.toggle('hidden', !r || r.value !== 'cyber');
        document.getElementById('bloqueCyber').classList.toggle('hidden', !r || r.value === 'admin');
        document.querySelectorAll('input[name=rol]').forEach(i => {
            const l = i.closest('label'); l.classList.toggle('border-azul-800', i.checked); l.classList.toggle('bg-blue-50', i.checked); l.classList.toggle('border-gray-200', !i.checked);
        });
    }
    const perfilAcceso=document.querySelector('[name=perfil_acceso]');
    perfilAcceso?.addEventListener('change',()=>document.querySelector('[data-permisos-selector] [data-perfil="'+(perfilAcceso.value==='cajero'?'cajero':'encargado')+'"]')?.click());
    function filtrarEncargados(){const c=document.querySelector('[name=cyber_id]'),e=document.querySelector('[name=encargado_id]');if(!c||!e)return;e.querySelectorAll('option[data-cyber]').forEach(o=>{o.hidden=o.dataset.cyber!==c.value;if(o.hidden&&o.selected)e.value='';});}
    document.querySelector('[name=cyber_id]')?.addEventListener('change',filtrarEncargados);filtrarEncargados();
    </script>
    @endpush
</x-layout>
