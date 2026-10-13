@php
    $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5';
    $lb = 'block text-xs font-semibold uppercase text-azul-800 mb-1';
    $docs = \App\Support\Legal::DOCUMENTOS;
@endphp
<x-layout titulo="Textos legales" activo="legal">
    <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-4 mb-5 text-sm flex gap-3">
        <i data-lucide="info" class="size-5 shrink-0 mt-0.5"></i>
        <div>Los textos vienen listos como plantilla según la Ley 8968 (Costa Rica) y la Ley 787 (Nicaragua). <b>No son asesoría legal:</b> pídale a un abogado que los revise antes de publicar. Versión vigente: <b>{{ $cfg->legal_version ?: '1.0' }}</b>@if($cfg->legal_actualizado_en) · actualizada {{ $cfg->legal_actualizado_en->format('d/m/Y') }}@endif.</div>
    </div>

    <form method="POST" action="{{ route('admin.legal.update') }}" class="space-y-5">
        @csrf @method('PUT')
        <div class="bg-white rounded-xl shadow p-5 sm:p-6">
            <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><i data-lucide="building-2" class="size-[18px] text-oro-500"></i> Responsable de los datos</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-1"><label class="{{ $lb }}">Nombre o razón social *</label>
                    <input type="text" name="legal_responsable" value="{{ old('legal_responsable', $cfg->legal_responsable ?: $cfg->nombre_negocio) }}" required maxlength="160" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">Cédula física o jurídica / RUC</label>
                    <input type="text" name="legal_identificacion" value="{{ old('legal_identificacion', $cfg->legal_identificacion) }}" maxlength="60" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">País de la ley aplicable *</label>
                    <select name="legal_pais" class="{{ $in }}">
                        @foreach(\App\Support\Pais::DATOS as $cod => $p)
                            <option value="{{ $cod }}" @selected(old('legal_pais', $cfg->legal_pais ?: 'CR') === $cod)>{{ $p['bandera'] ?? '' }} {{ $p['nombre'] }}</option>
                        @endforeach
                    </select></div>
            </div>
            <p class="text-[11px] text-gray-400 mt-3">El correo, teléfono y dirección se toman de <a href="{{ route('admin.marca.edit') }}" class="underline">Marca del sistema</a>.</p>
        </div>

        <div class="bg-white rounded-xl shadow p-5 sm:p-6" id="jzLegal">
            <div class="flex flex-wrap gap-2 mb-4 border-b pb-3">
                @foreach($docs as $k => [$titulo, , $icono])
                    <button type="button" data-tab="{{ $k }}" class="jz-tab inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold border border-gray-200 text-gray-600">
                        <i data-lucide="{{ $icono }}" class="size-4"></i> {{ $titulo }}
                    </button>
                @endforeach
            </div>
            @foreach($docs as $k => [$titulo])
                <div data-panel="{{ $k }}" class="jz-panel {{ $loop->first ? '' : 'hidden' }}">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <label class="{{ $lb }} mb-0">{{ $titulo }} (formato Markdown)</label>
                        <a href="{{ route('legal.ver', $k) }}" target="_blank" class="text-xs text-azul-800 underline inline-flex items-center gap-1"><i data-lucide="external-link" class="size-3.5"></i> Ver publicado</a>
                    </div>
                    <textarea name="textos[{{ $k }}]" rows="22" class="{{ $in }} font-mono text-xs leading-relaxed">{{ old('textos.' . $k, $textos[$k]) }}</textarea>
                </div>
            @endforeach
            <details class="mt-3 text-xs text-gray-600">
                <summary class="cursor-pointer font-semibold">Variables que se reemplazan solas</summary>
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-1 font-mono">
                    @verbatim
                    <span>{{marca}} · nombre del sistema</span><span>{{responsable}} · responsable</span>
                    <span>{{identificacion_texto}} · ", identificación X"</span><span>{{direccion}} · dirección</span>
                    <span>{{correo}} · correo de soporte</span><span>{{telefono}} · teléfono</span>
                    <span>{{pais}} · país de la ley</span><span>{{version}} · {{fecha}}</span>
                    <span>{{url_privacidad}} · {{url_terminos}}</span><span>{{url_contrato}} · {{url_cookies}} · {{url_eliminar}}</span>
                    @endverbatim
                </div>
                <p class="mt-2">Si deja un texto igual al de fábrica, recibirá las mejoras de futuras actualizaciones.</p>
            </details>
        </div>

        <div class="bg-white rounded-xl shadow p-5 flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="nueva_version" value="1" class="mt-1">
                <span><b>Publicar como nueva versión</b> ({{ $cfg->legal_version ?: '1.0' }} → siguiente). Todos los usuarios y Cybers deberán aceptar los textos otra vez al entrar. Úselo solo con cambios importantes.</span>
            </label>
            <button type="submit" class="inline-flex items-center justify-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-6 py-3 rounded-lg shrink-0">
                <i data-lucide="check" class="size-4"></i> Guardar textos
            </button>
        </div>
    </form>

    <script>
    (function () {
        const tabs = document.querySelectorAll('#jzLegal .jz-tab'), panels = document.querySelectorAll('#jzLegal .jz-panel');
        function ir(k) {
            tabs.forEach(t => { const on = t.dataset.tab === k; t.classList.toggle('bg-azul-800', on); t.classList.toggle('text-white', on); t.classList.toggle('border-azul-800', on); t.classList.toggle('text-gray-600', !on); });
            panels.forEach(p => p.classList.toggle('hidden', p.dataset.panel !== k));
        }
        tabs.forEach(t => t.addEventListener('click', () => ir(t.dataset.tab)));
        // Si hay un error de validación, abrir la pestaña con el texto vacío.
        const vacio = [...panels].find(p => !p.querySelector('textarea').value.trim());
        ir(vacio ? vacio.dataset.panel : tabs[0].dataset.tab);
    })();
    </script>
</x-layout>
