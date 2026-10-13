@php $esCliente = auth()->user()->esCliente(); @endphp
<x-layout :titulo="$tituloTipo" activo="cartas">

    <a href="{{ route('cartas.index') }}" class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 hover:bg-azul-800 hover:text-white text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg shadow-sm transition-colors mb-5">
        <i data-lucide="arrow-left" class="size-4"></i> Volver a tipos de carta
    </a>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        {{-- ===== FORMULARIO ===== --}}
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-azul-950 mb-1">{{ $tituloTipo }}</h2>
            <p class="text-sm text-gray-500 mb-4">Completa los datos — la vista previa de la derecha se actualiza sola.</p>

            <form id="formCarta" method="POST" action="{{ route('cartas.generar', $tipo) }}" data-sin-cargando onsubmit="return jzConfirmarCarta(event)">
                @csrf
                @if($esCliente)
                    <input type="hidden" name="cliente_id" id="clienteIdSeleccionado" value="{{ $preseleccionado?->id }}">
                    <div class="mb-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 text-sm">
                        La carta sale con sus datos: <b>{{ $preseleccionado?->nombreCompleto() }}</b>.
                        @if(!$preseleccionado?->numero_documento) <br>Le falta el número de documento: <a href="{{ route('mi-cv.editar') }}" class="underline font-semibold">complételo aquí</a>.@endif
                    </div>
                @else
                <div class="mb-4">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Persona a quien es la carta *</label>
                    <input type="text" id="buscarCliente" placeholder="Nombre, apellido o documento…" autocomplete="off" value="{{ $preseleccionado?->nombreCompleto() }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    <div id="resultadosCliente" class="mt-1 border border-gray-200 rounded-lg divide-y max-h-40 overflow-y-auto hidden"></div>
                    <input type="hidden" name="cliente_id" id="clienteIdSeleccionado" value="{{ $preseleccionado?->id }}">
                    <p id="clienteSeleccionadoTexto" class="text-sm text-emerald-600 mt-1 {{ $preseleccionado ? '' : 'hidden' }}">{{ $preseleccionado ? 'Seleccionado: ' . $preseleccionado->nombreCompleto() : '' }}</p>
                </div>
                @endif

                <div class="mb-4">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Modelo de redacción</label>
                    <select name="modelo" id="modeloSelect" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                        @for($i = 1; $i <= $cantidadModelos; $i++)
                            <option value="{{ $i }}">Modelo {{ $i }}</option>
                        @endfor
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Cambia el modelo cuantas veces quieras — no se pierde lo que ya escribiste.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Fecha de la carta</label>
                        <input type="date" name="fecha_carta" value="{{ now()->format('Y-m-d') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Lugar</label>
                        <input type="text" name="lugar" placeholder="Por defecto usa su municipio de nacimiento" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    </div>
                </div>

                @if($tipo === 'renuncia_15dias')
                <div class="mb-4">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Fecha en que se hace efectiva la renuncia</label>
                    <input type="date" name="fecha_renuncia" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    <p class="text-xs text-gray-400 mt-1">Si la dejas vacía, dice el plazo de preaviso de ley a partir de la fecha de la carta (Nicaragua: 15 días; Costa Rica: art. 28 del Código de Trabajo).</p>
                </div>
                @endif

                @if(in_array($tipo, ['renuncia_inmediata', 'renuncia_15dias', 'solicitud_empleo', 'presentacion']))
                <div class="mb-4">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Empresa</label>
                    <input type="text" name="empresa" placeholder="Nombre de la empresa" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Nombre del encargado de la empresa (opcional)</label>
                    <input type="text" name="encargado_nombre" placeholder="A quién va dirigida" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                </div>
                @endif

                @if(in_array($tipo, ['renuncia_inmediata', 'renuncia_15dias', 'recomendacion_laboral']))
                <div class="mb-4">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Cargo</label>
                    <input type="text" name="cargo" placeholder="Ej: Vendedor(a)" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                </div>
                @endif

                @if($tipo === 'presentacion')
                <div class="mb-4">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Puesto al que aplica</label>
                    <input type="text" name="cargo" placeholder="Si lo deja vacío se usa su profesión u oficio" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    <p class="text-xs text-gray-400 mt-1">La experiencia, los estudios y las habilidades se toman de su CV. Si cambia su CV, la carta se actualiza sola.</p>
                </div>
                @endif

                @if($tipo === 'solicitud_empleo')
                <div class="mb-4">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Área o puesto deseado</label>
                    <input type="text" name="area" placeholder="Ej: atención al cliente" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                </div>
                @endif

                @if($tipo === 'recomendacion_laboral')
                <div class="mb-4">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Empresa donde laboró</label>
                    <input type="text" name="empresa" placeholder="Nombre de la empresa" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Tiempo laborado</label>
                    <input type="text" name="tiempo_laborado" placeholder="Ej: 2 años" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                </div>
                @endif

                @if($tipo === 'recomendacion_personal')
                <div class="mb-4">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Relación con la persona</label>
                    <input type="text" name="relacion" placeholder="Ej: vecino(a) mío(a) desde hace 5 años" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                </div>
                @endif

                @if(in_array($tipo, ['recomendacion_laboral', 'recomendacion_personal']))
                <div class="mb-4 p-3 bg-blue-50 rounded-lg">
                    <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Quién firma la carta</label>
                    @unless($esCliente)
                    <p class="text-xs text-gray-500 mb-2">Búscalo primero en el sistema. Si no está registrado, escribe su nombre y celular a mano abajo.</p>
                    <input type="text" id="buscarFirmante" placeholder="Buscar por nombre o documento…" autocomplete="off"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    <div id="resultadosFirmante" class="mt-1 border border-gray-200 rounded-lg divide-y max-h-40 overflow-y-auto hidden bg-white"></div>
                    <input type="hidden" name="firmante_cliente_id" id="firmanteIdSeleccionado">
                    <p id="firmanteSeleccionadoTexto" class="text-sm text-emerald-600 mt-1 hidden"></p>
                    @else
                    <p class="text-xs text-gray-500 mb-2">Escriba el nombre y celular de la persona que le firma la carta.</p>
                    @endunless

                    <div id="firmanteManual" class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3">
                        <div>
                            <label class="block text-[11px] font-semibold uppercase text-gray-500 mb-1">Nombre (si no está registrado)</label>
                            <input type="text" name="firmante_nombre" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold uppercase text-gray-500 mb-1">Celular</label>
                            <input type="text" name="firmante_celular" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold uppercase text-gray-500 mb-1">Sexo (para el lenguaje correcto)</label>
                            <select name="firmante_sexo" class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm">
                                <option value="">—</option>
                                <option value="M">Masculino</option><option value="F">Femenino</option>
                            </select>
                        </div>
                    </div>
                    <p class="text-xs text-amber-600 mt-2">La cédula se deja en blanco en la hoja para completarla a mano.</p>
                </div>
                @endif

                <button type="submit" class="mt-2 inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                    <i data-lucide="download" class="size-4"></i> Descargar PDF para imprimir
                </button>
            </form>
        </div>

        {{-- ===== VISTA PREVIA ===== --}}
        <div class="bg-white rounded-xl shadow p-3">
            <p class="text-xs font-semibold uppercase text-gray-500 px-2 mb-2">Vista previa</p>
            <iframe id="iframePreview" class="w-full border border-gray-200 rounded-lg" style="height: 720px;"></iframe>
        </div>
    </div>

    @push('scripts')
    <script>
    // ---- Buscador de cliente (a quién es la carta) ----
    let jzTimeoutCliente = null;
    const jzBuscarClienteEl = document.getElementById('buscarCliente');
    if (jzBuscarClienteEl) jzBuscarClienteEl.addEventListener('input', function () {
        const q = this.value.trim();
        document.getElementById('clienteIdSeleccionado').value = '';
        clearTimeout(jzTimeoutCliente);
        if (q.length < 2) { document.getElementById('resultadosCliente').classList.add('hidden'); return; }
        jzTimeoutCliente = setTimeout(async () => {
            const resp = await fetch("{{ route('cartas.buscar') }}?q=" + encodeURIComponent(q));
            const datos = await resp.json();
            const cont = document.getElementById('resultadosCliente');
            cont.innerHTML = datos.length === 0 ? '<div class="px-3 py-2 text-sm text-gray-400">Sin resultados</div>' : '';
            datos.forEach(c => {
                const div = document.createElement('div');
                div.className = 'px-3 py-2 text-sm hover:bg-blue-50 cursor-pointer';
                div.textContent = c.texto;
                div.onclick = () => {
                    document.getElementById('clienteIdSeleccionado').value = c.id;
                    document.getElementById('clienteSeleccionadoTexto').textContent = 'Seleccionado: ' + c.texto;
                    document.getElementById('clienteSeleccionadoTexto').classList.remove('hidden');
                    cont.classList.add('hidden');
                    document.getElementById('buscarCliente').value = c.texto;
                    jzActualizarPreview();
                };
                cont.appendChild(div);
            });
            cont.classList.remove('hidden');
        }, 300);
    });

    // ---- Buscador de firmante (solo cartas de recomendación) ----
    const buscarFirmanteEl = document.getElementById('buscarFirmante');
    if (buscarFirmanteEl) {
        let jzTimeoutFirmante = null;
        buscarFirmanteEl.addEventListener('input', function () {
            const q = this.value.trim();
            document.getElementById('firmanteIdSeleccionado').value = '';
            clearTimeout(jzTimeoutFirmante);
            if (q.length < 2) { document.getElementById('resultadosFirmante').classList.add('hidden'); return; }
            jzTimeoutFirmante = setTimeout(async () => {
                const resp = await fetch("{{ route('cartas.buscar') }}?q=" + encodeURIComponent(q));
                const datos = await resp.json();
                const cont = document.getElementById('resultadosFirmante');
                cont.innerHTML = datos.length === 0 ? '<div class="px-3 py-2 text-sm text-gray-400">No está registrado — escríbelo abajo a mano</div>' : '';
                datos.forEach(c => {
                    const div = document.createElement('div');
                    div.className = 'px-3 py-2 text-sm hover:bg-blue-50 cursor-pointer';
                    div.textContent = c.texto;
                    div.onclick = () => {
                        document.getElementById('firmanteIdSeleccionado').value = c.id;
                        document.getElementById('firmanteSeleccionadoTexto').textContent = 'Encontrado: ' + c.texto;
                        document.getElementById('firmanteSeleccionadoTexto').classList.remove('hidden');
                        cont.classList.add('hidden');
                        buscarFirmanteEl.value = c.texto;
                        jzActualizarPreview();
                    };
                    cont.appendChild(div);
                });
                cont.classList.remove('hidden');
            }, 300);
        });
    }

    // ---- Vista previa en vivo (no navega, no pierde datos) ----
    let jzTimeoutPreview = null;
    async function jzActualizarPreview() {
        clearTimeout(jzTimeoutPreview);
        if (!document.getElementById('clienteIdSeleccionado').value) {
            document.getElementById('iframePreview').srcdoc = '<body style="font-family:sans-serif;color:#999;padding:20px;">Selecciona primero a quién es la carta para ver la vista previa aquí.</body>';
            return;
        }
        jzTimeoutPreview = setTimeout(async () => {
            const formData = new FormData(document.getElementById('formCarta'));
            const resp = await fetch("{{ route('cartas.previsualizar', $tipo) }}", { method: 'POST', body: formData });
            const html = await resp.text();
            document.getElementById('iframePreview').srcdoc = html;
        }, 400);
    }
    document.getElementById('formCarta').addEventListener('input', jzActualizarPreview);
    document.getElementById('modeloSelect').addEventListener('change', jzActualizarPreview);
    jzActualizarPreview();

    function jzConfirmarCarta(e) {
        e.preventDefault();
        if (!document.getElementById('clienteIdSeleccionado').value) {
            Swal.fire({icon: 'error', title: 'Falta la persona', text: 'Busque y seleccione a quién es la carta.'});
            return false;
        }
        Swal.fire({title: '¿Generar la carta?', icon: 'question', showCancelButton: true, confirmButtonColor: '#0B3C74', confirmButtonText: 'Sí, generar'})
            .then(r => { if (r.isConfirmed) e.target.submit(); });
        return false;
    }
    </script>
    @endpush
</x-layout>
