<x-layout titulo="Usuarios" activo="usuarios">
    <div class="page-heading">
        <div>
            <h2 class="page-title">Cuentas del sistema</h2>
            <p class="mt-1 text-sm text-gray-500">Administre el acceso de administradores, equipos de negocio y clientes.</p>
        </div>
        <x-ui.button :href="route('admin.usuarios.create', $cyberId !== '' ? ['cyber_id' => $cyberId] : [])" icon="user-plus" class="w-full sm:w-auto">
            Crear usuario
        </x-ui.button>
    </div>

    <x-ui.panel title="Buscar y filtrar" icon="list-filter" class="mb-5">
        <form method="GET" action="{{ route('admin.usuarios.index') }}" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.field label="Buscar" for="usuarios-buscar" class="sm:col-span-2 xl:col-span-4">
                <input id="usuarios-buscar" type="search" name="q" value="{{ $buscar }}" placeholder="Nombre, usuario o correo" maxlength="150" class="campo">
            </x-ui.field>
            <x-ui.field label="Negocio" for="usuarios-negocio">
                <select id="usuarios-negocio" name="cyber_id" class="campo">
                    <option value="">Todos</option>
                    @foreach($cybers as $cyber)
                        <option value="{{ $cyber->id }}" @selected($cyberId === (string) $cyber->id)>{{ $cyber->nombre }}</option>
                    @endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Rol" for="usuarios-rol">
                <select id="usuarios-rol" name="rol" class="campo">
                    <option value="">Todos</option>
                    @foreach(['admin' => 'Administrador general', 'cyber' => 'Usuario de negocio', 'cliente' => 'Cliente'] as $clave => $etiqueta)
                        <option value="{{ $clave }}" @selected($rol === $clave)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </x-ui.field>
            <x-ui.field label="Estado" for="usuarios-estado">
                <select id="usuarios-estado" name="estado" class="campo">
                    <option value="">Todos</option>
                    <option value="activo" @selected($estado === 'activo')>Activo</option>
                    <option value="inactivo" @selected($estado === 'inactivo')>Inactivo</option>
                </select>
            </x-ui.field>
            <x-ui.field label="Correo del cliente" for="usuarios-verificacion">
                <select id="usuarios-verificacion" name="verificacion" class="campo">
                    <option value="">Cualquier estado</option>
                    <option value="verificado" @selected($verificacion === 'verificado')>Verificado</option>
                    <option value="pendiente" @selected($verificacion === 'pendiente')>Pendiente de verificar</option>
                </select>
            </x-ui.field>
            <div class="flex flex-wrap gap-2 sm:col-span-2 xl:col-span-4">
                <x-ui.button type="submit" icon="search">Buscar</x-ui.button>
                @if($hayFiltros)
                    <x-ui.button :href="route('admin.usuarios.index')" variant="secondary" icon="x">Limpiar filtros</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.panel>

    <x-ui.panel :title="$usuarios->total() === 1 ? '1 cuenta' : $usuarios->total().' cuentas'" icon="users">
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($usuarios as $cuenta)
                <li class="grid items-start gap-x-4 gap-y-3 py-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(0,1.4fr)_minmax(0,1.2fr)_auto]">
                    <div class="flex min-w-0 items-start gap-3">
                        <span aria-hidden="true" class="flex size-10 shrink-0 items-center justify-center rounded-full bg-blue-50 text-sm font-bold text-azul-800 dark:bg-gray-700">{{ $cuenta->iniciales() }}</span>
                        <div class="min-w-0 break-words text-sm">
                            <h3 class="font-semibold text-azul-950">{{ $cuenta->name }}</h3>
                            <p class="text-gray-700">{{ $cuenta->usuario }} @if($cuenta->is(auth()->user()))<span class="text-xs text-gray-500">· Su cuenta</span>@endif</p>
                            <p class="mt-1 text-gray-500">{{ $cuenta->email }}</p>
                        </div>
                    </div>
                    <div class="min-w-0 break-words text-sm">
                        @if($cuenta->esCliente())
                            <p class="font-semibold text-gray-800">Cliente</p>
                            @if($cuenta->cliente?->cybers->isNotEmpty())
                                <p class="mt-1 text-gray-500">{{ $cuenta->cliente->cybers->pluck('nombre')->join(', ') }}</p>
                            @endif
                        @elseif($cuenta->esCyber())
                            <p class="font-semibold text-gray-800">{{ $cuenta->encargado_id ? 'Cajero / empleado' : 'Encargado' }}</p>
                            <p class="mt-1 text-gray-500">{{ $cuenta->cyber?->nombre ?? 'Sin negocio asignado' }}</p>
                            @if($cuenta->encargado)<p class="text-xs text-gray-500">Encargado {{ $cuenta->encargado->name }}</p>@endif
                        @else
                            <p class="font-semibold text-gray-800">{{ $cuenta->rolLegible() }}</p>
                        @endif
                    </div>
                    <div class="min-w-0 space-y-1.5 text-xs">
                        <span @class([
                            'inline-flex rounded-full px-2 py-1 font-semibold',
                            'bg-emerald-50 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' => $cuenta->activo,
                            'bg-gray-100 text-gray-700 dark:bg-gray-700' => !$cuenta->activo,
                        ])>{{ $cuenta->activo ? 'Activo' : 'Inactivo' }}</span>
                        @if($cuenta->esCliente())
                            <p @class(['font-medium', 'text-emerald-700 dark:text-emerald-300' => $cuenta->verificado(), 'text-amber-800 dark:text-amber-200' => !$cuenta->verificado()])>
                                {{ $cuenta->verificado() ? 'Correo verificado' : 'Correo pendiente' }}
                            </p>
                        @endif
                        <p class="text-gray-500">Último acceso
                            @if($cuenta->ultimo_acceso)
                                <time datetime="{{ $cuenta->ultimo_acceso->toIso8601String() }}" title="{{ $cuenta->ultimo_acceso->format('d/m/Y H:i') }}">{{ $cuenta->ultimo_acceso->diffForHumans() }}</time>
                            @else
                                · Nunca
                            @endif
                        </p>
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-start gap-2 sm:justify-end">
                            <x-ui.button :href="route('admin.usuarios.edit', $cuenta)" variant="secondary" icon="pencil" :aria-label="'Editar a '.$cuenta->name">Editar</x-ui.button>
                        </div>
                        @unless($cuenta->is(auth()->user()))
                            <details class="mt-2 text-sm sm:text-right">
                                <summary class="cursor-pointer rounded-lg px-2 py-2 font-medium text-azul-800 focus-visible:outline-azul-800" aria-label="Más acciones para {{ $cuenta->name }}">Más acciones</summary>
                                <div class="mt-2 grid justify-items-start gap-2 sm:justify-items-end">
                                    @if($cuenta->esCliente() && !$cuenta->verificado())
                                        <form method="POST" action="{{ route('admin.usuarios.verificar', $cuenta) }}" data-confirmar="¿Marcar como verificado el correo {{ $cuenta->email }}?">
                                            @csrf
                                            <input type="hidden" name="email" value="{{ $cuenta->email }}">
                                            <x-ui.button type="submit" variant="secondary" icon="mail-check">Verificar correo</x-ui.button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.usuarios.toggle', $cuenta) }}" @if($cuenta->activo) data-confirmar="¿Desactivar la cuenta {{ $cuenta->usuario }}?" @endif>
                                        @csrf
                                        <input type="hidden" name="activo" value="{{ $cuenta->activo ? 0 : 1 }}">
                                        <x-ui.button type="submit" variant="secondary" icon="power">{{ $cuenta->activo ? 'Desactivar' : 'Activar' }}</x-ui.button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.usuarios.destroy', $cuenta) }}" data-confirmar="¿Eliminar la cuenta {{ $cuenta->usuario }}?" data-detalle="La eliminación de la cuenta es permanente." data-peligro>
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button type="submit" variant="danger" icon="trash-2">Eliminar cuenta</x-ui.button>
                                    </form>
                                </div>
                            </details>
                        @endunless
                    </div>
                </li>
            @empty
                <li class="py-8 text-center">
                    <x-icono :name="$hayFiltros ? 'search-x' : 'users'" class="mb-2 size-8 text-gray-500" />
                    <p class="font-semibold text-azul-950">{{ $hayFiltros ? 'Ninguna cuenta coincide con los filtros' : 'Aún no hay usuarios' }}</p>
                    @if($hayFiltros)
                        <x-ui.button :href="route('admin.usuarios.index')" variant="secondary" class="mt-4">Ver todas las cuentas</x-ui.button>
                    @endif
                </li>
            @endforelse
        </ul>

        @if($usuarios->hasPages())
            <div class="border-t border-gray-200 pt-2">
                @include('partials.paginacion', ['p' => $usuarios])
            </div>
        @endif
    </x-ui.panel>

    @push('scripts')
        <script>
            const filtroRol = document.getElementById('usuarios-rol');
            const filtroCorreo = document.getElementById('usuarios-verificacion');
            function actualizarFiltroCorreo() {
                filtroCorreo.disabled = filtroRol.value !== '' && filtroRol.value !== 'cliente';
                if (filtroCorreo.disabled) filtroCorreo.value = '';
            }
            filtroRol.addEventListener('change', actualizarFiltroCorreo);
            actualizarFiltroCorreo();

            document.querySelectorAll('form[data-confirmar]').forEach(form => {
                form.addEventListener('submit', async event => {
                    event.preventDefault();
                    const title = form.dataset.confirmar;
                    const description = form.dataset.detalle;
                    const confirmed = window.Swal
                        ? (await Swal.fire({
                            titleText: title, text: description, icon: 'warning', showCancelButton: true,
                            focusCancel: true, confirmButtonText: form.hasAttribute('data-peligro') ? 'Eliminar cuenta' : 'Confirmar',
                            cancelButtonText: 'Cancelar',
                        })).isConfirmed
                        : window.confirm([title, description].filter(Boolean).join('\n'));
                    if (confirmed) HTMLFormElement.prototype.submit.call(form);
                });
            });
        </script>
    @endpush
</x-layout>
