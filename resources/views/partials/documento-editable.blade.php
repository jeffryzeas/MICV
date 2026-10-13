{{-- País del documento + tipo + número, con verificación en vivo. Uso: @include('partials.documento-editable', ['cliente' => $c]) --}}
@php
    $paisActual = old('pais', $cliente->pais ?: 'NI');
    $docsPais = \App\Support\Pais::de($paisActual)['documentos'];
    $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5';
    $lb = 'block text-xs font-semibold uppercase text-azul-800 mb-1';
@endphp
<section class="jz-seccion" data-titulo="Documento" id="sec-documento">
    <h3 class="text-sm font-bold uppercase text-azul-800 flex items-center gap-2 mb-3"><i data-lucide="id-card" class="size-4 text-oro-500"></i> Documento de identidad</h3>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label class="{{ $lb }}">País del documento</label>
            <select name="pais" id="fPais" class="{{ $in }}">
                @foreach(\App\Support\Pais::porRegion() as $region => $lista)<optgroup label="{{ $region }}">@foreach($lista as $cod => $p)<option value="{{ $cod }}" @selected($paisActual === $cod)>{{ $p['bandera'] }} {{ $p['nombre'] }}</option>@endforeach</optgroup>@endforeach
            </select>
            <p class="text-[11px] text-gray-400 mt-1">El país que emitió el documento. Ej.: un nicaragüense con DIMEX elige Costa Rica.</p>
        </div>
        <div>
            <label class="{{ $lb }}">Tipo de documento</label>
            <select name="tipo_documento" id="fTipoDoc" class="{{ $in }}">
                @foreach($docsPais as $k => $t)<option value="{{ $k }}" @selected(old('tipo_documento', $cliente->tipo_documento) === $k)>{{ $t }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="{{ $lb }}">Número *</label>
            <input type="text" name="numero_documento" id="fNumDoc" value="{{ old('numero_documento', $cliente->documentoFormateado()) }}" required autocomplete="off" class="{{ $in }} uppercase font-mono" data-ignorar="{{ $cliente->id }}">
        </div>
    </div>
    <p id="ayudaNumDoc" class="text-[11px] text-gray-400 mt-1"></p>
    <div id="estadoDoc" class="live-status"></div>
</section>
@push('scripts')
<script>
(function () {
    const PAISES = @json(\App\Support\Pais::paraJs());
    const pais = document.getElementById('fPais'), tipo = document.getElementById('fTipoDoc'), num = document.getElementById('fNumDoc');
    const caja = document.getElementById('estadoDoc');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const extra = document.getElementById('camposExtra'); if (extra) extra.classList.remove('hidden');
    let t = null;
    pais.addEventListener('change', function () {
        const docs = PAISES[pais.value].documentos;
        tipo.innerHTML = Object.keys(docs).map(k => '<option value="' + k + '">' + docs[k] + '</option>').join('');
        consultar();
    });
    function consultar() {
        clearTimeout(t);
        t = setTimeout(async function () {
            if (!num.value.trim()) { caja.className = 'live-status'; return; }
            const r = await fetch(@json(route('api.consultar-documento')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ tipo_documento: tipo.value, numero_documento: num.value }) });
            if (!r.ok) return;
            const d = await r.json();
            let ok = true, html = '';
            if (d.cedula) { ok = d.cedula.valida; html = ok ? '✓ Cédula válida · ' + (d.cedula.municipio || '') + ' / ' + (d.cedula.departamento || '') + ' · ' + (d.cedula.edad ?? '') + ' años' : '✗ ' + d.cedula.errores.join(' · '); }
            if (d.cr) { ok = d.cr.valida; html = ok ? '✓ Número válido' + (d.cr.resumen ? ' · ' + d.cr.resumen : '') + (d.cr.aviso ? '<br>⚠ ' + d.cr.aviso : '') : '✗ ' + d.cr.errores.join(' · ');
                if (ok) window.jzLlenarDesdeDocumento && jzLlenarDesdeDocumento(d.cr); }
            if (d.existe && (!d.cliente || String(d.cliente.id) !== num.dataset.ignorar)) { ok = false; html += (html ? '<br>' : '') + '⚠ Ese documento ya está registrado para otra persona.'; }
            caja.className = 'live-status show ' + (ok ? 'ok' : 'err'); caja.innerHTML = html || 'Documento disponible.';
        }, 450);
    }
    const AYUDA = @json(\App\Support\Documentos::ayudas());
    function ayuda() { document.getElementById('ayudaNumDoc').textContent = AYUDA[tipo.value] || ''; }
    num.addEventListener('input', consultar); tipo.addEventListener('change', function () { ayuda(); consultar(); }); pais.addEventListener('change', ayuda); ayuda();
})();
</script>
@endpush
