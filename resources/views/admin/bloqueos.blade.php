@php $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5'; $lb = 'block text-xs font-semibold uppercase text-azul-800 mb-1'; @endphp
<x-layout titulo="Lista privada" activo="bloqueos">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="bg-white rounded-xl shadow p-5 sm:p-6">
            <h2 class="font-semibold text-azul-950 mb-1 flex items-center gap-2"><x-icono name="shield-ban" class="size-[18px] text-red-500" /> Bloquear un documento</h2>
            <p class="text-xs text-gray-500 mb-4">Ningún Negocio podrá registrar, consultar ni ver a la persona con ese número. Un usuario registrado tampoco podrá poner ese documento en su ficha. Solo usted (admin) la sigue viendo.</p>
            <form method="POST" action="{{ route('admin.bloqueos.store') }}" class="space-y-4">
                @csrf
                <div><label class="{{ $lb }}">Número de documento *</label>
                    <input type="text" name="numero" value="{{ old('numero') }}" required maxlength="40" placeholder="Ej: 001-150890-1234A" class="{{ $in }} font-mono uppercase"></div>
                <div><label class="{{ $lb }}">Motivo (solo lo ve el admin)</label>
                    <input type="text" name="motivo" value="{{ old('motivo') }}" maxlength="255" placeholder="Ej: lo pidió la persona / orden judicial" class="{{ $in }}"></div>
                <label class="flex items-start gap-2 text-sm text-gray-600"><input type="checkbox" name="desactivar_cuenta" value="1" class="mt-1"> También desactivar la cuenta de usuario que tenga ese documento (no podrá entrar).</label>
                <button class="w-full inline-flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-5 py-2.5 rounded-lg"><x-icono name="lock" class="size-4" /> Agregar a la lista privada</button>
            </form>
        </div>
        <div class="lg:col-span-2 bg-white rounded-xl shadow p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <h2 class="font-semibold text-azul-950">Documentos bloqueados <span class="text-gray-400 font-normal">({{ $lista->total() }})</span></h2>
                <form class="flex gap-2"><input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar número" class="border border-gray-300 rounded-lg px-3 py-2 text-sm"><button class="bg-gray-100 hover:bg-gray-200 rounded-lg px-3 text-sm"><x-icono name="search" class="size-4" /></button></form>
            </div>
            <div class="divide-y">
                @forelse($lista as $b)
                    <div class="py-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-mono font-semibold text-azul-950">{{ $b->numero }}</div>
                            <div class="text-xs text-gray-500">{{ $b->motivo ?: 'Sin motivo' }} · {{ $b->created_at->format('d/m/Y') }} · {{ $b->autor?->name ?? '—' }}</div>
                        </div>
                        <form method="POST" action="{{ route('admin.bloqueos.destroy', $b) }}" class="jz-confirmar" data-msg="El documento {{ $b->numero }} volverá a estar disponible para los Negocios.">
                            @csrf @method('DELETE')
                            <button class="inline-flex items-center gap-1.5 text-sm border border-gray-300 hover:border-red-400 hover:text-red-600 rounded-lg px-3 py-1.5"><x-icono name="unlock" class="size-4" /> Quitar</button>
                        </form>
                    </div>
                @empty
                    <p class="py-10 text-center text-gray-400">La lista privada está vacía.</p>
                @endforelse
            </div>
            <div class="mt-4">@include('partials.paginacion', ['p' => $lista])</div>
        </div>
    </div>
    <script>
    document.querySelectorAll('form.jz-confirmar').forEach(f => f.addEventListener('submit', e => {
        if (f.dataset.ok) return; e.preventDefault();
        Swal.fire({ icon: 'question', title: '¿Quitar de la lista privada?', text: f.dataset.msg, showCancelButton: true, confirmButtonText: 'Sí, quitar', cancelButtonText: 'Cancelar', confirmButtonColor: '#0B3C74' })
            .then(r => { if (r.isConfirmed) { f.dataset.ok = 1; f.submit(); } });
    }));
    </script>
</x-layout>
