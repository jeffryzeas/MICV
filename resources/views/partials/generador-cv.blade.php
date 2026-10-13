{{-- Generador de CV: diseño, colores, foto, vista previa y PDF. --}}
<div id="generar-cv" class="bg-white rounded-xl shadow p-6 mb-5" data-vista="{{ route('cv.vista', $cliente) }}" data-pdf="{{ route('cv.pdf', $cliente) }}">
    <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><i data-lucide="file-text" class="size-[18px] text-oro-500"></i> Currículum en PDF</h2>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="space-y-4">
            <div>
                <p class="text-xs font-semibold uppercase text-azul-800 mb-2">Diseño</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach(\App\Services\CvDatos::DISENOS as $k => $t)
                        <label class="jz-diseno cursor-pointer border-2 rounded-lg p-3 text-center transition-colors">
                            <input type="radio" name="diseno" value="{{ $k }}" class="hidden" @checked($cv['diseno_defecto'] === $k)>
                            <i data-lucide="{{ ['clasico' => 'scroll', 'pro' => 'panel-left', 'moderno' => 'layout-template', 'elegante' => 'feather'][$k] ?? 'file' }}" class="size-6 mx-auto text-azul-800"></i>
                            <div class="text-sm font-semibold text-azul-950 mt-1">{{ $k === 'clasico' ? 'Clásico' : \Illuminate\Support\Str::after($t, 'Pro ') }}</div>
                            <div class="text-[11px] text-gray-500 leading-tight">{{ \App\Services\CvDatos::DESCRIPCION[$k] }}</div>
                        </label>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-400 mt-1">Los diseños Pro siempre salen en <b>una sola hoja</b>. El Clásico lleva el escudo de {{ $cliente->datosPais()['nombre'] }}.</p>
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
            <div class="flex flex-col gap-2 pt-2">
                <button type="button" onclick="jzCv(false)" class="inline-flex items-center justify-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg">
                    <i data-lucide="eye" class="size-4"></i> Ver PDF
                </button>
                <button type="button" onclick="jzCv(true)" class="inline-flex items-center justify-center gap-1.5 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors">
                    <i data-lucide="download" class="size-4"></i> Descargar PDF
                </button>
            </div>
        </div>
        <div class="lg:col-span-2">
            <p class="text-xs font-semibold uppercase text-azul-800 mb-2">Vista previa</p>
            <iframe id="visorCv" class="w-full rounded-lg border border-gray-200 bg-gray-600" style="height:72vh" title="Vista previa del CV"></iframe>
        </div>
    </div>
</div>
@push('scripts')
<style>.jz-diseno{border-color:#e5e7eb}.jz-diseno.sel{border-color:rgb(var(--jz-azul-800));background:#f0f5fb}</style>
<script>
(function () {
    const caja = document.getElementById('generar-cv');
    const visor = document.getElementById('visorCv');
    const tieneFoto = @json($cliente->tieneFoto());
    function opciones(foto) {
        const diseno = (caja.querySelector('input[name=diseno]:checked') || {}).value || 'pro';
        const paleta = (caja.querySelector('input[name=paleta]:checked') || {}).value || '';
        caja.querySelectorAll('.jz-diseno').forEach(l => l.classList.toggle('sel', l.querySelector('input').checked));
        document.getElementById('bloquePaleta').style.opacity = diseno === 'clasico' ? '.35' : '1';
        return '?diseno=' + diseno + '&foto=' + (foto ? '1' : '0') + (diseno !== 'clasico' && paleta ? '&paleta=' + paleta : '');
    }
    function refrescar() { visor.src = caja.dataset.vista + opciones(tieneFoto); }
    caja.addEventListener('change', refrescar);
    refrescar();
    window.jzCv = function (descargar) {
        const abrir = function (foto) {
            const url = caja.dataset.pdf + opciones(foto) + (descargar ? '&descargar=1' : '');
            if (descargar) { window.location = url; } else { window.open(url, '_blank'); }
        };
        if (!tieneFoto) { abrir(false); return; }
        Swal.fire({
            title: '¿El CV lleva foto?', text: 'Elige si quieres incluir la foto en el documento.',
            icon: 'question', showDenyButton: true, showCancelButton: true,
            confirmButtonText: 'Con foto', denyButtonText: 'Sin foto', cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0B3C74', denyButtonColor: '#6b7280',
        }).then(r => { if (r.isConfirmed) abrir(true); else if (r.isDenied) abrir(false); });
    };
})();
</script>
@endpush
