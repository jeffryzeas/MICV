@php
    $tabs = ['pendiente' => 'Pendientes', 'aprobada' => 'Aprobadas', 'rechazada' => 'Rechazadas', 'cancelada' => 'Canceladas', 'todas' => 'Todas'];
    $color = ['pendiente' => 'bg-amber-50 text-amber-700', 'aprobada' => 'bg-emerald-50 text-emerald-700', 'rechazada' => 'bg-red-50 text-red-700', 'cancelada' => 'bg-gray-100 text-gray-600'];
@endphp
<x-layout titulo="Solicitudes de eliminación" activo="solicitudes">
    <p class="text-gray-500 mb-4 text-sm">Las personas piden borrar su cuenta desde dentro de la app (Privacidad y cuenta), confirmando con su contraseña. Al aprobar, se borran su usuario, su foto, su CV y sus códigos, y se le avisa por correo. Atiéndalas pronto: las leyes de protección de datos fijan plazos cortos para responder.</p>

    <div class="flex flex-wrap gap-2 mb-4">
        @foreach($tabs as $k => $t)
            <a href="{{ route('admin.solicitudes.index', ['estado' => $k]) }}" class="px-3 py-1.5 rounded-full text-sm font-semibold border {{ $estado === $k ? 'bg-azul-800 text-white border-azul-800' : 'bg-white text-gray-600 border-gray-200 hover:border-azul-800' }}">{{ $t }}</a>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse($solicitudes as $s)
            @php $u = $s->usuario; @endphp
            <div class="bg-white rounded-xl shadow p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-semibold text-azul-950 break-words">{{ $s->nombre ?: ($u?->name ?? 'Sin nombre') }}</div>
                        <div class="text-sm text-gray-500 break-all">{{ $s->email }}</div>
                        <div class="text-xs text-gray-400 mt-1">
                            #{{ $s->id }} · {{ $s->created_at->format('d/m/Y H:i') }} · desde {{ $s->origen === 'publica' ? 'la página pública' : 'su panel' }}
                            @if($u) · cuenta: {{ \App\Models\User::ROLES[$u->rol] ?? $u->rol }} @else · <span class="text-red-500">sin cuenta con ese correo</span>@endif
                        </div>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $color[$s->estado] ?? '' }}">{{ \App\Models\SolicitudEliminacion::ESTADOS[$s->estado] ?? $s->estado }}</span>
                </div>
                @if($s->motivo)<p class="mt-3 text-sm text-gray-700 bg-gray-50 rounded-lg p-3"><b>Motivo:</b> {{ $s->motivo }}</p>@endif
                @if($s->nota_admin)<p class="mt-3 text-sm text-gray-700"><b>Nota:</b> {{ $s->nota_admin }}</p>@endif
                @if($s->atendida_en)<p class="text-xs text-gray-400 mt-1">Atendida {{ $s->atendida_en->format('d/m/Y H:i') }} por {{ $s->atendidaPor?->name ?? '—' }}</p>@endif

                @if($s->estado === 'pendiente')
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                        <form method="POST" action="{{ route('admin.solicitudes.aprobar', $s) }}" class="border border-emerald-200 rounded-lg p-3 space-y-2 jz-confirmar" data-msg="Se eliminará la cuenta de {{ $s->email }}. Esto no se puede deshacer.">
                            @csrf
                            @if($u?->esCliente())
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="borrar_ficha" value="1" checked> Borrar también su ficha y CV</label>
                            @else
                                <input type="hidden" name="borrar_ficha" value="1">
                            @endif
                            <input type="text" name="nota" maxlength="1000" placeholder="Nota (opcional)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <button class="w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg"><i data-lucide="check" class="size-4"></i> Aprobar y eliminar</button>
                        </form>
                        <form method="POST" action="{{ route('admin.solicitudes.rechazar', $s) }}" class="border border-red-200 rounded-lg p-3 space-y-2">
                            @csrf
                            <textarea name="nota" required maxlength="1000" rows="2" placeholder="Motivo del rechazo (se le envía a la persona)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                            <button class="w-full inline-flex items-center justify-center gap-2 bg-white border border-red-300 text-red-700 hover:bg-red-50 text-sm font-semibold px-4 py-2 rounded-lg"><i data-lucide="x" class="size-4"></i> Rechazar</button>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-xl shadow p-10 text-center text-gray-400">
                <i data-lucide="inbox" class="size-10 mx-auto mb-2"></i> No hay solicitudes {{ $estado === 'todas' ? '' : strtolower($tabs[$estado] ?? '') }}.
            </div>
        @endforelse
    </div>
    <div class="mt-4">@include('partials.paginacion', ['p' => $solicitudes])</div>

    <script>
    document.querySelectorAll('form.jz-confirmar').forEach(f => f.addEventListener('submit', e => {
        if (f.dataset.ok) return;
        e.preventDefault();
        Swal.fire({ icon: 'warning', title: '¿Eliminar la cuenta?', text: f.dataset.msg, showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#059669' })
            .then(r => { if (r.isConfirmed) { f.dataset.ok = 1; f.submit(); } });
    }));
    </script>
</x-layout>
