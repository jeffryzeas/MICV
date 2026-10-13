@php
    $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm';
    $lb = 'block text-[11px] font-semibold uppercase text-azul-800 mb-1';
    $cat = \App\Support\TarjetaDisenos::CATALOGO;
    $dis = $t->disenoDatos();
    $correoDestino = $esUsuario ? auth()->user()->email : $cliente->correo;
@endphp
<x-layout :titulo="$esUsuario ? 'Mi tarjeta de presentación' : 'Tarjeta de ' . $cliente->nombreCorto()" activo="tarjeta">
    <p class="text-gray-500 text-sm mb-4">Una tarjeta para darse a conocer, aparte del CV. Elija un diseño de su oficio, revise sus datos y descárguela para compartirla por WhatsApp o imprimirla: salen <b>10 tarjetas en una hoja carta</b>, listas para recortar.</p>

    <form id="fTarjeta" class="grid grid-cols-1 lg:grid-cols-5 gap-5" enctype="multipart/form-data" onsubmit="return false">
        @csrf
        {{-- Vista previa y acciones --}}
        <div class="lg:col-span-2 lg:order-2">
            <div class="lg:sticky lg:top-20 space-y-3">
                <div class="bg-white rounded-xl shadow p-3">
                    <div class="flex items-center justify-between px-1 pb-2">
                        <span class="text-xs font-semibold uppercase text-azul-800">Vista previa</span>
                        <span id="estadoVista" class="text-[11px] text-gray-400">Tamaño carné · 8,56 × 5,4 cm</span>
                    </div>
                    <iframe id="visorTarjeta" title="Vista previa de la tarjeta" class="w-full rounded-lg bg-[#e9edf3] aspect-[1.45]"></iframe>
                </div>
                <div class="bg-white rounded-xl shadow p-3 grid grid-cols-2 gap-2">
                    <button type="button" onclick="jzTarjeta('guardar')" class="col-span-2 inline-flex items-center justify-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold py-3 rounded-lg transition-colors"><i data-lucide="save" class="size-4"></i> Guardar tarjeta</button>
                    <button type="button" onclick="jzTarjeta('whatsapp')" class="inline-flex items-center justify-center gap-2 bg-[#25D366] hover:bg-[#1ebe5b] text-white text-sm font-semibold py-2.5 rounded-lg"><i data-lucide="message-circle" class="size-4"></i> WhatsApp</button>
                    <button type="button" onclick="jzTarjeta('png')" class="inline-flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-azul-800 text-sm font-semibold py-2.5 rounded-lg"><i data-lucide="image" class="size-4"></i> Imagen PNG</button>
                    <button type="button" onclick="jzTarjeta('hoja')" class="inline-flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-azul-800 text-sm font-semibold py-2.5 rounded-lg"><i data-lucide="printer" class="size-4"></i> Hoja de 10</button>
                    <button type="button" onclick="jzTarjeta('pdf')" class="inline-flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-azul-800 text-sm font-semibold py-2.5 rounded-lg"><i data-lucide="file-down" class="size-4"></i> PDF (1)</button>
                    <button type="button" onclick="jzTarjeta('correo')" @disabled(!$correoDestino) class="col-span-2 inline-flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-azul-800 text-sm font-semibold py-2.5 rounded-lg disabled:opacity-40"><i data-lucide="send" class="size-4"></i> {{ $esUsuario ? 'Enviar a mi correo' : 'Enviar al correo del cliente' }}</button>
                    <p class="col-span-2 text-[11px] text-gray-400 leading-snug">Para imprimir: cartulina tamaño carta, a <b>tamaño real (100 %)</b>, sin "ajustar a la página". Corte por las líneas.</p>
                </div>
            </div>
        </div>

        {{-- Formulario --}}
        <div class="lg:col-span-3 lg:order-1 space-y-5">
            <section class="bg-white rounded-xl shadow p-4 sm:p-5">
                <h2 class="font-semibold text-azul-950 mb-3 flex items-center gap-2"><i data-lucide="palette" class="size-[18px] text-oro-500"></i> 1. Elija un diseño</h2>
                <div class="flex gap-2 overflow-x-auto pb-2 mb-3 -mx-1 px-1" id="filtrosCat">
                    <button type="button" data-cat="" class="jz-cat shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold border border-azul-800 bg-azul-800 text-white">Todos</button>
                    @foreach(\App\Support\TarjetaDisenos::categorias() as $c)
                        <button type="button" data-cat="{{ $c }}" class="jz-cat shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold border border-gray-300 bg-white text-gray-600">{{ $c }}</button>
                    @endforeach
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5" id="gridDisenos">
                    @foreach($cat as $k => [$nombre, $categoria, $lay, $col, $icos])
                        @php $tp = \App\Support\Color::textoSobre($col['p']); $oscuro = in_array($lay, ['oscuro', 'elegante']); @endphp
                        <label class="jz-dis cursor-pointer" data-cat="{{ $categoria }}">
                            <input type="radio" name="diseno" value="{{ $k }}" class="peer sr-only" @checked($t->diseno === $k)>
                            <span class="block rounded-xl border-2 border-gray-200 peer-checked:border-azul-800 peer-checked:ring-2 peer-checked:ring-azul-800/20 overflow-hidden hover:border-gray-300 transition">
                                <span class="relative block aspect-[1.58] overflow-hidden" style="background: {{ $oscuro ? $col['f'] : '#fff' }}">
                                    @switch($lay)
                                        @case('lateral')<span class="absolute inset-y-0 left-0 w-[16%]" style="background:{{ $col['p'] }}"></span><span class="absolute inset-y-0 left-[16%] w-[2.5%]" style="background:{{ $col['s'] }}"></span>@break
                                        @case('superior')<span class="absolute inset-x-0 top-0 h-[38%]" style="background:{{ $col['p'] }}"></span><span class="absolute inset-x-0 top-[38%] h-[3%]" style="background:{{ $col['s'] }}"></span>@break
                                        @case('diagonal')<span class="absolute inset-0" style="background:{{ $col['p'] }};clip-path:polygon(64% 0,100% 0,100% 100%,50% 100%)"></span>@break
                                        @case('elegante')<span class="absolute inset-[7%] rounded border" style="border-color:{{ $col['s'] }}"></span>@break
                                        @case('oscuro')<span class="absolute -right-[20%] -top-[40%] w-[80%] aspect-square rounded-full opacity-40" style="background:radial-gradient(circle, {{ $col['p'] }}, transparent 65%)"></span>@break
                                        @case('foto')<span class="absolute -left-[16%] -top-[18%] w-[56%] h-[136%] rounded-full" style="background:{{ $col['p'] }}"></span>@break
                                    @endswitch
                                    <span class="absolute right-[7%] bottom-[12%] size-[26%] rounded bg-white shadow-sm flex items-center justify-center"><span class="size-[70%] rounded-sm" style="background: repeating-linear-gradient(90deg, {{ \App\Support\Color::luminancia($col['p']) < 0.2 ? $col['p'] : '#111' }} 0 2px, transparent 2px 4px)"></span></span>
                                    <span class="absolute left-[24%] top-[22%] w-[38%] h-[7%] rounded-full" style="background: {{ $oscuro || $lay === 'superior' ? ($lay === 'superior' ? $tp : '#fff') : $col['t'] }}; opacity:.85"></span>
                                    <span class="absolute left-[24%] top-[34%] w-[24%] h-[5%] rounded-full" style="background: {{ $col['s'] }}"></span>
                                    <span class="absolute left-[5%] {{ $lay === 'foto' ? 'top-[30%]' : 'bottom-[10%]' }} size-[15%] [&>svg]:w-full [&>svg]:h-full" style="color: {{ in_array($lay, ['lateral', 'foto']) ? $tp : ($oscuro ? $col['p'] : $col['p']) }}">{!! \App\Support\TarjetaDisenos::icono($icos[0]) !!}</span>
                                </span>
                                <span class="block px-2 py-1.5 text-[11px] font-semibold text-azul-950 leading-tight truncate">{{ $nombre }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="color_propio" value="1" id="colorPropio" @checked($t->color)> Usar mi propio color</label>
                    <input type="color" name="color" id="colorTj" value="{{ $t->color ?: $dis[3]['p'] }}" class="h-9 w-14 border border-gray-300 rounded-lg {{ $t->color ? '' : 'opacity-40' }}">
                </div>
            </section>

            <section class="bg-white rounded-xl shadow p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h2 class="font-semibold text-azul-950 flex items-center gap-2"><i data-lucide="contact" class="size-[18px] text-oro-500"></i> 2. Sus datos</h2>
                    <button type="button" id="btnDelCv" class="text-xs font-semibold text-azul-800 underline">Traer los datos de mi CV</button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div><label class="{{ $lb }}">Nombre en la tarjeta</label><input type="text" name="nombre" maxlength="{{ \App\Models\Tarjeta::LIMITES['nombre'] }}" value="{{ $t->d('nombre') }}" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Oficio o cargo</label><input type="text" name="cargo" maxlength="{{ \App\Models\Tarjeta::LIMITES['cargo'] }}" value="{{ $t->d('cargo') }}" placeholder="Ej: Mandados y entregas" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Celular / WhatsApp</label><input type="tel" name="telefono" maxlength="{{ \App\Models\Tarjeta::LIMITES['telefono'] }}" value="{{ $t->d('telefono') }}" placeholder="+505 8888 8888" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Correo</label><input type="email" name="correo" maxlength="{{ \App\Models\Tarjeta::LIMITES['correo'] }}" value="{{ $t->d('correo') }}" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Zona o dirección</label><input type="text" name="direccion" maxlength="{{ \App\Models\Tarjeta::LIMITES['direccion'] }}" value="{{ $t->d('direccion') }}" placeholder="Ej: Cartago centro" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Sitio web</label><input type="text" name="web" maxlength="{{ \App\Models\Tarjeta::LIMITES['web'] }}" value="{{ $t->d('web') }}" placeholder="misitio.com" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Red social</label><input type="text" name="redes" maxlength="{{ \App\Models\Tarjeta::LIMITES['redes'] }}" value="{{ $t->d('redes') }}" placeholder="@miusuario" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Frase corta (opcional)</label><input type="text" name="frase" maxlength="{{ \App\Models\Tarjeta::LIMITES['frase'] }}" value="{{ $t->d('frase') }}" placeholder="Rápido, seguro y puntual" class="{{ $in }}"></div>
                </div>
                <p class="text-[11px] text-gray-400 mt-2">En la tarjeta caben hasta 4 datos de contacto: se usan en este orden (celular, correo, web, red social, zona).</p>
            </section>

            <section class="bg-white rounded-xl shadow p-4 sm:p-5">
                <h2 class="font-semibold text-azul-950 mb-3 flex items-center gap-2"><i data-lucide="image" class="size-[18px] text-oro-500"></i> 3. Foto o logo</h2>
                <div class="grid grid-cols-3 gap-2 mb-3">
                    @foreach(['foto' => ['user-round', 'Mi foto'], 'logo' => ['badge-check', 'Mi logo'], 'ninguna' => ['shapes', 'Ícono del oficio']] as $k => [$ic, $tx])
                        <label class="cursor-pointer"><input type="radio" name="imagen" value="{{ $k }}" class="peer sr-only" @checked($t->imagen === $k)>
                            <span class="flex flex-col items-center gap-1 border-2 border-gray-200 rounded-xl py-3 text-xs font-semibold text-gray-600 peer-checked:border-azul-800 peer-checked:text-azul-800 peer-checked:bg-blue-50"><i data-lucide="{{ $ic }}" class="size-5"></i>{{ $tx }}</span></label>
                    @endforeach
                </div>
                @if(!$cliente->tieneFoto())<p class="text-[11px] text-amber-700 mb-2">No hay foto en el CV: si elige "Mi foto" salen sus iniciales. Puede subirla en "Mis datos".</p>@endif
                <div class="flex flex-wrap items-center gap-3">
                    <label class="inline-flex items-center gap-2 cursor-pointer border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2 rounded-lg">
                        <i data-lucide="upload" class="size-4"></i> {{ $t->logo ? 'Cambiar logo' : 'Subir logo' }}
                        <input type="file" name="logo" id="logoTj" accept="image/png,image/jpeg,image/webp" class="sr-only">
                    </label>
                    @if($t->logo)<label class="inline-flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" name="quitar_logo" value="1"> Quitar logo</label>@endif
                    <span class="text-[11px] text-gray-400">PNG con fondo transparente queda mejor. El logo también va en el centro del código QR.</span>
                </div>
                <input type="hidden" name="logo_temporal" id="logoTemporal">
            </section>

            <section class="bg-white rounded-xl shadow p-4 sm:p-5">
                <h2 class="font-semibold text-azul-950 mb-3 flex items-center gap-2"><i data-lucide="qr-code" class="size-[18px] text-oro-500"></i> 4. Código QR</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach(['whatsapp' => ['message-circle', 'Abre WhatsApp'], 'contacto' => ['contact', 'Guarda mi contacto'], 'web' => ['globe', 'Abre mi sitio'], 'ninguno' => ['x', 'Sin QR']] as $k => [$ic, $tx])
                        <label class="cursor-pointer"><input type="radio" name="qr" value="{{ $k }}" class="peer sr-only" @checked($t->qr === $k)>
                            <span class="flex flex-col items-center gap-1 border-2 border-gray-200 rounded-xl py-3 text-xs font-semibold text-gray-600 text-center peer-checked:border-azul-800 peer-checked:text-azul-800 peer-checked:bg-blue-50"><i data-lucide="{{ $ic }}" class="size-5"></i>{{ $tx }}</span></label>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-400 mt-2">"Abre WhatsApp" lleva directo a un chat con su número. "Guarda mi contacto" agrega sus datos a la agenda del celular de quien lo escanee.</p>
            </section>
        </div>
    </form>

    @push('scripts')
    <script>
    (function () {
        const f = document.getElementById('fTarjeta'), visor = document.getElementById('visorTarjeta'), estado = document.getElementById('estadoVista');
        const rutas = { vista: @json(route('tarjetas.vista', $cliente)), guardar: @json(route('tarjetas.guardar', $cliente)), exportar: @json(route('tarjetas.exportar', [$cliente, 'FORMATO'])), enviar: @json(route('tarjetas.enviar', $cliente)) };
        const delCv = @json(\App\Models\Tarjeta::datosDelCv($cliente));
        const nombreArchivo = @json('Tarjeta_' . \Illuminate\Support\Str::slug($t->d('nombre') ?: 'presentacion', '_'));
        let timer = null, pedido = 0;

        function datos() { return new FormData(f); }
        async function refrescar() {
            const n = ++pedido; estado.textContent = 'Actualizando…';
            try {
                const r = await fetch(rutas.vista, { method: 'POST', body: datos(), headers: { 'Accept': 'application/json, text/html;q=0.9', 'X-Requested-With': 'XMLHttpRequest' } });
                if (n !== pedido) return;
                if (r.ok) { visor.srcdoc = await r.text(); estado.textContent = 'Tamaño carné · 8,56 × 5,4 cm'; }
                else { const j = await r.json().catch(() => null); estado.textContent = primerError(j) || 'Revise los datos'; if (r.status === 419 && j && j.token) { renovarToken(j.token); pronto(); } }
            } catch (e) { estado.textContent = 'Sin conexión'; }
        }
        const pronto = () => { clearTimeout(timer); timer = setTimeout(refrescar, 350); };
        f.addEventListener('input', pronto); f.addEventListener('change', pronto);

        // Filtro por categoría
        document.getElementById('filtrosCat').addEventListener('click', e => {
            const b = e.target.closest('.jz-cat'); if (!b) return;
            document.querySelectorAll('.jz-cat').forEach(x => { const on = x === b; x.classList.toggle('bg-azul-800', on); x.classList.toggle('text-white', on); x.classList.toggle('border-azul-800', on); x.classList.toggle('bg-white', !on); x.classList.toggle('text-gray-600', !on); });
            document.querySelectorAll('.jz-dis').forEach(d => d.classList.toggle('hidden', !!b.dataset.cat && d.dataset.cat !== b.dataset.cat));
        });
        document.getElementById('colorPropio').addEventListener('change', e => document.getElementById('colorTj').classList.toggle('opacity-40', !e.target.checked));
        document.getElementById('colorTj').addEventListener('input', () => { document.getElementById('colorPropio').checked = true; document.getElementById('colorTj').classList.remove('opacity-40'); });

        // Logo: vista previa sin subirlo todavía
        document.getElementById('logoTj').addEventListener('change', function () {
            const a = this.files[0]; if (!a) return;
            const lector = new FileReader();
            lector.onload = () => { document.getElementById('logoTemporal').value = lector.result; f.querySelector('input[name=imagen][value=logo]').checked = true; refrescar(); };
            lector.readAsDataURL(a);
        });
        document.getElementById('btnDelCv').addEventListener('click', () => {
            Object.entries(delCv).forEach(([k, v]) => { const i = f.querySelector('[name=' + k + ']'); if (i && v) i.value = v; });
            refrescar();
        });

        function primerError(j) { if (!j) return ''; if (j.errors) { const e = Object.values(j.errors)[0]; return Array.isArray(e) ? e[0] : e; } return j.mensaje || j.message || ''; }
        function renovarToken(t) { f.querySelector('input[name=_token]').value = t; const m = document.querySelector('meta[name=csrf-token]'); if (m) m.content = t; }
        // Pide siempre JSON para los errores: así un error nunca se descarga como si fuera la imagen o el PDF.
        async function pedir(url, comoJson, esperado, reintento) {
            const fd = datos(); fd.delete('logo_temporal');
            const r = await fetch(url, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.status === 419 && !reintento) {
                const j = await r.json().catch(() => ({}));
                if (j.token) { renovarToken(j.token); return pedir(url, comoJson, esperado, true); }
            }
            if (!r.ok) {
                const j = await r.json().catch(() => ({}));
                throw new Error(r.status === 429 ? 'Hizo muchas descargas seguidas. Espere un minuto.' : (primerError(j) || 'No se pudo completar. Intente de nuevo.'));
            }
            // Solo es error si el servidor mandó una página o un mensaje en lugar del archivo.
            const tipo = (r.headers.get('Content-Type') || '').toLowerCase();
            if (esperado && (tipo.includes('text/html') || tipo.includes('json'))) {
                throw new Error('El archivo no se pudo crear. Recargue la página e intente de nuevo.');
            }
            return r;
        }
        function bajar(blob, nombre) { const u = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = u; a.download = nombre; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(u), 4000); }

        window.jzTarjeta = async function (accion) {
            const textos = { guardar: 'Guardando…', png: 'Creando la imagen en alta calidad…', pdf: 'Creando el PDF…', hoja: 'Armando la hoja con 10 tarjetas…', whatsapp: 'Preparando la imagen…', correo: 'Enviando su tarjeta…' };
            window.jzMostrarCargando && jzMostrarCargando(textos[accion]);
            try {
                if (accion === 'guardar') {
                    const r = await pedir(rutas.guardar, true); const j = await r.json();
                    jzOcultarCargando(); Swal.fire({ icon: 'success', title: '¡Listo!', text: j.mensaje, confirmButtonColor: '#0B3C74', timer: 1800 });
                    return;
                }
                if (accion === 'correo') {
                    const r = await pedir(rutas.enviar, true); const j = await r.json();
                    jzOcultarCargando(); Swal.fire({ icon: 'success', title: '¡Enviada!', text: j.mensaje, confirmButtonColor: '#0B3C74' });
                    return;
                }
                const formato = accion === 'whatsapp' ? 'png' : accion;
                const r = await pedir(rutas.exportar.replace('FORMATO', formato), false, formato === 'png' ? 'image/png' : 'application/pdf');
                let blob = await r.blob().catch(() => null);
                jzOcultarCargando();
                // Algunos gestores de descarga (IDM y otros) se quedan con el archivo y la página recibe nada:
                // la descarga ya se hizo, así que no se muestra ningún error.
                if (!blob || !blob.size) { toastDescarga(); return; }
                const tipoEsperado = formato === 'png' ? 'image/png' : 'application/pdf';
                if (blob.type !== tipoEsperado) blob = new Blob([blob], { type: tipoEsperado });
                const nombre = nombreArchivo + (formato === 'png' ? '.png' : (formato === 'hoja' ? '_hoja_10.pdf' : '.pdf'));
                if (accion === 'whatsapp') {
                    const archivo = new File([blob], nombre, { type: 'image/png' });
                    if (navigator.canShare && navigator.canShare({ files: [archivo] })) {
                        try { await navigator.share({ files: [archivo], title: 'Mi tarjeta de presentación', text: 'Esta es mi tarjeta de presentación. ¡Guárdela!' }); return; } catch (e) { if (e.name === 'AbortError') return; }
                    }
                    bajar(blob, nombre);
                    Swal.fire({ icon: 'info', title: 'Imagen descargada', html: 'Su navegador no permite compartir directo. La imagen ya se descargó: abra WhatsApp y adjúntela desde <b>Galería</b> o <b>Documentos</b>.', confirmButtonText: 'Abrir WhatsApp', showCancelButton: true, cancelButtonText: 'Cerrar', confirmButtonColor: '#25D366' })
                        .then(x => { if (x.isConfirmed) window.open('https://wa.me/?text=' + encodeURIComponent('Le comparto mi tarjeta de presentación.'), '_blank'); });
                    return;
                }
                bajar(blob, nombre);
                if (formato === 'hoja') Swal.fire({ icon: 'success', title: 'Hoja lista', html: 'Imprímala en <b>cartulina carta</b> a <b>tamaño real (100 %)</b>. Corte por las líneas punteadas.', confirmButtonColor: '#0B3C74' });
            } catch (e) {
                jzOcultarCargando && jzOcultarCargando();
                // Corte de red durante una descarga: casi siempre es un gestor de descargas que la tomó.
                if (e instanceof TypeError && ['png', 'pdf', 'hoja'].includes(accion)) { toastDescarga(); return; }
                Swal.fire({ icon: 'error', title: 'No se pudo', text: e.message, confirmButtonColor: '#0B3C74' });
            }
        };
        function toastDescarga() {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Descarga iniciada', showConfirmButton: false, timer: 2500 });
        }
        refrescar();
    })();
    </script>
    @endpush
</x-layout>
