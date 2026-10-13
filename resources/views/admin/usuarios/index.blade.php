<x-layout titulo="Usuarios" activo="usuarios">
    <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
        <p class="text-gray-500">Administradores (ven todo), usuarios de cada Cyber (ven solo los clientes de su Cyber) y usuarios registrados (personas que hacen su propio CV).</p>
        <a href="{{ route('admin.usuarios.create', $cyberId !== '' ? ['cyber_id' => $cyberId] : []) }}" class="inline-flex items-center gap-2 bg-oro-500 hover:bg-oro-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors shrink-0">
            <i data-lucide="user-plus" class="size-4"></i> Crear usuario
        </a>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <form method="GET" class="flex flex-wrap gap-3 items-end mb-5">
            <div class="flex-1 min-w-[200px] relative">
                <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Buscar</label>
                <i data-lucide="search" class="absolute left-3 top-[34px] size-4 text-gray-400"></i>
                <input type="search" name="q" value="{{ $buscar }}" placeholder="Nombre, usuario o correo" class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5">
            </div>
            <div class="min-w-[200px]">
                <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Cyber</label>
                <select name="cyber_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    <option value="">Todos</option>
                    @foreach($cybers as $cy)<option value="{{ $cy->id }}" @selected($cyberId === (string) $cy->id)>{{ $cy->nombre }}</option>@endforeach
                </select>
            </div>
            <div class="min-w-[170px]">
                <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Tipo</label>
                <select name="rol" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    <option value="">Todos</option>
                    @foreach(\App\Models\User::ROLES as $k => $t)<option value="{{ $k }}" @selected(request('rol') === $k)>{{ $t }}</option>@endforeach
                </select>
            </div>
            <button class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-2.5 rounded-lg"><i data-lucide="search" class="size-4"></i> Buscar</button>
        </form>

        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs uppercase text-gray-500 border-b">
                <th class="py-2 pr-3">Usuario</th><th class="py-2 pr-3">Nombre</th><th class="py-2 pr-3">Rol / Cyber</th><th class="py-2 pr-3">Estado</th><th class="py-2 pr-3">Último acceso</th><th class="py-2"></th>
            </tr></thead>
            <tbody>
            @forelse($usuarios as $u)
                <tr class="border-b last:border-0">
                    <td class="py-2.5 pr-3 font-mono text-xs font-semibold">{{ $u->usuario }}</td>
                    <td class="py-2.5 pr-3">{{ $u->name }}<div class="text-xs text-gray-400">{{ $u->email }}</div></td>
                    <td class="py-2.5 pr-3">
                        @if($u->esAdmin())
                            <span class="px-2 py-0.5 rounded-full bg-oro-500/15 text-oro-700 text-xs font-bold uppercase">Admin</span>
                        @elseif($u->esCliente())
                            <span class="px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 text-xs font-semibold">Cliente con cuenta</span><small class="block text-gray-500">{{ $u->cliente?->cybers->pluck('nombre')->implode(', ') }}</small>
                            @if($u->verificado())<span class="px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-semibold">✓ verificado</span>
                            @else<span class="px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[10px] font-semibold">sin verificar</span>@endif
                        @else
                            <span class="px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold">{{ $u->cyber?->nombre ?? 'Sin cyber' }}</span><small class="block text-gray-500">{{ $u->encargado ? 'Cajero de '.$u->encargado->name : 'Encargado' }}</small>
                        @endif
                    </td>
                    <td class="py-2.5 pr-3"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $u->activo ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">{{ $u->activo ? 'Activo' : 'Inactivo' }}</span></td>
                    <td class="py-2.5 pr-3 text-xs text-gray-500">{{ $u->ultimo_acceso?->diffForHumans() ?? 'Nunca' }}</td>
                    <td class="py-2.5 whitespace-nowrap">
                        <a href="{{ route('admin.usuarios.edit', $u) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-azul-800 hover:text-oro-700"><i data-lucide="pencil" class="size-3.5"></i> Editar</a>
                        @if($u->esCliente() && !$u->verificado())
                            <form method="POST" action="{{ route('admin.usuarios.verificar', $u) }}" class="inline ml-2">@csrf
                                <button class="text-xs font-semibold text-emerald-700">Marcar verificado</button></form>
                        @endif
                        @unless($u->is(auth()->user()))
                            <form method="POST" action="{{ route('admin.usuarios.toggle', $u) }}" class="inline ml-2">@csrf
                                <button class="text-xs font-semibold {{ $u->activo ? 'text-red-600' : 'text-emerald-600' }}">{{ $u->activo ? 'Desactivar' : 'Activar' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.usuarios.destroy', $u) }}" class="inline ml-2" onsubmit="return confBorrar(event, @js($u->usuario))">@csrf @method('DELETE')
                                <button class="text-xs font-semibold text-gray-400 hover:text-red-700"><i data-lucide="trash-2" class="size-3.5 inline"></i></button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-gray-400">Sin usuarios.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        @include('partials.paginacion', ['p' => $usuarios])
    </div>
    @push('scripts')
    <script>function confBorrar(e,u){e.preventDefault();Swal.fire({title:'¿Eliminar al usuario '+u+'?',icon:'warning',showCancelButton:true,confirmButtonColor:'#c0392b',confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar'}).then(r=>{if(r.isConfirmed)e.target.submit();});return false;}</script>
    @endpush
</x-layout>
