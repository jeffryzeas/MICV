<x-layout titulo="Errores del sistema" activo="errores">

    <p class="text-gray-500 mb-5">
        Lee directamente <code class="bg-gray-100 px-1.5 py-0.5 rounded text-xs">{{ $archivo }}</code>
        ({{ $tamano }} KB). Solo se muestran las últimas 300 entradas, la más reciente primero.
    </p>

    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
            <form method="GET" class="flex gap-2 flex-wrap">
                <input type="search" name="q" value="{{ $q }}" placeholder="Buscar texto en el mensaje"
                       class="border border-gray-300 rounded-lg px-3 py-2.5 w-64">
                <select name="nivel" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2.5">
                    <option value="">Todos los niveles</option>
                    <option value="ERROR" @selected($nivel === 'ERROR')>ERROR</option>
                    <option value="CRITICAL" @selected($nivel === 'CRITICAL')>CRITICAL</option>
                    <option value="WARNING" @selected($nivel === 'WARNING')>WARNING</option>
                    <option value="INFO" @selected($nivel === 'INFO')>INFO</option>
                    <option value="DEBUG" @selected($nivel === 'DEBUG')>DEBUG</option>
                </select>
                <button type="submit" class="bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-2.5 rounded-lg">
                    <i data-lucide="search" class="size-4"></i>
                </button>
            </form>

            <form method="POST" action="{{ route('admin.errores.limpiar') }}" onsubmit="return confirmarLimpiar(event)">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 bg-red-50 hover:bg-red-100 text-red-700 text-sm font-semibold px-4 py-2.5 rounded-lg border border-red-200">
                    <i data-lucide="trash-2" class="size-4"></i> Limpiar log
                </button>
            </form>
        </div>

        @if(empty($entradas))
            <div class="text-center py-10 text-gray-400">
                <i data-lucide="shield-check" class="size-10 mx-auto mb-2"></i>
                <p>Sin errores registrados{{ $q || $nivel ? ' con ese filtro' : '' }}. Buena señal.</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach($entradas as $i => $e)
                @php
                    $colorNivel = match($e['nivel']) {
                        'ERROR', 'CRITICAL', 'EMERGENCY', 'ALERT' => 'bg-red-50 text-red-700 border-red-200',
                        'WARNING' => 'bg-amber-50 text-amber-700 border-amber-200',
                        'INFO', 'NOTICE' => 'bg-blue-50 text-blue-700 border-blue-200',
                        default => 'bg-gray-50 text-gray-700 border-gray-200',
                    };
                    $lineas = explode("\n", $e['mensaje']);
                    $primeraLinea = array_shift($lineas);
                    $resto = implode("\n", $lineas);
                @endphp
                <div class="border rounded-lg {{ $colorNivel }}">
                    <button type="button" onclick="toggleLog({{ $i }})" class="w-full text-left px-4 py-3 flex items-start gap-3">
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full border {{ $colorNivel }} shrink-0 mt-0.5">{{ $e['nivel'] }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs text-gray-500">{{ $e['fecha'] }}</div>
                            @if($e['pista'])
                                <div class="text-sm font-semibold text-azul-800 mt-0.5">💡 {{ $e['pista'] }}</div>
                            @endif
                            <div class="text-sm font-mono break-words {{ $e['pista'] ? 'text-gray-500 mt-1' : '' }}">{{ \Illuminate\Support\Str::limit($primeraLinea, 160) }}</div>
                        </div>
                        @if($resto)
                            <i data-lucide="chevron-down" class="size-4 shrink-0 mt-1"></i>
                        @endif
                    </button>
                    @if($resto)
                    <div id="logDetalle{{ $i }}" class="hidden px-4 pb-4">
                        <pre class="text-xs bg-white/60 rounded p-3 overflow-x-auto whitespace-pre-wrap">{{ $resto }}</pre>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        @endif
    </div>

    @push('scripts')
    <script>
    function toggleLog(i) {
        const el = document.getElementById('logDetalle' + i);
        if (el) el.classList.toggle('hidden');
    }
    function confirmarLimpiar(e) {
        e.preventDefault();
        Swal.fire({title:'¿Limpiar el log?', text:'Se borrará todo el historial de errores guardado.', icon:'warning', showCancelButton:true, confirmButtonColor:'#c0392b', confirmButtonText:'Sí, limpiar', cancelButtonText:'Cancelar'})
            .then(r => { if (r.isConfirmed) e.target.submit(); });
        return false;
    }
    </script>
    @endpush
</x-layout>
