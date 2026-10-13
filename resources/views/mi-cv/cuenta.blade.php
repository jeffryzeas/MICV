<x-layout titulo="Privacidad y cuenta" activo="cuenta">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-azul-950 mb-2 flex items-center gap-2"><i data-lucide="download" class="size-[18px] text-oro-500"></i> Descargar mis datos</h2>
            <p class="text-sm text-gray-500 mb-4">Reciba una copia de toda la información que guardamos de usted, en un archivo (JSON).</p>
            <a href="{{ route('mi-cuenta.descargar') }}" class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-2.5 rounded-lg"><i data-lucide="download" class="size-4"></i> Descargar mis datos</a>
            <hr class="my-5">
            <h3 class="text-sm font-bold uppercase text-azul-800 mb-2">Mis aceptaciones</h3>
            <p class="text-sm text-gray-600">Términos y privacidad, versión <b>{{ $user->terminos_version ?? '—' }}</b>, aceptados el {{ $user->terminos_aceptados_en?->format('d/m/Y h:i A') ?? '—' }}.</p>
            <div class="flex flex-wrap gap-3 mt-3 text-sm">
                <a href="{{ route('legal.ver', 'terminos') }}" target="_blank" class="underline text-azul-800">Términos</a>
                <a href="{{ route('legal.ver', 'privacidad') }}" target="_blank" class="underline text-azul-800">Privacidad</a>
                <a href="{{ route('legal.ver', 'cookies') }}" target="_blank" class="underline text-azul-800">Cookies</a>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow p-6 border-t-4 border-red-500">
            <h2 class="font-semibold text-azul-950 mb-2 flex items-center gap-2"><i data-lucide="user-x" class="size-[18px] text-red-500"></i> Eliminar mi cuenta</h2>
            @if($pendiente)
                <div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm mb-4">
                    Su solicitud del {{ $pendiente->created_at->format('d/m/Y') }} está <b>pendiente de aprobación</b> por un administrador. Le avisaremos por correo.
                </div>
                <form method="POST" action="{{ route('mi-cuenta.cancelar') }}">@csrf
                    <button class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg">Cancelar la solicitud</button>
                </form>
            @else
                <p class="text-sm text-gray-500 mb-4">Se borrarán su cuenta, su CV, su foto y sus datos. Un administrador revisa la solicitud antes de eliminarla. <b>No se puede deshacer.</b></p>
                <form method="POST" action="{{ route('mi-cuenta.solicitar') }}" class="space-y-3" onsubmit="return confEliminar(event)">
                    @csrf
                    <div><label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Motivo (opcional)</label>
                        <textarea name="motivo" rows="2" maxlength="1000" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm"></textarea></div>
                    <div><label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Su contraseña, para confirmar</label>
                        <input type="password" name="password" required autocomplete="current-password" class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
                    <label class="flex items-start gap-2 text-sm text-gray-600"><input type="checkbox" name="confirmo" value="1" class="mt-1"> Entiendo que mis datos se borrarán para siempre.</label>
                    <button class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg"><i data-lucide="trash-2" class="size-4"></i> Solicitar eliminación</button>
                </form>
            @endif
            @if($historial->where('estado', '!=', 'pendiente')->isNotEmpty())
                <h3 class="text-xs font-bold uppercase text-gray-500 mt-5 mb-2">Solicitudes anteriores</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    @foreach($historial->where('estado', '!=', 'pendiente') as $h)
                        <li>{{ $h->created_at->format('d/m/Y') }} · {{ \App\Models\SolicitudEliminacion::ESTADOS[$h->estado] ?? $h->estado }}@if($h->nota_admin) — {{ $h->nota_admin }}@endif</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
    @push('scripts')
    <script>function confEliminar(e){e.preventDefault();Swal.fire({title:'¿Solicitar la eliminación?',text:'Un administrador la revisará. Cuando la apruebe, su cuenta y sus datos se borrarán.',icon:'warning',showCancelButton:true,confirmButtonColor:'#c0392b',confirmButtonText:'Sí, solicitar',cancelButtonText:'Cancelar'}).then(r=>{if(r.isConfirmed)e.target.submit();});return false;}</script>
    @endpush
</x-layout>
