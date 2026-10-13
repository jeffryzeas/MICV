{{-- Generador de CV: diseño, colores, foto, vista previa y PDF. --}}
@php
    $esUsuario = auth()->user()?->esCliente();
    $correoDestino = $esUsuario ? auth()->user()->email : $cliente->correo;
@endphp
<div id="generar-cv" class="bg-white rounded-xl shadow p-4 sm:p-6 mb-5" data-vista="{{ route('cv.vista', $cliente) }}" data-pdf="{{ route('cv.pdf', $cliente) }}" data-enviar="{{ route('cv.enviar', $cliente) }}">
    <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><i data-lucide="file-text" class="size-[18px] text-oro-500"></i> Currículum en PDF</h2>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="space-y-4">
            <div>
                <p class="text-xs font-semibold uppercase text-azul-800 mb-2">Diseño</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach(\App\Services\CvDatos::DISENOS as $k => $t)
                        <label class="jz-diseno cursor-pointer border-2 rounded-lg p-3 text-center transition-colors">
                            <input type="radio" name="diseno" value="{{ $k }}" class="hidden" @checked($cv['diseno_defecto'] === $k)>
                            <i data-lucide="{{ ['clasico' => 'scroll', 'pro' => 'panel-left', 'moderno' => 'layout-template', 'elegante' => 'feather', 'ejecutivo' => 'rows-3', 'creativo' => 'sparkles'][$k] ?? 'file' }}" class="size-6 mx-auto text-azul-800"></i>
                            <div class="text-sm font-semibold text-azul-950 mt-1">{{ $k === 'clasico' ? 'Clásico' : \Illuminate\Support\Str::after($t, 'Pro ') }}</div>
                            <div class="text-[11px] text-gray-500 leading-tight">{{ \App\Services\CvDatos::DESCRIPCION[$k] }}</div>
                        </label>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-400 mt-1">Los diseños Pro siempre salen en <b>una sola hoja</b>. @if($cliente->datosPais()['escudo'])El Clásico lleva el escudo de {{ $cliente->datosPais()['nombre'] }}.@else El Clásico es formal, con marco doble.@endif</p>
            </div>
            <div id="bloquePaleta">
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
                        <span class="flex items-center justify-center gap-2 border-2 border-gray-200 rounded-lg py-2 text-sm font-semibold text-gray-600 peer-checked:border-azul-800 peer-checked:bg-blue-50 peer-checked:text-azul-800">🇪🇸 Español</span></label>
                    <label class="cursor-pointer"><input type="radio" name="idioma" value="en" class="peer hidden">
                        <span class="flex items-center justify-center gap-2 border-2 border-gray-200 rounded-lg py-2 text-sm font-semibold text-gray-600 peer-checked:border-azul-800 peer-checked:bg-blue-50 peer-checked:text-azul-800">🇺🇸 English</span></label>
                </div>
                <p class="text-[11px] text-gray-400 mt-1">En inglés se traducen los títulos. El perfil y el oficio salen en inglés si los escribió en "Mis datos → Perfil".</p>
            </div>
            <div class="flex flex-col gap-2 pt-1">
                <button type="button" onclick="jzCv('descargar')" class="inline-flex items-center justify-center gap-1.5 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-3 rounded-lg transition-colors">
                    <i data-lucide="download" class="size-4"></i> Descargar PDF
                </button>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" onclick="jzCv('ver')" class="inline-flex items-center justify-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-azul-800 text-sm font-semibold px-3 py-2.5 rounded-lg">
                        <i data-lucide="eye" class="size-4"></i> Ver PDF
                    </button>
                    <button type="button" onclick="jzCv('correo')" @disabled(!$correoDestino) title="{{ $correoDestino ? 'Se envía a ' . $correoDestino : 'No hay correo registrado' }}" class="inline-flex items-center justify-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-azul-800 text-sm font-semibold px-3 py-2.5 rounded-lg disabled:opacity-40">
                        <i data-lucide="send" class="size-4"></i> {{ $esUsuario ? 'A mi correo' : 'Al correo' }}
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
        document.getElementById('bloquePaleta').style.opacity = diseno === 'clasico' ? '.35' : '1';
        return { diseno: diseno, foto: foto ? '1' : '0', idioma: idioma || sel('idioma', 'es'), paleta: diseno !== 'clasico' ? paleta : '' };
    }
    const qs = o => '?' + new URLSearchParams(Object.entries(o).filter(([k, v]) => v !== '')).toString();
    function refrescar() { visor.src = caja.dataset.vista + qs(opciones(tieneFoto)); }
    caja.addEventListener('change', refrescar);
    refrescar();

    // Al exportar se pregunta (igual que la foto) el idioma del CV.
    window.jzCv = function (accion) {
        const titulos = { descargar: 'Descargar el CV', ver: 'Ver el CV', correo: @json($esUsuario ? 'Enviar el CV a mi correo' : 'Enviar el CV por correo') };
        const botones = { descargar: 'Descargar', ver: 'Ver PDF', correo: 'Enviar' };
        const idiomaActual = sel('idioma', 'es');
        const opcion = (n, v, txt, on) => '<label class="jz-op"><input type="radio" name="' + n + '" value="' + v + '"' + (on ? ' checked' : '') + '><span>' + txt + '</span></label>';
        let html = '<div class="text-left space-y-3">';
        if (tieneFoto) html += '<div><p class="jz-op-t">¿Lleva foto?</p><div class="jz-ops">' + opcion('sw_foto', '1', '📷 Con foto', true) + opcion('sw_foto', '0', 'Sin foto', false) + '</div></div>';
        html += '<div><p class="jz-op-t">Idioma</p><div class="jz-ops">' + opcion('sw_idioma', 'es', '🇪🇸 Español', idiomaActual === 'es') + opcion('sw_idioma', 'en', '🇺🇸 English', idiomaActual === 'en') + '</div></div>';
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
