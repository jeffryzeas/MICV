{{-- Generador de CV: diseño, colores, foto, vista previa y PDF. --}}
@php
    $esUsuario = auth()->user()?->esCliente();
    $correoDestino = $esUsuario ? auth()->user()->email : $cliente->correo;
@endphp
<div id="generar-cv" class="bg-white rounded-xl shadow p-4 sm:p-6 mb-5" data-vista="{{ route('cv.vista', $cliente) }}" data-pdf="{{ route('cv.pdf', $cliente) }}" data-word="{{ route('cv.word', $cliente) }}" data-enviar="{{ route('cv.enviar', $cliente) }}">
    <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><x-icono name="file-text" class="size-[18px] text-oro-500" /> Currículum en PDF</h2>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="space-y-4">
            <div>
                <p class="label-campo">Diseño del currículum</p>
                <button type="button" id="cv-open-gallery" class="btn btn-pri w-full" aria-haspopup="dialog" aria-controls="cv-gallery"><x-icono name="layout-template" class="size-5"/><span class="flex-1 text-left"><strong class="block">Ver los 31 diseños y elegir</strong><small class="mt-1 block text-xs font-normal text-white/90">30 estilos visuales + formato ATS</small></span><x-icono name="chevron-down" class="size-4"/></button>
                <div class="mt-3 flex items-center gap-2" aria-hidden="true">@foreach(['esencial','balance','ats'] as $ejemplo)<img src="{{ asset('images/cv/'.$ejemplo.'.png') }}" alt="" class="h-16 w-12 rounded border border-gray-200 bg-gray-50 object-contain" loading="lazy">@endforeach<span class="text-xs text-gray-500">Pulse el botón para ver las plantillas y cambiar el diseño.</span></div>
                <p id="cv-selected-name" class="mt-3 text-sm font-semibold text-azul-800" aria-live="polite"></p>
                <p id="cv-ats-note" hidden class="mt-2 rounded-lg border border-blue-200 bg-blue-50 p-3 text-xs text-blue-900">ATS utiliza una columna, texto seleccionable y títulos claros, sin foto ni gráficos. Puede ocupar más de una página para conservar la legibilidad.</p>
                <dialog id="cv-gallery" class="cv-gallery" aria-labelledby="cv-gallery-title" aria-describedby="cv-gallery-help">
                  <header class="cv-gallery-header"><div class="flex items-start justify-between gap-3"><div><h3 id="cv-gallery-title" class="text-lg font-semibold text-azul-950">Elija el diseño de su currículum</h3><p id="cv-gallery-help" class="mt-1 text-sm text-gray-500">Desplácese hacia abajo para ver los 31 diseños. Pulse una tarjeta para aplicarla.</p></div><button type="button" id="cv-close-gallery" class="btn btn-sec shrink-0" aria-label="Cerrar galería"><x-icono name="x" class="size-4"/></button></div>
                    <label class="mt-4 block"><span class="sr-only">Buscar diseños</span><input id="cv-design-search" type="search" class="campo" placeholder="Buscar diseño, estilo o ATS" autocomplete="off"></label>
                    <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="Filtrar diseños"><button type="button" class="btn btn-sec cv-filter" data-filter="all" aria-pressed="true">Todos (31)</button><button type="button" class="btn btn-sec cv-filter" data-filter="simple" aria-pressed="false">Sencillos</button><button type="button" class="btn btn-sec cv-filter" data-filter="new" aria-pressed="false">Nuevos (10)</button><button type="button" class="btn btn-sec cv-filter" data-filter="ats" aria-pressed="false">ATS</button></div>
                    <p id="cv-results-count" class="mt-3 text-xs text-gray-500" aria-live="polite">31 diseños disponibles</p>
                  </header>
                  <div id="cv-gallery-list" class="cv-gallery-scroll" tabindex="0" aria-label="Galería de diseños; desplácese para ver más"><div class="cv-gallery-grid">
                  @foreach(\App\Services\CvDatos::DISENOS as $k=>$t)
                    <div class="jz-diseno cv-choice" data-title="{{ $t }}" data-description="{{ \App\Services\CvDatos::DESCRIPCION[$k] }}" data-simple="{{ in_array($k,\App\Services\CvDatos::SENCILLOS,true)?'1':'0' }}" data-new="{{ in_array($k,\App\Services\CvDatos::NUEVOS,true)?'1':'0' }}" data-ats="{{ $k==='ats'?'1':'0' }}">
                      <input type="radio" name="diseno" value="{{ $k }}" class="sr-only" tabindex="-1" aria-hidden="true" @checked($cv['diseno_defecto']===$k)>
                      <button type="button" class="cv-choice-button" data-choose="{{ $k }}" aria-label="Usar diseño {{ $t }}" aria-pressed="{{ $cv['diseno_defecto']===$k?'true':'false' }}"><span class="cv-card-top"><span class="text-xs font-semibold text-gray-500">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }} / 31</span>@if($k==='ats')<span class="cv-card-tag">ATS</span>@elseif(in_array($k,\App\Services\CvDatos::NUEVOS,true))<span class="cv-card-tag">Nuevo</span>@endif<span class="cv-selected-badge hidden" data-selected>En uso</span></span><img loading="lazy" src="{{ asset('images/cv/'.$k.'.png') }}" alt="Captura de ejemplo del diseño {{ $t }}" width="816" height="1056"><strong>{{ $t }}</strong><small>{{ \App\Services\CvDatos::DESCRIPCION[$k] }}</small><span class="cv-card-action"><x-icono name="check" class="size-4"/>Usar este diseño</span></button>
                    </div>
                  @endforeach
                  </div><p id="cv-no-results" hidden class="py-12 text-center text-sm text-gray-500">No encontramos diseños con esa búsqueda. Pruebe otro nombre o elija Todos.</p></div>
                  <footer class="cv-gallery-footer"><span class="text-xs text-gray-500"><x-icono name="arrow-down" class="size-4"/> Desplácese para ver más diseños</span><button id="cv-scroll-selected" type="button" class="btn btn-sec">Ver diseño actual</button></footer>
                </dialog>
                <p class="text-[11px] text-gray-400 mt-1">Los diseños visuales se ajustan a una hoja. El formato ATS conserva un tamaño legible y puede ocupar más de una página. @if($cliente->datosPais()['escudo'] || !empty($cliente->datosPais()['escudo_subido']))El Clásico lleva el escudo de {{ $cliente->datosPais()['nombre'] }}.@else El Clásico es formal, con marco doble.@endif</p>
            </div>
            <p class="text-xs text-gray-500">Carta Amarillo y Azul conserva los colores del diseño original.</p><div id="bloquePaleta">
                <p class="text-xs font-semibold uppercase text-azul-800 mb-2">Colores (diseños Pro)</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($paletas as $k => $p)
                        @php
                            $lat = $k === 'cyber' ? ($cyberColores?->color_primario ?? '#0B3C74') : $p['lateral'];
                            $ac  = $k === 'cyber' ? ($cyberColores?->color_secundario ?? '#C9971F') : $p['acento'];
                        @endphp
                        <label class="cursor-pointer" title="{{ $p['nombre'] }}">
                            <input type="radio" name="paleta" value="{{ $k }}" class="peer hidden" @checked($cv['pro_paleta'] === $k)>
                            <span class="block size-9 rounded-full border-2 border-white ring-1 ring-gray-300 peer-checked:ring-2 peer-checked:ring-azul-800"
                                  style="background:linear-gradient(135deg, {{ $lat }} 55%, {{ $ac }} 55%)"></span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase text-azul-800 mb-2">Idioma del CV</p>
                <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer"><input type="radio" name="idioma" value="es" class="peer hidden" checked>
                        <span class="flex items-center justify-center gap-2 border-2 border-gray-200 rounded-lg py-2 text-sm font-semibold text-gray-600 peer-checked:border-azul-800 peer-checked:bg-blue-50 peer-checked:text-azul-800">Español</span></label>
                    <label class="cursor-pointer"><input type="radio" name="idioma" value="en" class="peer hidden">
                        <span class="flex items-center justify-center gap-2 border-2 border-gray-200 rounded-lg py-2 text-sm font-semibold text-gray-600 peer-checked:border-azul-800 peer-checked:bg-blue-50 peer-checked:text-azul-800">Inglés</span></label>
                </div>
                <p class="text-[11px] text-gray-400 mt-1">En inglés se traducen los títulos. El perfil y el oficio salen en inglés si los escribió en "Mis datos → Perfil".</p>
            </div>
            <div class="flex flex-col gap-2 pt-1">
                <button type="button" onclick="jzCv('descargar')" class="inline-flex items-center justify-center gap-1.5 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-3 rounded-lg transition-colors">
                    <x-icono name="download" class="size-4" /> Descargar PDF
                </button>
                <button type="button" onclick="jzCv('word')" class="inline-flex items-center justify-center gap-1.5 bg-[#2B579A] hover:bg-[#1e3f73] text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors">
                    <x-icono name="file-type" class="size-4" /> Descargar en Word (.docx)
                </button>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="jzCv('ver')" class="inline-flex items-center justify-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-azul-800 text-sm font-semibold px-3 py-2.5 rounded-lg">
                        <x-icono name="eye" class="size-4" /> Ver PDF
                    </button>
                    <button type="button" onclick="jzCv('correo')" @disabled(!$correoDestino) title="{{ $correoDestino ? 'Se envía a ' . $correoDestino : 'No hay correo registrado' }}" class="inline-flex items-center justify-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-azul-800 text-sm font-semibold px-3 py-2.5 rounded-lg disabled:opacity-40">
                        <x-icono name="send" class="size-4" /> {{ $esUsuario ? 'A mi correo' : 'Al correo' }}
                    </button>
                </div>
                @unless($correoDestino)<p class="text-[11px] text-gray-400">Para enviarlo por correo, agregue el correo de la persona en sus datos.</p>@endunless
            </div>
        </div>
        <div class="lg:col-span-2">
            <p class="text-xs font-semibold uppercase text-azul-800 mb-2">Vista previa</p>
            <iframe id="visorCv" class="w-full rounded-lg border border-gray-200 bg-gray-600" style="height:72vh" title="Vista previa del CV"></iframe>
        </div>
    </div>
</div>
@push('scripts')
<script src="{{ asset('js/cv-gallery.js') }}" defer></script>
<style>.jz-diseno{border-color:#e5e7eb}.jz-diseno.sel{border-color:rgb(var(--jz-azul-800));background:#f0f5fb}
.jz-op-t{font-size:12px;font-weight:700;text-transform:uppercase;color:#0B3C74;margin-bottom:6px}.jz-ops{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.jz-op input{display:none}.jz-op span{display:block;text-align:center;border:2px solid #e5e7eb;border-radius:10px;padding:10px 6px;font-weight:600;font-size:14px;color:#4b5563;cursor:pointer}
.jz-op input:checked+span{border-color:#0B3C74;background:#eff5fc;color:#0B3C74}</style>
<script>
(function () {
    const caja = document.getElementById('generar-cv');
    const visor = document.getElementById('visorCv');
    const tieneFoto = @json($cliente->tieneFoto());
    function sel(n, def) { return (caja.querySelector('input[name=' + n + ']:checked') || {}).value || def; }
    function opciones(foto, idioma) {
        const diseno = sel('diseno', 'pro'), paleta = sel('paleta', '');
        caja.querySelectorAll('.jz-diseno').forEach(l => l.classList.toggle('sel', l.querySelector('input').checked));
        const paletaFija=['clasico','carta','ats'].includes(diseno);
        const bloquePaleta=document.getElementById('bloquePaleta');
        bloquePaleta.style.opacity=paletaFija?'.35':'1';
        bloquePaleta.setAttribute('aria-disabled',String(paletaFija));
        bloquePaleta.querySelectorAll('input').forEach(input=>input.disabled=paletaFija);
        return { diseno: diseno, foto: diseno!=='ats' && foto ? '1' : '0', idioma: idioma || sel('idioma', 'es'), paleta: !['clasico','ats'].includes(diseno) ? paleta : '' };
    }
    const qs = o => '?' + new URLSearchParams(Object.entries(o).filter(([k, v]) => v !== '')).toString();
    function refrescar() { visor.src = caja.dataset.vista + qs(opciones(tieneFoto)); }
    caja.addEventListener('change', refrescar);
    refrescar();

    // Al exportar se pregunta (igual que la foto) el idioma del CV.
    window.jzCv = function (accion) {
        const titulos = { descargar: 'Descargar el CV', word: 'CV en Word (editable)', ver: 'Ver el CV', correo: @json($esUsuario ? 'Enviar el CV a mi correo' : 'Enviar el CV por correo') };
        const botones = { descargar: 'Descargar', word: 'Descargar Word', ver: 'Ver PDF', correo: 'Enviar' };
        const idiomaActual = sel('idioma', 'es');
        const opcion = (n, v, txt, on) => '<label class="jz-op"><input type="radio" name="' + n + '" value="' + v + '"' + (on ? ' checked' : '') + '><span>' + txt + '</span></label>';
        let html = '<div class="text-left space-y-3">';
        if (tieneFoto && sel('diseno','pro')!=='ats') html += '<div><p class="jz-op-t">¿Lleva foto?</p><div class="jz-ops">' + opcion('sw_foto', '1', 'Con foto', true) + opcion('sw_foto', '0', 'Sin foto', false) + '</div></div>';
        html += '<div><p class="jz-op-t">Idioma</p><div class="jz-ops">' + opcion('sw_idioma', 'es', 'Español', idiomaActual === 'es') + opcion('sw_idioma', 'en', 'Inglés', idiomaActual === 'en') + '</div></div>';
        if (accion === 'word') html += '<p class="text-xs text-gray-500">Sale en un diseño sencillo para que pueda cambiar cualquier texto en Word.</p>';
        if (accion === 'correo') html += '<p class="text-xs text-gray-500">Se enviará a <b>' + @json($correoDestino ?? '') + '</b>.</p>';
        html += '</div>';
        Swal.fire({
            title: titulos[accion], html: html, showCancelButton: true, confirmButtonText: botones[accion], cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0B3C74', focusConfirm: false,
            preConfirm: () => ({ foto: (document.querySelector('input[name=sw_foto]:checked') || { value: tieneFoto ? '1' : '0' }).value === '1', idioma: document.querySelector('input[name=sw_idioma]:checked').value })
        }).then(async function (r) {
            if (!r.isConfirmed) return;
            const o = opciones(r.value.foto, r.value.idioma);
            if (accion === 'descargar') { window.location = caja.dataset.pdf + qs(Object.assign(o, { descargar: 1 })); return; }
            if (accion === 'word') { window.location = caja.dataset.word + qs({ diseno: o.diseno, foto: o.foto, idioma: o.idioma, paleta: o.paleta }); return; }
            if (accion === 'ver') { window.open(caja.dataset.pdf + qs(o), '_blank'); return; }
            Swal.fire({ title: 'Enviando su CV…', text: 'Estamos preparando el PDF y enviándolo.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            try {
                const resp = await fetch(caja.dataset.enviar, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify(o) });
                const d = await resp.json().catch(() => ({ ok: false, mensaje: resp.status === 429 ? 'Ya envió varios correos. Espere unos minutos.' : 'No se pudo enviar.' }));
                Swal.fire({ icon: d.ok ? 'success' : 'error', title: d.ok ? '¡Enviado!' : 'No se pudo enviar', text: d.mensaje || (resp.status === 429 ? 'Ya envió varios correos. Espere unos minutos.' : ''), confirmButtonColor: '#0B3C74' });
            } catch (e) { Swal.fire({ icon: 'error', title: 'Sin conexión', text: 'Revise su internet e intente otra vez.' }); }
        });
    };
})();
</script>
@endpush
