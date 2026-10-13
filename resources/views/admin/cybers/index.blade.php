<x-layout titulo="Cybers" activo="cybers">
    <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
        <p class="text-gray-500">Cada Cyber ve solo sus clientes. El logo y los colores también los puede cambiar el propio Cyber desde "Mi marca".</p>
        <a href="{{ route('admin.cybers.create') }}" class="inline-flex items-center gap-2 bg-oro-500 hover:bg-oro-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors shrink-0">
            <i data-lucide="plus" class="size-4"></i> Registrar Cyber
        </a>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase text-gray-500 border-b">
                    <th class="py-2 pr-3">Logo</th><th class="py-2 pr-3">Código</th><th class="py-2 pr-3">Nombre</th>
                    <th class="py-2 pr-3">Colores</th><th class="py-2 pr-3">Clientes</th><th class="py-2 pr-3">Usuarios</th><th class="py-2 pr-3">Estado</th><th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($cybers as $s)
                <tr class="border-b">
                    <td class="py-2.5 pr-3">
                        @if($s->logoUrl())<img src="{{ $s->logoUrl() }}" class="size-10 object-contain rounded bg-gray-100 border" alt="">@else<span class="text-gray-300">—</span>@endif
                    </td>
                    <td class="py-2.5 pr-3 font-mono text-xs">{{ $s->codigo }}</td>
                    <td class="py-2.5 pr-3 font-medium">{{ $s->nombre }}<div class="text-xs text-gray-400">{{ $s->telefono }}</div></td>
                    <td class="py-2.5 pr-3">
                        <span class="inline-block size-4 rounded align-middle" style="background:{{ $s->color_primario }}"></span>
                        <span class="inline-block size-4 rounded align-middle" style="background:{{ $s->color_secundario }}"></span>
                    </td>
                    <td class="py-2.5 pr-3">{{ $s->clientes_count }}</td>
                    <td class="py-2.5 pr-3">{{ $s->usuarios_count }}</td>
                    <td class="py-2.5 pr-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $s->activo ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                            {{ $s->activo ? 'Activo' : 'En mantenimiento' }}
                        </span>
                    </td>
                    <td class="py-2.5">
                        <div class="flex flex-wrap gap-3 items-center">
                            <a href="{{ route('admin.cybers.edit', $s) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-azul-800 hover:text-oro-700"><i data-lucide="pencil" class="size-3.5"></i> Editar</a>
                            <a href="{{ route('admin.usuarios.index', ['cyber_id' => $s->id]) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 hover:text-azul-800"><i data-lucide="user-cog" class="size-3.5"></i> Usuarios</a>
                            <a href="{{ route('admin.usuarios.create', ['cyber_id' => $s->id]) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 hover:text-azul-800"><i data-lucide="user-plus" class="size-3.5"></i> Crear usuario</a>
                            <a href="{{ route('clientes.index', ['cyber_id' => $s->id]) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 hover:text-azul-800"><i data-lucide="list" class="size-3.5"></i> Clientes</a>
                            <form method="POST" action="{{ route('admin.cybers.toggle', $s) }}" onsubmit="return confToggle(event, {{ $s->activo ? 'true' : 'false' }})">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1 text-xs font-semibold {{ $s->activo ? 'text-red-600 hover:text-red-800' : 'text-emerald-600 hover:text-emerald-800' }}">
                                    <i data-lucide="{{ $s->activo ? 'power-off' : 'power' }}" class="size-3.5"></i> {{ $s->activo ? 'Mantenimiento' : 'Reactivar' }}
                                </button>
                            </form>
                            @if($s->usuarios_count === 0)
                            <form method="POST" action="{{ route('admin.cybers.destroy', $s) }}" onsubmit="return confBorrar(event)">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-1 text-xs font-semibold text-gray-400 hover:text-red-700"><i data-lucide="trash-2" class="size-3.5"></i> Eliminar</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-8 text-center text-gray-400">Aún no hay Cybers registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @push('scripts')
    <script>
    function confToggle(e, activo){e.preventDefault();Swal.fire({title: activo ? '¿Poner en mantenimiento?' : '¿Reactivar el Cyber?',text: activo ? 'Sus usuarios no podrán entrar mientras esté en mantenimiento.' : '',icon:'warning',showCancelButton:true,confirmButtonColor:'#0B3C74',confirmButtonText:'Sí',cancelButtonText:'Cancelar'}).then(r=>{if(r.isConfirmed)e.target.submit();});return false;}
    function confBorrar(e){e.preventDefault();Swal.fire({title:'¿Eliminar este Cyber?',text:'Sus clientes no se borran, solo dejan de estar en este Cyber.',icon:'warning',showCancelButton:true,confirmButtonColor:'#c0392b',confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar'}).then(r=>{if(r.isConfirmed)e.target.submit();});return false;}
    </script>
    @endpush
</x-layout>
