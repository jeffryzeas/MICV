{{--
    Datos personales de la persona, ordenados por secciones.
    Uso: @include('partials.campos-registro-cliente', ['cliente' => $c, 'cybers' => $cybers, 'paisActual' => 'NI', 'notas' => true])
    Requiere un elemento #fPais (select o hidden) con el país del documento.
--}}
@php
    $cliente = $cliente ?? null;
    $val = fn (string $campo, $default = null) => old($campo, $cliente?->{$campo} ?? $default);
    $paisActual = old('pais', $paisActual ?? ($cliente?->pais ?: 'NI'));
    $P = \App\Support\Pais::de($paisActual);
    $redes = $P['companias'];
    foreach ([$val('telefono_red'), $val('telefono_secundario_red')] as $r) { if ($r && !in_array($r, $redes)) { $redes[] = $r; } }
    $mostrarNotas = $notas ?? true;
    $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5';
    $lb = 'block text-xs font-semibold uppercase text-azul-800 mb-1';
    $nacActual = $val('nacionalidad', $P['gentilicio']);
@endphp
<style>
    .jz-campo { position: relative; }
    .jz-campo .jz-icono { position: absolute; left: 10px; top: 34px; color: #9ca3af; pointer-events: none; }
    .jz-campo.sin-label .jz-icono, .jz-campo.sin-label .jz-check { top: 13px; }
    .jz-campo input, .jz-campo select { padding-left: 2.25rem !important; transition: border-color .15s, background-color .15s; }
    .jz-campo.jz-lleno input, .jz-campo.jz-lleno select { border-color: #10b981 !important; background-color: #f0fdf4; }
    .jz-check { position: absolute; right: 10px; top: 34px; color: #10b981; display: none; }
    .jz-campo.jz-lleno .jz-check { display: block; }
    .dark .jz-campo.jz-lleno input, .dark .jz-campo.jz-lleno select { background-color: #0f2a20 !important; }
</style>

{{-- ── Nombre completo ── --}}
<section class="jz-seccion" data-titulo="Nombre" id="sec-nombre">
    <h3 class="text-sm font-bold uppercase text-azul-800 flex items-center gap-2 mb-3"><x-icono name="user" class="size-4 text-oro-500" /> Nombre completo</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach(['primer_nombre' => ['Primer nombre *', 'María', true], 'segundo_nombre' => ['Segundo nombre', 'José', false], 'primer_apellido' => ['Primer apellido *', 'Pérez', true], 'segundo_apellido' => ['Segundo apellido', 'Gómez', false]] as $campo => [$t, $ej, $req])
            <div class="jz-campo">
                <label class="{{ $lb }}">{{ $t }}</label>
                <x-icono name="user" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
                <input type="text" name="{{ $campo }}" @if($req) required @endif placeholder="Ej: {{ $ej }}" value="{{ $val($campo) }}" autocomplete="off" class="{{ $in }}">
            </div>
        @endforeach
    </div>
    @if(isset($cybers) && $cybers->isNotEmpty() && !$cliente)
        <div class="jz-campo mt-4 max-w-md">
            <label class="{{ $lb }}">Cyber al que pertenece</label>
            <x-icono name="building-2" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
            <select name="cyber_id" class="{{ $in }}">
                <option value="">— Ninguno (solo el admin lo ve) —</option>
                @foreach($cybers as $cy)<option value="{{ $cy->id }}" @selected((string) old('cyber_id') === (string) $cy->id)>{{ $cy->nombre }}</option>@endforeach
            </select>
        </div>
    @endif
</section>

{{-- ── Nacimiento y nacionalidad ── --}}
<section class="jz-seccion border-t border-gray-200 pt-5 mt-5" data-titulo="Nacimiento" id="sec-nacimiento">
    <h3 class="text-sm font-bold uppercase text-azul-800 flex items-center gap-2 mb-3"><x-icono name="cake" class="size-4 text-oro-500" /> Nacimiento y nacionalidad</h3>
    <div id="camposExtra" class="{{ $cliente ? '' : 'hidden' }} mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="jz-campo">
                <label class="{{ $lb }}">Fecha de nacimiento *</label>
                <x-icono name="calendar" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
                <input type="date" name="fecha_nacimiento" id="fFN" value="{{ old('fecha_nacimiento', optional($cliente?->fecha_nacimiento)->format('Y-m-d')) }}" class="{{ $in }}">
            </div>
            <div class="jz-campo">
                <label class="{{ $lb }}"><span data-etq="municipio">{{ $P['municipio'] }}</span> de nacimiento *</label>
                <x-icono name="map-pin" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
                <input type="text" name="municipio_nacimiento" id="fMuni" value="{{ $val('municipio_nacimiento') }}" class="{{ $in }}">
            </div>
            <div class="jz-campo">
                <label class="{{ $lb }}"><span data-etq="departamento">{{ $P['departamento'] }}</span> de nacimiento *</label>
                <x-icono name="map" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
                <input type="text" name="departamento_nacimiento" id="fDepto" value="{{ $val('departamento_nacimiento') }}" class="{{ $in }}">
            </div>
        </div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="jz-campo">
            <label class="{{ $lb }}">Sexo</label>
            <x-icono name="users" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
            <select name="sexo" id="fSexo" class="{{ $in }}">
                <option value="">—</option>
                @foreach(['M' => 'Masculino', 'F' => 'Femenino', 'O' => 'Otro'] as $sv => $sl)<option value="{{ $sv }}" @selected($val('sexo') === $sv)>{{ $sl }}</option>@endforeach
            </select>
        </div>
        <div class="jz-campo">
            <label class="{{ $lb }}">Nacionalidad</label>
            <x-icono name="flag" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
            <select id="fNacSel" class="{{ $in }}" data-actual="{{ $nacActual }}"></select>
            <input type="text" name="nacionalidad" id="fNacionalidad" value="{{ $nacActual }}" maxlength="60" placeholder="Escriba la nacionalidad" class="{{ $in }} mt-2 hidden">
        </div>
        <div class="jz-campo">
            <label class="{{ $lb }}">Estado civil</label>
            <x-icono name="heart" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
            <select name="estado_civil" id="jzEstadoCivil" data-guardado="{{ $val('estado_civil') }}" class="{{ $in }}"><option value="">—</option></select>
        </div>
        <div class="jz-campo">
            <label class="{{ $lb }}">Número de hijos</label>
            <x-icono name="baby" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
            <input type="number" name="numero_hijos" min="0" max="30" inputmode="numeric" value="{{ $val('numero_hijos') }}" class="{{ $in }}">
        </div>
    </div>
</section>

{{-- ── Contacto ── --}}
<section class="jz-seccion border-t border-gray-200 pt-5 mt-5" data-titulo="Contacto" id="sec-contacto">
    <h3 class="text-sm font-bold uppercase text-azul-800 flex items-center gap-2 mb-3"><x-icono name="phone" class="size-4 text-oro-500" /> Contacto</h3>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div>
            <label class="{{ $lb }}">Celular</label>
            <div class="grid grid-cols-[7rem_1fr] sm:grid-cols-[7rem_1fr_7rem] gap-2">
                <select name="telefono_codigo_pais" id="fCodTel" class="w-full border border-gray-300 rounded-lg px-2 py-2.5 text-sm">
                    @foreach(\App\Support\Pais::telefonos() as $cv => $cl)<option value="{{ $cv }}" @selected($val('telefono_codigo_pais', $P['telefono'] ?: '+505') === $cv)>{{ $cl }}</option>@endforeach
                </select>
                <div class="jz-campo sin-label">
                    <x-icono name="phone" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
                    <input type="tel" name="telefono" inputmode="tel" placeholder="8888 8888" value="{{ $val('telefono') }}" class="{{ $in }}">
                </div>
                <select name="telefono_red" class="jz-redes col-span-2 sm:col-span-1 w-full border border-gray-300 rounded-lg px-2 py-2.5 text-sm" data-actual="{{ $val('telefono_red') }}">
                    @foreach($redes as $r)<option value="{{ $r }}" @selected($val('telefono_red') === $r)>{{ $r }}</option>@endforeach
                </select>
            </div>
            <input type="hidden" name="telefono_whatsapp" value="0">
            <label class="inline-flex items-center gap-2 text-sm text-gray-600 mt-2"><input type="checkbox" name="telefono_whatsapp" value="1" @checked($val('telefono_whatsapp'))> <x-icono name="message-circle" class="size-4 text-emerald-600" /> Este número tiene WhatsApp</label>
        </div>
        <div>
            <label class="{{ $lb }}">Celular secundario (opcional)</label>
            <div class="grid grid-cols-1 sm:grid-cols-[1fr_7rem] gap-2">
                <div class="jz-campo sin-label">
                    <x-icono name="phone" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
                    <input type="tel" name="telefono_secundario" inputmode="tel" placeholder="Opcional" value="{{ $val('telefono_secundario') }}" class="{{ $in }}">
                </div>
                <select name="telefono_secundario_red" class="jz-redes w-full border border-gray-300 rounded-lg px-2 py-2.5 text-sm" data-vacio="1" data-actual="{{ $val('telefono_secundario_red') }}">
                    <option value="">—</option>
                    @foreach($redes as $r)<option value="{{ $r }}" @selected($val('telefono_secundario_red') === $r)>{{ $r }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="jz-campo">
            <label class="{{ $lb }}">Correo electrónico</label>
            <x-icono name="mail" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
            <input type="email" name="correo" inputmode="email" placeholder="Ej: maria.perez@gmail.com" value="{{ $val('correo') }}" class="{{ $in }}">
        </div>
        <div class="jz-campo">
            <label class="{{ $lb }}">Dirección donde vive</label>
            <x-icono name="map-pin" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
            <input type="text" name="direccion" placeholder="Ej: Del parque central, 200 m norte" value="{{ $val('direccion') }}" class="{{ $in }}">
        </div>
    </div>
    <h4 class="text-xs font-bold uppercase text-gray-500 mt-5 mb-2 flex items-center gap-2"><x-icono name="link" class="size-3.5" /> Perfiles en internet <span class="font-normal normal-case">(opcional, salen con su ícono en el CV)</span></h4>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="jz-campo">
            <label class="{{ $lb }}">LinkedIn</label>
            <x-icono name="linkedin" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
            <input type="text" name="linkedin" autocapitalize="off" placeholder="Su usuario o el enlace" value="{{ $val('linkedin') }}" class="{{ $in }}">
        </div>
        <div class="jz-campo">
            <label class="{{ $lb }}">Sitio web o portafolio</label>
            <x-icono name="globe" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
            <input type="text" name="sitio_web" autocapitalize="off" inputmode="url" placeholder="Ej: miportafolio.com" value="{{ $val('sitio_web') }}" class="{{ $in }}">
        </div>
        <div class="jz-campo">
            <label class="{{ $lb }}">GitHub (programadores)</label>
            <x-icono name="github" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
            <input type="text" name="github" autocapitalize="off" placeholder="Su usuario" value="{{ $val('github') }}" class="{{ $in }}">
        </div>
    </div>
</section>

{{-- ── Trabajo y seguro social ── --}}
<section class="jz-seccion border-t border-gray-200 pt-5 mt-5" data-titulo="Trabajo" id="sec-trabajo">
    <h3 class="text-sm font-bold uppercase text-azul-800 flex items-center gap-2 mb-3"><x-icono name="briefcase" class="size-4 text-oro-500" /> Trabajo y seguro social</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="jz-campo">
            <label class="{{ $lb }}">Profesión u oficio</label>
            <x-icono name="briefcase" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
            <input type="text" name="profesion" placeholder="Ej: Técnico en contabilidad" value="{{ $val('profesion') }}" class="{{ $in }}">
        </div>
        <div class="jz-campo {{ $P['seguro_largo'] ? '' : 'hidden' }}" id="campoSeguro">
            <label class="{{ $lb }}" data-etq="seguro_largo">{{ $P['seguro_largo'] }}</label>
            <x-icono name="badge-check" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
            <input type="text" name="inss_nss" placeholder="Opcional" value="{{ $val('inss_nss') }}" class="{{ $in }}">
        </div>
    </div>
</section>

{{-- ── Familia ── --}}
<section class="jz-seccion border-t border-gray-200 pt-5 mt-5" data-titulo="Familia" id="sec-familia">
    <h3 class="text-sm font-bold uppercase text-azul-800 flex items-center gap-2 mb-3"><x-icono name="users" class="size-4 text-oro-500" /> Familia</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach(['nombre_padre' => 'Nombre del padre', 'nombre_madre' => 'Nombre de la madre'] as $campo => $t)
            <div class="jz-campo">
                <label class="{{ $lb }}">{{ $t }}</label>
                <x-icono name="user" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
                <input type="text" name="{{ $campo }}" placeholder="Opcional" value="{{ $val($campo) }}" class="{{ $in }}">
            </div>
        @endforeach
    </div>
</section>

@if($mostrarNotas)
<section class="jz-seccion border-t border-gray-200 pt-5 mt-5" data-titulo="Notas" id="sec-notas">
    <div class="jz-campo">
        <label class="{{ $lb }}">Notas internas (no salen en el CV)</label>
        <x-icono name="sticky-note" class="jz-icono size-4" /><x-icono name="check-circle-2" class="jz-check size-4" />
        <input type="text" name="notas" placeholder="Cualquier detalle adicional" value="{{ $val('notas') }}" class="{{ $in }}">
    </div>
</section>
@endif

<script>
document.addEventListener('input', function (e) {
    if (e.target.matches('.jz-campo input, .jz-campo select')) e.target.closest('.jz-campo').classList.toggle('jz-lleno', e.target.value.trim() !== '');
});
document.addEventListener('change', function (e) {
    if (e.target.matches('.jz-campo select')) e.target.closest('.jz-campo').classList.toggle('jz-lleno', e.target.value.trim() !== '');
});
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.jz-campo input, .jz-campo select').forEach(function (el) {
        if (el.value && el.value.trim() !== '' && el.type !== 'hidden') el.closest('.jz-campo').classList.add('jz-lleno');
    });
});

(function () {
    const PAISES = @json(\App\Support\Pais::paraJs());
    const NAC = @json(\App\Support\Nacionalidades::paraJs());
    const selSexo = document.getElementById('fSexo');
    const selEstado = document.getElementById('jzEstadoCivil');
    const selNac = document.getElementById('fNacSel');
    const txtNac = document.getElementById('fNacionalidad');
    const sexo = () => (selSexo && selSexo.value === 'F') ? 'F' : 'M';

    // Estado civil según el sexo (Soltero / Soltera)
    const ESTADOS = [
        { base: 'soltero', M: 'Soltero', F: 'Soltera' }, { base: 'casado', M: 'Casado', F: 'Casada' },
        { base: 'union', M: 'Unión de hecho', F: 'Unión de hecho' }, { base: 'divorciado', M: 'Divorciado', F: 'Divorciada' },
        { base: 'viudo', M: 'Viudo', F: 'Viuda' }, { base: 'separado', M: 'Separado', F: 'Separada' },
    ];
    function baseDe(t) {
        t = (t || '').toLowerCase();
        if (t.startsWith('solter')) return 'soltero'; if (t.startsWith('casad')) return 'casado';
        if (t.startsWith('unión') || t.startsWith('union')) return 'union'; if (t.startsWith('divorciad')) return 'divorciado';
        if (t.startsWith('viud')) return 'viudo'; if (t.startsWith('separad')) return 'separado'; return '';
    }
    function estados() {
        if (!selEstado) return;
        const b = baseDe(selEstado.value) || baseDe(selEstado.dataset.guardado);
        selEstado.innerHTML = '<option value="">—</option>' + ESTADOS.map(o => '<option value="' + o[sexo()] + '"' + (o.base === b ? ' selected' : '') + '>' + o[sexo()] + '</option>').join('');
        selEstado.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // Nacionalidad: se elige de la lista; "Otra" permite escribirla
    function codigoDe(texto) {
        const t = (texto || '').trim().toLowerCase(); if (!t) return '';
        const n = NAC.find(x => x.m.toLowerCase() === t || x.f.toLowerCase() === t); return n ? n.c : '_otra';
    }
    function nacionalidades(codigoForzado) {
        if (!selNac) return;
        const actual = codigoForzado !== undefined ? codigoForzado : (selNac.value || codigoDe(txtNac.value));
        selNac.innerHTML = '<option value="">— Elija —</option>' + NAC.map(n => '<option value="' + n.c + '"' + (n.c === actual ? ' selected' : '') + '>' + n[sexo() === 'F' ? 'f' : 'm'] + ' (' + n.p + ')</option>').join('')
            + '<option value="_otra"' + (actual === '_otra' ? ' selected' : '') + '>Otra (escribirla)…</option>';
        sincronizarNac();
    }
    function sincronizarNac() {
        const v = selNac.value;
        txtNac.classList.toggle('hidden', v !== '_otra');
        if (v === '_otra') {
            // Si lo escrito ya está en la lista, se elige de la lista (y se adapta a Masculino/Femenino).
            const c = codigoDe(txtNac.value);
            if (c && c !== '_otra') { selNac.value = c; return sincronizarNac(); }
            selNac.closest('.jz-campo').classList.add('jz-lleno'); return;
        }
        const n = NAC.find(x => x.c === v);
        txtNac.value = n ? (sexo() === 'F' ? n.f : n.m) : '';
        selNac.closest('.jz-campo').classList.toggle('jz-lleno', !!v);
    }
    if (selNac) {
        selNac.addEventListener('change', function () { if (selNac.value === '_otra') { txtNac.value = ''; setTimeout(() => txtNac.focus(), 0); } sincronizarNac(); });
        txtNac.addEventListener('change', sincronizarNac);
    }
    if (selSexo) selSexo.addEventListener('change', function () { estados(); nacionalidades(); });
    estados(); nacionalidades();

    // País del documento → etiquetas, código telefónico, compañías y nacionalidad sugerida
    // Lo que se puede leer del número de documento (departamento, fecha, sexo) se llena solo si está vacío.
    window.jzLlenarDesdeDocumento = function (v) {
        const poner = (id, valor, ev) => { const el = document.getElementById(id); if (el && valor && !el.value) { el.value = valor; el.dispatchEvent(new Event(ev || 'input', { bubbles: true })); } };
        poner('fDepto', v.departamento); poner('fFN', v.fecha_nacimiento); poner('fSexo', v.sexo, 'change');
    };

    window.jzAplicarPais = function (cod, cambiarValores) {
        const p = PAISES[cod] || PAISES.NI;
        const cs = document.getElementById('campoSeguro'); if (cs) cs.classList.toggle('hidden', !p.seguro_largo);
        document.querySelectorAll('[data-etq]').forEach(el => { const k = el.getAttribute('data-etq'); if (p[k]) el.textContent = p[k]; });
        if (cambiarValores) {
            const tel = document.getElementById('fCodTel'); if (tel && p.telefono) tel.value = p.telefono;
            if (selNac && NAC.some(n => n.c === cod) && !selNac.value) nacionalidades(cod);
        }
        document.querySelectorAll('select.jz-redes').forEach(function (sel) {
            const actual = sel.value || sel.dataset.actual || '';
            const lista = p.companias.slice(); if (actual && !lista.includes(actual)) lista.push(actual);
            sel.innerHTML = (sel.dataset.vacio ? '<option value="">—</option>' : '') + lista.map(c => '<option' + (c === actual ? ' selected' : '') + '>' + c + '</option>').join('');
        });
    };
    const fp = document.getElementById('fPais');
    if (fp && fp.tagName === 'SELECT') fp.addEventListener('change', function () { jzAplicarPais(fp.value, true); });
})();
</script>
