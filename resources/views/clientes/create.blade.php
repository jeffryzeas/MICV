<x-layout titulo="Registrar cliente" activo="clientes-nuevo">

    <p class="text-gray-500 mb-5">Primero elija la nacionalidad: el formulario se adapta al país (documentos, INSS o CCSS, municipio o cantón, código telefónico). Con cédula nicaragüense la fecha, el lugar de nacimiento y la edad se llenan solos.</p>

    <div class="bg-white rounded-xl shadow p-6" id="boxConsulta">
        <h2 class="font-semibold text-azul-950 flex items-center gap-2 mb-4">
            <i data-lucide="search" class="size-[18px] text-oro-500"></i> Consultar documento
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
            <div>
                <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">1. Nacionalidad / país del documento</label>
                <select id="paisDoc" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    @foreach(\App\Support\Pais::DATOS as $cod => $p)<option value="{{ $cod }}">{{ $p['bandera'] }} {{ $p['nombre'] }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">2. Tipo de documento</label>
                <select id="tipoDoc" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    @foreach(\App\Support\Pais::de('NI')['documentos'] as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach
                </select>
            </div>
        </div>
        <div id="cedulaInputs" class="cedula-box mt-4">
            <input type="text" id="cedP1" maxlength="3" placeholder="001" inputmode="numeric" class="w-16">
            <span class="sep">-</span>
            <input type="text" id="cedP2" maxlength="6" placeholder="150890" inputmode="numeric" class="w-28">
            <span class="sep">-</span>
            <input type="text" id="cedP3" maxlength="4" placeholder="1234" inputmode="numeric" class="w-20">
            <input type="text" id="cedP4" maxlength="1" placeholder="A" class="w-12 uppercase">
        </div>
        <div id="otroDocInput" class="mt-4" style="display:none;">
            <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Número</label>
            <input type="text" id="numeroDoc" placeholder="Número de documento" class="w-full max-w-sm border border-gray-300 rounded-lg px-3 py-2.5">
            <p id="ayudaDoc" class="text-xs text-gray-400 mt-1"></p>
        </div>
        <div id="liveStatus" class="live-status"></div>
        <div class="mt-5 flex gap-3 flex-wrap">
            <button type="button" onclick="continuarRegistro()" class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                <i data-lucide="arrow-right" class="size-4"></i> Continuar
            </button>
            <a href="{{ route('clientes.index') }}" class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors"><i data-lucide="list" class="size-4"></i> Ver lista</a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow p-6 mt-5" id="formRegistro" style="display:none;">
        <h2 class="font-semibold text-azul-950 flex items-center gap-2 mb-4">
            <i data-lucide="user-plus" class="size-[18px] text-oro-500"></i> Completar datos
        </h2>
        <form method="POST" action="{{ route('clientes.store') }}" enctype="multipart/form-data" onsubmit="return confirmarGuardar(event)">
            @csrf
            <input type="hidden" name="tipo_documento" id="fTipo" value="cedula">
            <input type="hidden" name="pais" id="fPais" value="NI">
            <input type="hidden" name="numero_documento" id="fNumero">
            <nav data-indice class="sticky top-14 z-10 flex gap-2 overflow-x-auto py-2 mb-3 bg-white/95 backdrop-blur"></nav>
            <p id="docResumen" class="font-semibold text-azul-800 mb-2"></p>
            <div id="datosCedulaAuto" class="mb-4 p-3 bg-blue-50 rounded-lg text-sm" style="display:none;">
                <div id="autoInfo"></div>
            </div>
            @include('partials.campos-registro-cliente', ['cybers' => $cybers])

            <section class="jz-seccion border-t border-gray-200 pt-5 mt-5" data-titulo="Foto" id="sec-foto">
                <label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Foto (opcional, tipo carné)</label>
                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp,image/gif" class="text-sm">
            </section>

            <x-cv-form-fields :cliente="$clienteNuevo" />

            <button type="submit" class="mt-5 inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors"><i data-lucide="check" class="size-4"></i> Guardar cliente completo (incluye CV)</button>
        </form>
    </div>

    @push('scripts')
    <script src="{{ asset('js/documento-live.js') }}"></script>
    <script>

    // El modal de "Verificando..." usa el spinner de SweetAlert. Si se abre
    // otro modal mientras ese se esta cerrando, el nuevo hereda el estado de
    // carga y aparece SIN botones. Por eso se apaga el spinner y se espera
    // un instante antes de mostrar el aviso.
    function jzAviso(opciones) {
        try { Swal.hideLoading(); } catch (e) {}
        Swal.close();
        setTimeout(function () {
            Swal.fire(Object.assign({
                showConfirmButton: true,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#0B3C74',
                allowOutsideClick: true,
                allowEscapeKey: true,
            }, opciones));
        }, 80);
    }
    document.body.dataset.apiDocumento = '{{ route('api.consultar-documento') }}';

    (function () {
        const PAISES = @json(\App\Support\Pais::paraJs());
        const AYUDA = { cedula_cr: '9 dígitos, ej. 1-0234-0567 (la provincia sale del primer número).', dimex: '11 o 12 dígitos, sin guiones.', pasaporte: 'Tal como aparece en el pasaporte.', residencia: 'Número de la cédula de residencia.', partida_nacimiento: 'Código de persona del certificado de nacimiento.', otro: 'Número del documento.' };
        const pais = document.getElementById('paisDoc'), tipo = document.getElementById('tipoDoc');
        function ayuda() { const a = document.getElementById('ayudaDoc'); if (a) a.textContent = AYUDA[tipo.value] || ''; }
        pais.addEventListener('change', function () {
            const docs = PAISES[pais.value].documentos;
            tipo.innerHTML = Object.keys(docs).map(k => '<option value="' + k + '">' + docs[k] + '</option>').join('');
            tipo.dispatchEvent(new Event('change'));
            ayuda();
        });
        tipo.addEventListener('change', ayuda);
    })();

    async function continuarRegistro() {
        if (!window.JZDocumento) {
            jzAviso({icon: 'error', title:'Error de carga', text:'No se cargó el script de validación. Revisa la consola (F12).'});
            return;
        }
        const tipo = document.getElementById('tipoDoc').value;
        Swal.fire({ title: 'Verificando documento…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        let data;
        try { data = await window.JZDocumento.consultarInmediato(); }
        catch (err) { console.error(err); jzAviso({icon: 'error', title:'Error inesperado', text:'Revisa la consola (F12).'}); return; }
        Swal.close();

        if (!data) { jzAviso({icon: 'error', title:'Sin respuesta', text:'Revisa la consola (F12).'}); return; }
        if (data.vacio) { jzAviso({icon: 'info', title:'Documento vacío', text:'Escribe el número completo.'}); return; }
        if (!data.ok) { jzAviso({icon: 'error', title:'Error del servidor', text: data.error || 'No se pudo consultar.'}); return; }
        if (tipo === 'cedula' && data.cedula && !data.cedula.valida) {
            jzAviso({icon: 'error', title:'Cédula inválida', html:(data.cedula.errores||[]).join('<br>')}); return;
        }
        if (data.cr && !data.cr.valida) {
            jzAviso({icon: 'error', title:'Número inválido', html:(data.cr.errores||[]).join('<br>')}); return;
        }
        if (data.existe && data.cliente && data.en_mi_cyber) {
            const r0 = await Swal.fire({
                icon: 'info', title: 'Ya está en su lista',
                html: '<b>' + data.cliente.nombre_completo + '</b><br><small>Esta persona ya está registrada en su Cyber.</small>',
                confirmButtonText: 'Ver su ficha', confirmButtonColor: '#0B3C74', showCancelButton: true, cancelButtonText: 'Cerrar',
            });
            if (r0.isConfirmed && data.cliente.url) window.location = data.cliente.url;
            return;
        }
        if (data.existe && data.cliente) {
            const c = data.cliente;
            // El cliente existe en otro Cyber: se cargan sus datos y al guardar
            // se vincula también a este Cyber (no se duplica).
            const r = await Swal.fire({
                icon: 'info',
                title: 'Persona encontrada',
                html: '<b>' + c.nombre_completo + '</b><br><small>Ya está en el sistema. Se cargarán sus datos para que los revises; al guardar quedará también en su lista.</small>',
                confirmButtonText: 'Continuar',
                confirmButtonColor: '#0B3C74',
                showCancelButton: true,
                cancelButtonText: 'Cancelar',
            });
            if (!r.isConfirmed) return;
            // Rellenar campos con los datos existentes
            const campos = {
                'fPN': c.primer_nombre, 'fSN': c.segundo_nombre,
                'fPA': c.primer_apellido, 'fSA': c.segundo_apellido,
                'correo': c.correo, 'telefono': c.telefono, 'direccion': c.direccion,
            };
            Object.entries(campos).forEach(([id, val]) => {
                const el = document.getElementById(id) || document.querySelector('#formRegistro [name="' + ({fPN:'primer_nombre',fSN:'segundo_nombre',fPA:'primer_apellido',fSA:'segundo_apellido'}[id] || id) + '"]');
                if (el && val) { el.value = val; el.dispatchEvent(new Event('input', {bubbles:true})); }
            });
        }
        document.getElementById('formRegistro').style.display = 'block';
        document.getElementById('fTipo').value = tipo;
        const paisSel = document.getElementById('paisDoc').value;
        document.getElementById('fPais').value = paisSel;
        if (window.jzAplicarPais) jzAplicarPais(paisSel, true);
        document.getElementById('fNumero').value = data.numero_limpio || '';
        const nombresTipo = @json(\App\Support\Pais::todosLosDocumentos());
        if (tipo === 'cedula') {
            document.getElementById('docResumen').textContent = 'Cédula: ' + (data.numero_ui || '');
            document.getElementById('camposExtra').classList.add('hidden');
            document.getElementById('datosCedulaAuto').style.display = 'block';
            document.getElementById('autoInfo').textContent =
                (data.cedula.municipio||'')+' / '+(data.cedula.departamento||'')+' · Nac: '+(data.cedula.fecha_nacimiento||'')+' · Edad: '+(data.cedula.edad??'—');
        } else {
            document.getElementById('docResumen').textContent = (nombresTipo[tipo] || tipo) + ': ' + (data.numero_limpio||'');
            document.getElementById('camposExtra').classList.remove('hidden');
            document.getElementById('datosCedulaAuto').style.display = 'none';
            if (data.cr && data.cr.provincia) {
                const dp = document.getElementById('fDepto');
                if (dp && !dp.value) { dp.value = data.cr.provincia; dp.dispatchEvent(new Event('input', {bubbles: true})); }
            }
        }
        document.getElementById('formRegistro').scrollIntoView({behavior:'smooth'});
        lucide.createIcons();
    }
    function confirmarGuardar(e) {
        e.preventDefault();
        const tipo = document.getElementById('fTipo').value;
        if (tipo !== 'cedula') {
            if (!document.getElementById('fFN').value || !document.getElementById('fMuni').value || !document.getElementById('fDepto').value) {
                jzAviso({icon: 'error', title:'Faltan datos', text:'Fecha, municipio y departamento son obligatorios.'});
                return false;
            }
        }
        Swal.fire({title:'¿Guardar los datos?', icon:'question', showCancelButton:true, confirmButtonColor:'#0B3C74', confirmButtonText:'Sí'})
            .then(r => { if (r.isConfirmed) e.target.submit(); });
        return false;
    }
    </script>
    @endpush
</x-layout>
