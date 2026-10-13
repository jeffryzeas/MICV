@php
    $in = 'w-full border border-gray-300 rounded-lg px-3 py-3 text-base focus:border-azul-800 focus:ring-1 focus:ring-azul-800';
    $lb = 'block text-xs font-semibold uppercase text-azul-800 mb-1';
    $paises = collect(\App\Support\Pais::activos())->map(fn ($p) => ['docs' => $p['documentos'] ?? [], 'tel' => $p['telefono'] ?? '', 'mun' => $p['municipio'] ?? 'Municipio', 'dep' => $p['departamento'] ?? 'Departamento'])->all();
    $paisSel = old('pais', $paisDefecto);
    $estudios = old('estudios', [['nivel' => '', 'titulo' => '', 'institucion' => '', 'anio' => '']]);
    $experiencias = old('experiencias', [['cargo' => '', 'empresa' => '', 'fecha_inicio' => '', 'fecha_fin' => '', 'descripcion' => '']]);
    $idiomas = old('idiomas', [['idioma' => '', 'nivel' => 'intermedio']]);
    $referencias = old('referencias', [['nombre' => '', 'telefono' => ''], ['nombre' => '', 'telefono' => ''], ['nombre' => '', 'telefono' => '']]);
@endphp
<x-publico titulo="Mis datos para el CV" ancho="max-w-xl">
    <div class="text-center mb-5">
        <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Está en</p>
        <h2 class="text-xl font-bold text-azul-950">{{ $cyber->nombre }}</h2>
        <p class="text-sm text-gray-500 mt-1">Llene sus datos mientras espera. Al terminar le damos un número de turno y en el mostrador le imprimimos su CV.</p>
    </div>

    <form method="POST" action="{{ route('autoservicio.guardar', $cyber->codigo) }}" class="space-y-5" id="fAuto" novalidate>
        @csrf
        @include('partials.antibot')

        <fieldset class="space-y-3">
            <legend class="font-semibold text-azul-950 flex items-center gap-2 mb-1"><i data-lucide="id-card" class="size-5 text-oro-500"></i> Documento</legend>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="{{ $lb }}" for="aPais">País</label>
                    <select name="pais" id="aPais" class="{{ $in }}">
                        @foreach(\App\Support\Pais::porRegion() as $region => $lista)<optgroup label="{{ $region }}">@foreach($lista as $cod => $p)<option value="{{ $cod }}" @selected($paisSel === $cod)>{{ $p['bandera'] }} {{ $p['nombre'] }}</option>@endforeach</optgroup>@endforeach
                    </select></div>
                <div><label class="{{ $lb }}" for="aTipo">Tipo</label>
                    <select name="tipo_documento" id="aTipo" class="{{ $in }}" data-actual="{{ old('tipo_documento') }}"></select></div>
            </div>
            <div><label class="{{ $lb }}" for="aNumero">Número de documento *</label>
                <input type="text" name="numero_documento" id="aNumero" required value="{{ old('numero_documento') }}" autocomplete="off" class="{{ $in }} font-mono tracking-wide uppercase">
                <p id="aAyuda" class="text-xs text-gray-400 mt-1"></p></div>
        </fieldset>

        <fieldset class="space-y-3">
            <legend class="font-semibold text-azul-950 flex items-center gap-2 mb-1"><i data-lucide="user-round" class="size-5 text-oro-500"></i> Sus datos</legend>
            <div class="grid grid-cols-2 gap-3">
                @foreach(['primer_nombre' => 'Primer nombre *', 'segundo_nombre' => 'Segundo nombre', 'primer_apellido' => 'Primer apellido *', 'segundo_apellido' => 'Segundo apellido'] as $k => $t)
                    <div><label class="{{ $lb }}">{{ $t }}</label><input type="text" name="{{ $k }}" value="{{ old($k) }}" @if(str_contains($t, '*')) required @endif autocomplete="off" class="{{ $in }}"></div>
                @endforeach
                <div><label class="{{ $lb }}">Fecha de nacimiento</label><input type="date" name="fecha_nacimiento" value="{{ old('fecha_nacimiento') }}" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">Sexo</label><select name="sexo" class="{{ $in }}"><option value="">—</option>@foreach(['F' => 'Femenino', 'M' => 'Masculino', 'O' => 'Otro'] as $k => $t)<option value="{{ $k }}" @selected(old('sexo') === $k)>{{ $t }}</option>@endforeach</select></div>
                <div><label class="{{ $lb }}"><span data-et="mun">Municipio</span> donde nació</label><input type="text" name="municipio_nacimiento" value="{{ old('municipio_nacimiento') }}" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}"><span data-et="dep">Departamento</span></label><input type="text" name="departamento_nacimiento" value="{{ old('departamento_nacimiento') }}" class="{{ $in }}"></div>
                <div class="col-span-2"><label class="{{ $lb }}">Estado civil</label><select name="estado_civil" class="{{ $in }}"><option value="">—</option>@foreach(['Soltero(a)', 'Casado(a)', 'Unión de hecho', 'Divorciado(a)', 'Viudo(a)', 'Separado(a)'] as $e)<option @selected(old('estado_civil') === $e)>{{ $e }}</option>@endforeach</select></div>
            </div>
        </fieldset>

        <fieldset class="space-y-3">
            <legend class="font-semibold text-azul-950 flex items-center gap-2 mb-1"><i data-lucide="phone" class="size-5 text-oro-500"></i> Contacto</legend>
            <div class="grid grid-cols-[110px_1fr] gap-3">
                <div><label class="{{ $lb }}">Código</label><select name="telefono_codigo_pais" id="aCod" class="{{ $in }} px-2">@foreach(\App\Support\Pais::telefonos() as $cod => $t)<option value="{{ $cod }}" @selected(old('telefono_codigo_pais') === $cod)>{{ $t }}</option>@endforeach</select></div>
                <div><label class="{{ $lb }}">Celular *</label><input type="tel" name="telefono" required inputmode="tel" value="{{ old('telefono') }}" class="{{ $in }}"></div>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" name="telefono_whatsapp" value="1" @checked(old('telefono_whatsapp', '1'))> Tiene WhatsApp</label>
            <label class="{{ $lb }}">Nacionalidad<select name="nacionalidad" required class="{{ $in }}"><option value="">Seleccione su nacionalidad</option>@foreach(\App\Support\Nacionalidades::lista() as $codigo => [$nombrePais,$masculino,$femenino])<option value="{{ $masculino }}" @selected(old('nacionalidad') === $masculino || old('nacionalidad') === $femenino)>{{ $masculino }}{{ $masculino !== $femenino ? ' / ' . $femenino : '' }}</option>@endforeach</select></label><div><label class="{{ $lb }}">Correo</label><input type="email" name="correo" inputmode="email" value="{{ old('correo') }}" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Dirección</label><input type="text" name="direccion" value="{{ old('direccion') }}" placeholder="Ej: Barrio San José, 100 m sur de la iglesia" class="{{ $in }}"></div>
        </fieldset>

        <fieldset class="space-y-3">
            <legend class="font-semibold text-azul-950 flex items-center gap-2 mb-1"><i data-lucide="briefcase" class="size-5 text-oro-500"></i> Trabajo que busca</legend>
            <div><label class="{{ $lb }}">Profesión u oficio</label><input type="text" name="profesion" value="{{ old('profesion') }}" placeholder="Ej: Cajera, bodeguero, contador" class="{{ $in }}"></div>
            <div><label class="{{ $lb }}">Hable un poco de usted (opcional)</label><textarea name="perfil_profesional" rows="3" maxlength="1500" placeholder="Ej: Soy una persona responsable y puntual, con experiencia en atención al cliente." class="{{ $in }}">{{ old('perfil_profesional') }}</textarea></div>
            <div><label class="{{ $lb }}">Habilidades (separadas por coma)</label><input type="text" name="habilidades_texto" value="{{ old('habilidades_texto') }}" placeholder="Ej: Excel, manejo de caja, trabajo en equipo" class="{{ $in }}"></div>
        </fieldset>

        @include('partials.situacion-laboral')<label class="{{ $lb }}">Disponibilidad<input name="disponibilidad" maxlength="255" value="{{ old('disponibilidad') }}" class="{{ $in }}"></label><label class="{{ $lb }}">Cualidades separadas por coma<input name="cualidades" maxlength="255" value="{{ old('cualidades') }}" class="{{ $in }}"></label><details class="group rounded-xl border border-gray-200 p-3" @if(old('estudios')) open @endif>
            <summary class="font-semibold text-azul-950 flex items-center gap-2 cursor-pointer list-none"><i data-lucide="graduation-cap" class="size-5 text-oro-500"></i> Estudios <span class="text-xs text-gray-400 font-normal ml-auto">tocar para llenar</span></summary>
            <div class="space-y-3 mt-3" data-lista="estudios" data-max="30">
                @foreach($estudios as $i => $e)
                    <div class="jz-fila grid grid-cols-2 gap-2 border-t border-gray-100 pt-3 first:border-0 first:pt-0">
                        <div class="col-span-2"><select name="estudios[{{ $i }}][nivel]" class="{{ $in }}"><option value="">Nivel…</option>@foreach(\App\Models\ClienteEstudio::NIVELES as $k => $t)<option value="{{ $k }}" @selected(($e['nivel'] ?? '') === $k)>{{ $t }}</option>@endforeach</select></div>
                        <label class="text-xs">Período de estudios<input name="estudios[{{ $i }}][periodo]" maxlength="255" value="{{ $e['periodo'] ?? '' }}" class="{{ $in }}"></label><label class="text-xs">Detalle<input name="estudios[{{ $i }}][detalle]" maxlength="255" value="{{ $e['detalle'] ?? '' }}" class="{{ $in }}"></label><input type="text" name="estudios[{{ $i }}][titulo]" value="{{ $e['titulo'] ?? '' }}" placeholder="Título o carrera" class="{{ $in }} col-span-2">
                        <input type="text" name="estudios[{{ $i }}][institucion]" value="{{ $e['institucion'] ?? '' }}" placeholder="Colegio / universidad" class="{{ $in }}">
                        <input type="number" name="estudios[{{ $i }}][anio]" value="{{ $e['anio'] ?? '' }}" placeholder="Año" inputmode="numeric" class="{{ $in }}">
                    </div>
                @endforeach
            </div>
            <button type="button" class="jz-otra mt-2 text-sm font-semibold text-azul-800" data-para="estudios">+ Otro estudio</button>
        </details>

        <details class="group rounded-xl border border-gray-200 p-3" @if(old('experiencias')) open @endif>
            <summary class="font-semibold text-azul-950 flex items-center gap-2 cursor-pointer list-none"><i data-lucide="building-2" class="size-5 text-oro-500"></i> Experiencia de trabajo <span class="text-xs text-gray-400 font-normal ml-auto">tocar para llenar</span></summary>
            <div class="space-y-3 mt-3" data-lista="experiencias" data-max="5">
                @foreach($experiencias as $i => $e)
                    <div class="jz-fila grid grid-cols-2 gap-2 border-t border-gray-100 pt-3 first:border-0 first:pt-0">
                        <label class="text-xs">Ubicación del empleo<input name="experiencias[{{ $i }}][ubicacion]" maxlength="255" value="{{ $e['ubicacion'] ?? '' }}" class="{{ $in }}"></label><label class="text-xs">Jornada<input name="experiencias[{{ $i }}][jornada]" maxlength="255" value="{{ $e['jornada'] ?? '' }}" class="{{ $in }}"></label><label class="text-xs">Duración<input name="experiencias[{{ $i }}][duracion]" maxlength="255" value="{{ $e['duracion'] ?? '' }}" class="{{ $in }}"></label><input type="text" name="experiencias[{{ $i }}][cargo]" value="{{ $e['cargo'] ?? '' }}" placeholder="Puesto" class="{{ $in }}">
                        <input type="text" name="experiencias[{{ $i }}][empresa]" value="{{ $e['empresa'] ?? '' }}" placeholder="Empresa" class="{{ $in }}">
                        <label class="text-[11px] text-gray-500">Desde<input type="date" name="experiencias[{{ $i }}][fecha_inicio]" value="{{ $e['fecha_inicio'] ?? '' }}" class="{{ $in }}"></label>
                        <label class="text-[11px] text-gray-500">Hasta (vacío = actual)<input type="date" name="experiencias[{{ $i }}][fecha_fin]" value="{{ $e['fecha_fin'] ?? '' }}" class="{{ $in }}"></label>
                        <textarea name="experiencias[{{ $i }}][descripcion]" rows="2" placeholder="¿Qué hacía?" class="{{ $in }} col-span-2">{{ $e['descripcion'] ?? '' }}</textarea>
                    </div>
                @endforeach
            </div>
            <button type="button" class="jz-otra mt-2 text-sm font-semibold text-azul-800" data-para="experiencias">+ Otro trabajo</button>
        </details>

        <details class="group rounded-xl border border-gray-200 p-3" @if(old('idiomas')) open @endif>
            <summary class="font-semibold text-azul-950 flex items-center gap-2 cursor-pointer list-none"><i data-lucide="languages" class="size-5 text-oro-500"></i> Idiomas <span class="text-xs text-gray-400 font-normal ml-auto">opcional</span></summary>
            <div class="space-y-2 mt-3" data-lista="idiomas" data-max="4">
                @foreach($idiomas as $i => $x)
                    <div class="jz-fila grid grid-cols-2 gap-2">
                        <input type="text" name="idiomas[{{ $i }}][idioma]" value="{{ $x['idioma'] ?? '' }}" placeholder="Ej: Inglés" list="listaIdiomas" class="{{ $in }}">
                        <select name="idiomas[{{ $i }}][nivel]" class="{{ $in }}">@foreach(\App\Models\ClienteIdioma::NIVELES as $k => [$t])<option value="{{ $k }}" @selected(($x['nivel'] ?? 'intermedio') === $k)>{{ $t }}</option>@endforeach</select>
                    </div>
                @endforeach
            </div>
            <datalist id="listaIdiomas">@foreach(array_keys(\App\Models\ClienteIdioma::COMUNES) as $id)<option value="{{ $id }}">@endforeach</datalist>
            <button type="button" class="jz-otra mt-2 text-sm font-semibold text-azul-800" data-para="idiomas">+ Otro idioma</button>
        </details>

        <details class="group rounded-xl border border-gray-200 p-3" @if(old('referencias')) open @endif>
            <summary class="font-semibold text-azul-950 flex items-center gap-2 cursor-pointer list-none"><i data-lucide="users" class="size-5 text-oro-500"></i> Referencias <span class="text-xs text-gray-400 font-normal ml-auto">personas que lo recomiendan</span></summary>
            <div class="space-y-2 mt-3">
                @foreach($referencias as $i => $r)
                    <div class="grid grid-cols-2 gap-2">
                        <label class="text-xs">Detalle<input name="referencias[{{ $i }}][detalle]" maxlength="255" value="{{ $r['detalle'] ?? '' }}" class="{{ $in }}"></label><input type="text" name="referencias[{{ $i }}][nombre]" value="{{ $r['nombre'] ?? '' }}" placeholder="Nombre" class="{{ $in }}">
                        <input type="tel" name="referencias[{{ $i }}][telefono]" value="{{ $r['telefono'] ?? '' }}" placeholder="Teléfono" class="{{ $in }}">
                    </div>
                @endforeach
            </div>
        </details>

        <label class="flex items-start gap-3 text-sm text-gray-600 bg-gray-50 rounded-xl p-3">
            <input type="checkbox" name="acepto" value="1" required class="mt-1 size-4" @checked(old('acepto'))>
            <span>Autorizo a <b>{{ $cyber->nombre }}</b> a usar estos datos solo para hacer mi CV, según el <a href="{{ route('legal.ver', 'privacidad') }}" target="_blank" class="text-azul-800 underline">aviso de privacidad</a>. Si no llego a atenderme, se borran solos en {{ \App\Models\AutoservicioSolicitud::DIAS_GUARDADO }} días.</span>
        </label>

        <button class="w-full inline-flex items-center justify-center gap-2 bg-azul-800 hover:bg-oro-500 text-white font-semibold uppercase tracking-wide text-sm py-4 rounded-xl transition-colors">
            <i data-lucide="send" class="size-5"></i> Enviar mis datos
        </button>
    </form>

    <script>
    (function () {
        const PAISES = @json($paises), AYUDAS = @json(\App\Support\Documentos::ayudas());
        const pais = document.getElementById('aPais'), tipo = document.getElementById('aTipo'), cod = document.getElementById('aCod');
        function cambiarPais(primera) {
            const p = PAISES[pais.value] || { docs: {}, tel: '' };
            const antes = primera ? tipo.dataset.actual : '';
            tipo.innerHTML = Object.entries(p.docs).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
            if (antes && p.docs[antes]) tipo.value = antes;
            document.querySelector('[data-et=mun]').textContent = p.mun; document.querySelector('[data-et=dep]').textContent = p.dep;
            if (p.tel && (!primera || !@json(old('telefono_codigo_pais')))) cod.value = p.tel;
            ayuda();
        }
        function ayuda() { document.getElementById('aAyuda').textContent = AYUDAS[tipo.value] || ''; }
        pais.addEventListener('change', () => cambiarPais(false)); tipo.addEventListener('change', ayuda);
        cambiarPais(true);
        // Agregar otra fila (copia la última, vacía)
        document.querySelectorAll('.jz-otra').forEach(b => b.addEventListener('click', () => {
            const lista = document.querySelector('[data-lista="' + b.dataset.para + '"]'), filas = lista.querySelectorAll('.jz-fila');
            if (filas.length >= +lista.dataset.max) return;
            const n = filas[filas.length - 1].cloneNode(true), i = filas.length;
            n.querySelectorAll('[name]').forEach(el => { el.name = el.name.replace(/\[\d+\]/, '[' + i + ']'); if (el.tagName === 'SELECT') el.selectedIndex = el.name.includes('[nivel]') && b.dataset.para === 'idiomas' ? 2 : 0; else el.value = ''; });
            lista.appendChild(n); n.querySelector('input,select')?.focus();
        }));
        document.getElementById('fAuto').addEventListener('submit', function (e) {
            const faltan = [...this.querySelectorAll('[required]')].filter(i => i.type === 'checkbox' ? !i.checked : !i.value.trim());
            if (faltan.length) { e.preventDefault(); faltan[0].focus(); faltan[0].scrollIntoView({ block: 'center', behavior: 'smooth' }); faltan.forEach(i => i.classList.add('border-red-400')); }
        });
    })();
    </script>
</x-publico>
