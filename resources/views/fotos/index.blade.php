@php
    $card = 'bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5';
    $lb = 'block text-[11px] font-semibold uppercase text-azul-800 mb-1';
    $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm';
@endphp
<x-layout titulo="Fotos carné y pasaporte" activo="fotos">
    <p class="text-gray-500 text-sm mb-4">
        Tome o suba la foto, el sistema le quita el fondo, la recorta a la medida y arma la hoja lista para imprimir con sus líneas de corte.
        @if($cliente) Foto para <b class="text-azul-950">{{ $cliente->nombreCompleto() }}</b>. @endif
    </p>

    @unless($fondoListo)
        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm p-3 flex gap-2">
            <x-icono name="triangle-alert" class="size-5 shrink-0" />
            <div>El quitador de fondo todavía no está instalado en el servidor. Mientras tanto se puede recortar e imprimir con el fondo original.
                @if(auth()->user()->esAdmin()) Para activarlo, en el servidor corra: <code class="bg-white px-1 rounded">php artisan micv:modelo-fondo</code>@endif
            </div>
        </div>
    @endunless

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-5">
        <div class="lg:col-span-3 space-y-5">
            {{-- 1. Foto --}}
            <section class="{{ $card }} p-4 sm:p-5">
                <h2 class="font-semibold text-azul-950 mb-3 flex items-center gap-2"><span class="size-6 rounded-full bg-azul-800 text-white text-xs flex items-center justify-center">1</span> La foto</h2>
                <div class="flex flex-wrap gap-2">
                    <button type="button" id="btnCamara" class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-2.5 rounded-lg"><x-icono name="camera" class="size-4" /> Usar la cámara</button>
                    <label class="inline-flex items-center gap-2 cursor-pointer bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg">
                        <x-icono name="upload" class="size-4" /> Subir foto
                        <input type="file" id="archivoFoto" accept="image/*" class="sr-only">
                    </label>
                    @if($cliente && $cliente->tieneFoto())
                        <button type="button" id="btnFotoCv" class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg"><x-icono name="user-round" class="size-4" /> Usar la foto del CV</button>
                    @endif
                </div>
                <ul class="mt-3 text-xs text-gray-500 grid sm:grid-cols-2 gap-x-4 gap-y-1">
                    <li class="flex gap-1.5"><x-icono name="check" class="size-3.5 text-emerald-600 mt-0.5 shrink-0" /> De frente, mirando a la cámara, sin lentes oscuros.</li>
                    <li class="flex gap-1.5"><x-icono name="check" class="size-3.5 text-emerald-600 mt-0.5 shrink-0" /> Buena luz en la cara, sin sombras fuertes.</li>
                    <li class="flex gap-1.5"><x-icono name="check" class="size-3.5 text-emerald-600 mt-0.5 shrink-0" /> Que se vean los hombros y la cabeza completa.</li>
                    <li class="flex gap-1.5"><x-icono name="check" class="size-3.5 text-emerald-600 mt-0.5 shrink-0" /> Cualquier fondo sirve: el sistema lo cambia.</li>
                </ul>
            </section>

            {{-- 2. Fondo --}}
            <section class="{{ $card }} p-4 sm:p-5">
                <h2 class="font-semibold text-azul-950 mb-3 flex items-center gap-2"><span class="size-6 rounded-full bg-azul-800 text-white text-xs flex items-center justify-center">2</span> El fondo</h2>
                <div class="flex flex-wrap items-center gap-2" id="fondos">
                    @foreach(\App\Support\FotoFormatos::FONDOS as $color => $nombre)
                        <label class="cursor-pointer {{ !$fondoListo && $color !== 'original' ? 'opacity-40 pointer-events-none' : '' }}">
                            <input type="radio" name="fondo" value="{{ $color }}" class="peer sr-only" @checked($fondoListo ? $color === '#ffffff' : $color === 'original')>
                            <span class="flex items-center gap-2 border-2 border-gray-200 rounded-xl px-3 py-2 text-xs font-semibold text-gray-600 peer-checked:border-azul-800 peer-checked:text-azul-800">
                                @if($color === 'original')<x-icono name="image" class="size-5" />@else<span class="size-5 rounded-full ring-1 ring-gray-300" style="background: {{ $color }}"></span>@endif
                                {{ $nombre }}
                            </span>
                        </label>
                    @endforeach
                    <label class="cursor-pointer {{ $fondoListo ? '' : 'opacity-40 pointer-events-none' }}">
                        <input type="radio" name="fondo" value="propio" class="peer sr-only">
                        <span class="flex items-center gap-2 border-2 border-gray-200 rounded-xl px-3 py-2 text-xs font-semibold text-gray-600 peer-checked:border-azul-800 peer-checked:text-azul-800">
                            <input type="color" id="colorPropio" value="#c62828" class="size-5 rounded-full border-0 p-0 bg-transparent"> Otro color
                        </span>
                    </label>
                </div>
                <p id="estadoFondo" class="text-xs text-gray-500 mt-2">Revise qué color pide la institución: lo más común es blanco.</p>
            </section>

            {{-- 3. Recorte --}}
            <section class="{{ $card }} p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h2 class="font-semibold text-azul-950 flex items-center gap-2"><span class="size-6 rounded-full bg-azul-800 text-white text-xs flex items-center justify-center">3</span> Acomode la cara</h2>
                    <div class="flex gap-1" id="herramientas">
                        @foreach(['izq' => 'rotate-ccw', 'der' => 'rotate-cw', 'menos' => 'zoom-out', 'mas' => 'zoom-in', 'centrar' => 'scan-face'] as $acc => $ic)
                            <button type="button" data-acc="{{ $acc }}" class="size-9 rounded-lg border border-gray-300 hover:border-azul-800 text-azul-800 flex items-center justify-center" title="{{ ['izq' => 'Girar a la izquierda', 'der' => 'Girar a la derecha', 'menos' => 'Alejar', 'mas' => 'Acercar', 'centrar' => 'Volver a encuadrar'][$acc] }}"><i data-lucide="{{ $ic }}" class="size-4"></i></button>
                        @endforeach
                    </div>
                </div>
                <div id="zonaRecorte" class="jz-foto-recorte relative rounded-xl overflow-hidden bg-gray-100 h-[420px] flex items-center justify-center">
                    <div id="vacio" class="text-center text-gray-400 px-6"><x-icono name="scan-face" class="size-14 mx-auto" /><p class="mt-2 text-sm">Aquí aparece la foto para acomodarla.</p></div>
                    <img id="imgRecorte" alt="" class="hidden max-w-full">
                </div>
                <p class="text-xs text-gray-500 mt-2">Arrastre la foto para moverla y use la rueda o los botones para acercar. La cabeza debe quedar dentro del óvalo y la barbilla sobre la línea.</p>
                <div class="grid grid-cols-2 gap-4 mt-3">
                    <label class="text-xs text-gray-600">Brillo <input type="range" id="brillo" min="80" max="130" value="100" class="w-full accent-[#0B3C74]"></label>
                    <label class="text-xs text-gray-600">Contraste <input type="range" id="contraste" min="80" max="130" value="100" class="w-full accent-[#0B3C74]"></label>
                </div>
            </section>
        </div>

        {{-- 4. Medida, papel y acciones --}}
        <div class="lg:col-span-2">
            <div class="lg:sticky lg:top-20 space-y-4">
                <section class="{{ $card }} p-4 sm:p-5 space-y-3">
                    <h2 class="font-semibold text-azul-950 flex items-center gap-2"><span class="size-6 rounded-full bg-azul-800 text-white text-xs flex items-center justify-center">4</span> Medida y papel</h2>
                    <div><label class="{{ $lb }}" for="formato">Medida de la foto</label>
                        <select id="formato" class="{{ $in }}">
                            @foreach(\App\Support\FotoFormatos::FORMATOS as $k => [$n])<option value="{{ $k }}">{{ $n }}</option>@endforeach
                        </select>
                        <p id="notaFormato" class="text-[11px] text-gray-400 mt-1"></p>
                    </div>
                    <div id="medidaPropia" class="hidden grid grid-cols-2 gap-2">
                        <div><label class="{{ $lb }}">Ancho (mm)</label><input type="number" id="anchoPropio" min="15" max="100" value="30" class="{{ $in }}"></div>
                        <div><label class="{{ $lb }}">Alto (mm)</label><input type="number" id="altoPropio" min="15" max="120" value="40" class="{{ $in }}"></div>
                    </div>
                    <div><label class="{{ $lb }}" for="papel">Papel</label>
                        <select id="papel" class="{{ $in }}">
                            @foreach(\App\Support\FotoFormatos::PAPELES as $k => [$n])<option value="{{ $k }}">{{ $n }}</option>@endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-3">
                        <div class="flex-1"><label class="{{ $lb }}" for="cantidad">Cantidad de fotos</label><input type="number" id="cantidad" min="1" value="" class="{{ $in }}"></div>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-600 pb-2.5"><input type="checkbox" id="marcas" checked> Líneas de corte</label>
                    </div>
                    <div class="flex items-center gap-3">
                        <svg id="miniHoja" viewBox="0 0 216 280" class="w-20 shrink-0 bg-white rounded shadow ring-1 ring-gray-200"></svg>
                        <p id="resumenHoja" class="text-sm text-gray-600"></p>
                    </div>
                </section>
                <section class="{{ $card }} p-3 grid grid-cols-2 gap-2">
                    <button type="button" id="btnHoja" disabled class="col-span-2 inline-flex items-center justify-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold py-3 rounded-lg disabled:opacity-40 disabled:hover:bg-azul-800"><x-icono name="printer" class="size-4" /> Hoja para imprimir (PDF)</button>
                    <button type="button" id="btnUna" disabled class="inline-flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-azul-800 text-sm font-semibold py-2.5 rounded-lg disabled:opacity-40"><x-icono name="image-down" class="size-4" /> Una foto (JPG)</button>
                    @if($cliente)
                        <button type="button" id="btnCv" disabled class="inline-flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-azul-800 text-sm font-semibold py-2.5 rounded-lg disabled:opacity-40"><x-icono name="file-user" class="size-4" /> Poner en su CV</button>
                    @else
                        <button type="button" id="btnTransparente" disabled class="inline-flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-azul-800 text-sm font-semibold py-2.5 rounded-lg disabled:opacity-40"><x-icono name="layers" class="size-4" /> Sin fondo (PNG)</button>
                    @endif
                    <p class="col-span-2 text-[11px] text-gray-400 leading-snug">Imprima a <b>tamaño real (100 %)</b>, sin "ajustar a la página", en papel fotográfico o de buena calidad.</p>
                </section>
            </div>
        </div>
    </div>

    {{-- Cámara --}}
    <div id="modalCamara" class="hidden fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-4 w-full max-w-xl">
            <div class="relative rounded-xl overflow-hidden bg-black aspect-[4/3]">
                <video id="video" autoplay playsinline muted class="w-full h-full object-cover -scale-x-100"></video>
                <div class="absolute inset-0 pointer-events-none flex items-center justify-center"><div class="w-[38%] aspect-[3/4] rounded-[50%] border-2 border-white/80 border-dashed -mt-[8%]"></div></div>
                <div id="cuenta" class="hidden absolute inset-0 flex items-center justify-center text-white text-7xl font-bold drop-shadow"></div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 mt-3">
                <select id="camaras" class="border border-gray-300 rounded-lg px-2 py-2 text-sm max-w-[55%]"></select>
                <div class="flex gap-2">
                    <button type="button" id="camCerrar" class="px-4 py-2.5 rounded-lg text-sm font-semibold text-gray-600 hover:bg-gray-100">Cancelar</button>
                    <button type="button" id="camTomar" class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg"><x-icono name="aperture" class="size-4" /> Tomar (3 s)</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <link rel="stylesheet" href="{{ asset('vendor/cropper.min.css') }}">
    <script src="{{ asset('vendor/cropper.min.js') }}"></script>
    <style>
        .jz-foto-recorte .cropper-bg { background-image: none; }
        .jz-foto-recorte .cropper-modal { opacity: .55; }
        .jz-foto-recorte .cropper-view-box { outline: 2px solid #fff; background: var(--fondo, transparent); }
        /* Guía: óvalo de la cara y línea de la barbilla */
        .jz-foto-recorte .cropper-face { background: transparent; opacity: 1; }
        .jz-foto-recorte .cropper-face::before { content: ''; position: absolute; left: 25%; right: 25%; top: 12%; height: 58%; border: 2px dashed rgba(255,255,255,.95); border-radius: 50%; box-shadow: 0 0 0 1px rgba(0,0,0,.25); }
        .jz-foto-recorte .cropper-face::after { content: ''; position: absolute; left: 30%; right: 30%; top: 70%; border-top: 2px solid rgba(255,255,255,.9); }
        .jz-foto-recorte .cropper-dashed { display: none; }
    </style>
    <script>
    (function () {
        const CFG = @json($configJs);
        const RUTAS = { procesar: @json(route('fotos.procesar')), hoja: @json(route('fotos.hoja')), cv: @json($cliente ? route('fotos.cv', $cliente) : null), fotoCv: @json($cliente && $cliente->tieneFoto() ? route('clientes.foto', $cliente) : null) };
        const FONDO_LISTO = @json($fondoListo);
        const NOMBRE = @json($cliente ? \Illuminate\Support\Str::slug($cliente->nombreCompleto(), '_') : 'foto');
        const token = document.querySelector('meta[name=csrf-token]').content;
        const $ = id => document.getElementById(id);
        const img = $('imgRecorte'), zona = $('zonaRecorte');
        let cropper = null, original = null, urlOriginal = null, sinFondo = null, pedido = 0;

        // ── Medidas
        function medida() {
            const f = $('formato').value;
            if (f === 'propio') return [Math.min(100, Math.max(15, +$('anchoPropio').value || 30)), Math.min(120, Math.max(15, +$('altoPropio').value || 40))];
            return [CFG.formatos[f].w, CFG.formatos[f].h];
        }
        function acomodo() {
            const [w, h] = medida(), p = CFG.papeles[$('papel').value], g = CFG.separacion;
            const cols = Math.max(0, Math.floor((p.w - 2 * p.m + g) / (w + g))), filas = Math.max(0, Math.floor((p.h - 2 * p.m + g) / (h + g)));
            return { w, h, p, cols, filas, max: cols * filas };
        }
        function dibujarHoja() {
            const a = acomodo(), cant = Math.min(a.max, +$('cantidad').value || a.max);
            const svg = $('miniHoja'); svg.setAttribute('viewBox', `0 0 ${a.p.w} ${a.p.h}`);
            const g = CFG.separacion, x0 = (a.p.w - (a.cols * a.w + (a.cols - 1) * g)) / 2, y0 = (a.p.h - (a.filas * a.h + (a.filas - 1) * g)) / 2;
            let s = '';
            for (let i = 0; i < cant; i++) { const c = i % a.cols, f = Math.floor(i / a.cols); s += `<rect x="${x0 + c * (a.w + g)}" y="${y0 + f * (a.h + g)}" width="${a.w}" height="${a.h}" rx="1" fill="#0B3C74" opacity=".75"/>`; }
            svg.innerHTML = s;
            $('cantidad').max = a.max; $('cantidad').placeholder = a.max;
            $('resumenHoja').innerHTML = a.max ? `Caben <b>${a.max}</b> fotos de ${String(a.w).replace('.', ',')} × ${String(a.h).replace('.', ',')} mm en ${a.p.nombre.toLowerCase()}.` : '<span class="text-red-600">Esa medida no cabe en ese papel.</span>';
            $('notaFormato').textContent = (CFG.formatos[$('formato').value] || {}).nota || '';
            $('medidaPropia').classList.toggle('hidden', $('formato').value !== 'propio');
            if (cropper) cropper.setAspectRatio(a.w / a.h);
        }
        ['formato', 'papel', 'cantidad', 'anchoPropio', 'altoPropio'].forEach(id => $(id).addEventListener('input', dibujarHoja));

        // ── Fondo
        function colorFondo() {
            const v = document.querySelector('input[name=fondo]:checked').value;
            return v === 'propio' ? $('colorPropio').value : v;
        }
        function aplicarFondoVisual() { const c = colorFondo(); zona.style.setProperty('--fondo', c === 'original' ? 'transparent' : c); }
        $('fondos').addEventListener('change', () => { aplicarFondoVisual(); cargarEnRecorte(); });
        $('colorPropio').addEventListener('input', () => { document.querySelector('input[name=fondo][value=propio]').checked = true; aplicarFondoVisual(); cargarEnRecorte(); });

        // ── Cargar foto: se achica en el navegador (más rápido de subir) y se le quita el fondo una vez (transparente).
        async function reducir(blob, max = 1600) {
            const bmp = await createImageBitmap(blob, { imageOrientation: 'from-image' });
            const k = Math.min(1, max / Math.max(bmp.width, bmp.height));
            const c = document.createElement('canvas'); c.width = Math.round(bmp.width * k); c.height = Math.round(bmp.height * k);
            c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
            return new Promise(r => c.toBlob(r, 'image/jpeg', 0.93));
        }
        async function nuevaFoto(blob) {
            original = await reducir(blob); sinFondo = null;
            if (urlOriginal) URL.revokeObjectURL(urlOriginal);
            urlOriginal = URL.createObjectURL(original);
            img.dataset.tipo = ''; // foto nueva: se vuelve a montar el recorte
            cargarEnRecorte();
            if (FONDO_LISTO) quitarFondo();
        }
        async function quitarFondo() {
            const n = ++pedido;
            $('estadoFondo').innerHTML = '<span class="inline-flex items-center gap-1.5 text-azul-800 font-semibold"><span class="size-3 rounded-full border-2 border-azul-800 border-t-transparent animate-spin"></span> Quitando el fondo… (unos segundos)</span>';
            const fd = new FormData(); fd.append('foto', original, 'foto.jpg'); fd.append('color', 'transparente');
            try {
                const r = await fetch(RUTAS.procesar, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const j = await r.json().catch(() => ({}));
                if (n !== pedido) return;
                if (!r.ok || !j.ok) throw new Error(r.status === 429 ? 'Muchas fotos seguidas. Espere un minuto.' : (j.mensaje || j.message || 'No se pudo quitar el fondo.'));
                sinFondo = j.imagen;
                $('estadoFondo').innerHTML = '<span class="text-emerald-700 font-semibold">Fondo quitado.</span> Puede cambiar el color cuando quiera.';
                cargarEnRecorte();
            } catch (e) {
                if (n !== pedido) return;
                $('estadoFondo').innerHTML = '<span class="text-red-600">' + e.message + '</span>';
            }
        }
        function cargarEnRecorte() {
            if (!original) return;
            const usarSinFondo = colorFondo() !== 'original' && sinFondo;
            const src = usarSinFondo ? sinFondo : urlOriginal;
            const tipo = usarSinFondo ? 'sinfondo' : 'original';
            if (img.dataset.tipo === tipo && cropper) { filtros(); return; } // misma imagen: solo cambia el color del fondo
            const datos = cropper && img.dataset.tipo ? cropper.getData() : null; // conserva el encuadre al cambiar de imagen
            img.dataset.tipo = tipo;
            if (cropper) cropper.destroy();
            $('vacio').classList.add('hidden'); img.classList.remove('hidden');
            img.onload = () => {
                const a = acomodo();
                cropper = new Cropper(img, { aspectRatio: a.w / a.h, viewMode: 0, dragMode: 'move', autoCropArea: 0.62, background: false, responsive: true, toggleDragModeOnDblclick: false,
                    ready() { if (datos) cropper.setData(datos); filtros(); } });
                ['btnHoja', 'btnUna', 'btnCv', 'btnTransparente'].forEach(id => $(id) && ($(id).disabled = false));
            };
            img.src = src;
        }
        function filtros() {
            const f = `brightness(${$('brillo').value}%) contrast(${$('contraste').value}%)`;
            zona.querySelectorAll('.cropper-canvas img, .cropper-view-box img').forEach(i => i.style.filter = f);
        }
        $('brillo').addEventListener('input', filtros); $('contraste').addEventListener('input', filtros);
        $('herramientas').addEventListener('click', e => {
            const b = e.target.closest('[data-acc]'); if (!b || !cropper) return;
            ({ izq: () => cropper.rotate(-90), der: () => cropper.rotate(90), mas: () => cropper.zoom(0.1), menos: () => cropper.zoom(-0.1), centrar: () => cropper.reset() })[b.dataset.acc]();
        });

        $('archivoFoto').addEventListener('change', function () { if (this.files[0]) nuevaFoto(this.files[0]); this.value = ''; });
        if ($('btnFotoCv')) $('btnFotoCv').addEventListener('click', async () => nuevaFoto(await (await fetch(RUTAS.fotoCv)).blob()));

        // ── Foto final a 600 ppp con el fondo y los ajustes
        function fotoFinal(tipo = 'image/jpeg', conFondo = true) {
            const [w, h] = medida(), pxW = Math.round(w / 25.4 * 600), pxH = Math.round(h / 25.4 * 600);
            const color = colorFondo();
            const base = cropper.getCroppedCanvas({ width: pxW, height: pxH, imageSmoothingQuality: 'high', fillColor: conFondo && color !== 'original' ? color : 'transparent' });
            const c = document.createElement('canvas'); c.width = pxW; c.height = pxH;
            const ctx = c.getContext('2d');
            if (conFondo) { ctx.fillStyle = color === 'original' ? '#ffffff' : color; ctx.fillRect(0, 0, pxW, pxH); }
            ctx.filter = `brightness(${$('brillo').value}%) contrast(${$('contraste').value}%)`;
            ctx.drawImage(base, 0, 0);
            return c.toDataURL(tipo, 0.95);
        }
        function bajar(url, nombre) { const a = document.createElement('a'); a.href = url; a.download = nombre; document.body.appendChild(a); a.click(); a.remove(); }

        $('btnUna').addEventListener('click', () => bajar(fotoFinal(), 'Foto_' + NOMBRE + '.jpg'));
        if ($('btnTransparente')) $('btnTransparente').addEventListener('click', () => {
            if (!sinFondo) return Swal.fire({ icon: 'info', title: 'Primero quite el fondo', text: 'Elija un color de fondo para que el sistema lo quite.', confirmButtonColor: '#0B3C74' });
            bajar(fotoFinal('image/png', false), 'Foto_' + NOMBRE + '_sin_fondo.png');
        });
        $('btnHoja').addEventListener('click', async () => {
            const a = acomodo(); if (!a.max) return;
            jzMostrarCargando('Armando la hoja…');
            try {
                const fd = new FormData();
                fd.append('imagen', fotoFinal()); fd.append('formato', $('formato').value); fd.append('papel', $('papel').value);
                fd.append('cantidad', Math.min(a.max, +$('cantidad').value || a.max)); fd.append('marcas', $('marcas').checked ? 1 : 0);
                fd.append('ancho', a.w); fd.append('alto', a.h);
                const r = await fetch(RUTAS.hoja, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const tipo = (r.headers.get('Content-Type') || '');
                if (!r.ok || tipo.includes('json') || tipo.includes('html')) { const j = await r.json().catch(() => ({})); throw new Error(r.status === 429 ? 'Muchas hojas seguidas. Espere un minuto.' : (j.mensaje || j.message || 'No se pudo armar la hoja.')); }
                const blob = new Blob([await r.blob()], { type: 'application/pdf' });
                jzOcultarCargando();
                const u = URL.createObjectURL(blob);
                bajar(u, `Fotos_${NOMBRE}_${$('formato').value}.pdf`);
                window.open(u, '_blank');
            } catch (e) { jzOcultarCargando(); Swal.fire({ icon: 'error', title: 'No se pudo', text: e.message, confirmButtonColor: '#0B3C74' }); }
        });
        if ($('btnCv')) $('btnCv').addEventListener('click', async () => {
            const fd = new FormData();
            const c = cropper.getCroppedCanvas({ width: 900, height: Math.round(900 * acomodo().h / acomodo().w), fillColor: colorFondo() === 'original' ? '#fff' : colorFondo() });
            fd.append('imagen', c.toDataURL('image/jpeg', 0.92));
            const r = await fetch(RUTAS.cv, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const j = await r.json().catch(() => ({}));
            Swal.fire({ icon: r.ok ? 'success' : 'error', title: r.ok ? '¡Listo!' : 'No se pudo', text: j.mensaje || j.message || '', confirmButtonColor: '#0B3C74' });
        });

        // ── Cámara (webcam del cyber o del celular)
        let flujo = null;
        async function abrirCamara(id) {
            if (flujo) flujo.getTracks().forEach(t => t.stop());
            flujo = await navigator.mediaDevices.getUserMedia({ video: id ? { deviceId: { exact: id }, width: { ideal: 1920 }, height: { ideal: 1440 } } : { facingMode: 'user', width: { ideal: 1920 }, height: { ideal: 1440 } }, audio: false });
            $('video').srcObject = flujo;
            const lista = (await navigator.mediaDevices.enumerateDevices()).filter(d => d.kind === 'videoinput');
            $('camaras').innerHTML = lista.map((d, i) => `<option value="${d.deviceId}">${d.label || 'Cámara ' + (i + 1)}</option>`).join('');
            const actual = flujo.getVideoTracks()[0].getSettings().deviceId; if (actual) $('camaras').value = actual;
        }
        function cerrarCamara() { if (flujo) flujo.getTracks().forEach(t => t.stop()); flujo = null; $('modalCamara').classList.add('hidden'); }
        $('btnCamara').addEventListener('click', async () => {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return $('archivoFoto').click();
            $('modalCamara').classList.remove('hidden');
            try { await abrirCamara(); } catch (e) { cerrarCamara(); Swal.fire({ icon: 'warning', title: 'No se pudo abrir la cámara', text: 'Revise el permiso de la cámara en el navegador (o que la página use https). También puede subir una foto.', confirmButtonColor: '#0B3C74' }); }
        });
        $('camaras').addEventListener('change', e => abrirCamara(e.target.value).catch(() => {}));
        $('camCerrar').addEventListener('click', cerrarCamara);
        $('camTomar').addEventListener('click', async () => {
            const cu = $('cuenta'); cu.classList.remove('hidden');
            for (const n of [3, 2, 1]) { cu.textContent = n; await new Promise(r => setTimeout(r, 800)); }
            cu.classList.add('hidden');
            const v = $('video'), c = document.createElement('canvas'); c.width = v.videoWidth; c.height = v.videoHeight;
            const ctx = c.getContext('2d'); ctx.translate(c.width, 0); ctx.scale(-1, 1); ctx.drawImage(v, 0, 0);
            cerrarCamara();
            c.toBlob(b => nuevaFoto(b), 'image/jpeg', 0.95);
        });

        aplicarFondoVisual(); dibujarHoja();
    })();
    </script>
    @endpush
</x-layout>
