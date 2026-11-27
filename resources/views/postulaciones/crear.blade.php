<x-postulaciones-shell titulo="Enviar CV a una empresa" tab="nuevo">
    <header class="page-heading"><div><h2 class="page-title flex items-center gap-2"><x-icono name="send" class="size-6"/>Enviar CV a una empresa</h2><p class="mt-1 text-sm text-gray-500">Elija el destinatario, prepare su CV y revise el mensaje antes de enviarlo.</p></div></header>
    @if($cuentas->isEmpty())<p class="gestion-notice gestion-notice-warning">Configure y compruebe una cuenta de Gmail para continuar. <a class="gestion-link" href="{{ route('postulaciones.gmail') }}">Configurar Gmail</a></p>@endif
    @if($clientes->isEmpty())<p class="gestion-notice">Necesita una ficha de CV activa. @if(auth()->user()->esCliente())<a class="gestion-link" href="{{ route('mi-cv.editar') }}">Ir a mis datos</a>@else<a class="gestion-link" href="{{ route('clientes.create') }}">Registrar cliente</a>@endif</p>@endif
    @if($empresas->isEmpty())<p class="gestion-notice">Registre primero una empresa destinataria. <a class="gestion-link" href="{{ route('postulaciones.empresas.crear') }}">Registrar empresa</a></p>@endif
    <form method="POST" action="{{ route('postulaciones.preparar') }}" class="post-registration-form bg-white rounded-xl shadow p-4 sm:p-6" id="post-compose" data-pasos="no" data-contar-selecciones="1" data-jz-confirmar="¿Preparar este CV para revisión?" data-jz-detalle="Se preparará un borrador con el mensaje y un PDF temporal. Todavía no se enviará ningún correo." data-jz-boton="Sí, preparar borrador" data-cargo-anterior="{{ session()->hasOldInput('cargo') ? '1' : '0' }}" data-redactar="{{ route('postulaciones.redactar') }}">@csrf
        <nav data-indice aria-label="Secciones de la postulación"></nav>
        <section class="jz-seccion post-registration-section" data-titulo="Destinatario">
            <h3 class="post-section-title"><x-icono name="building-2"/>Datos del envío</h3>
            <p class="post-help mb-4">Seleccione quién envía el correo y la empresa que recibirá el currículum.</p>
            <div class="form-grid">
                <x-ui.field label="Enviar desde" for="post-cuenta"><select class="campo" id="post-cuenta" name="gmail_cuenta_id" required>@forelse($cuentas as $cuenta)<option value="{{ $cuenta->id }}" @selected((string)old('gmail_cuenta_id')===(string)$cuenta->id)>{{ $cuenta->correo }}@if(auth()->user()->esAdmin()) · {{ $cuenta->user->name }}@endif</option>@empty<option value="">Configure Gmail</option>@endforelse</select></x-ui.field>
                <x-ui.field label="CV de" for="post-cliente"><select class="campo" id="post-cliente" name="cliente_id" required><option value="">Seleccione una persona</option>@foreach($clientes as $cliente)<option value="{{ $cliente->id }}" data-vista="{{ route('cv.vista',$cliente) }}" @selected((string)old('cliente_id',request('cliente_id',$clientes->count()===1?$cliente->id:''))===(string)$cliente->id)>{{ $cliente->nombreCompleto() }} · {{ $cliente->documentoFormateado() }}</option>@endforeach</select></x-ui.field>
                <x-ui.field label="Empresa destinataria" for="post-empresa"><select class="campo" id="post-empresa" name="empresa_id" required><option value="">Seleccione una empresa</option>@foreach($empresas as $empresa)<option value="{{ $empresa->id }}" data-correo="{{ $empresa->correo }}" data-cargo="{{ $empresa->cargo }}" @selected((string)old('empresa_id',request('empresa_id'))===(string)$empresa->id)>{{ $empresa->nombre }}</option>@endforeach</select><p class="mt-1 text-xs text-gray-500" id="post-para"></p></x-ui.field>
                <x-ui.field label="Cargo solicitado" for="post-cargo" hint="Opcional. En blanco se consideran las oportunidades disponibles."><input class="campo" id="post-cargo" name="cargo" maxlength="150" value="{{ old('cargo') }}" placeholder="Al puesto que se ajuste a mi perfil"></x-ui.field>
            </div>
        </section>
        <section class="jz-seccion post-registration-section" data-titulo="Diseño del CV">
            <h3 class="post-section-title"><x-icono name="layout-template"/>Elegir el currículum</h3>
            <div class="post-template-grid">
                <div class="space-y-5">
                    <div>@include('partials.selector-diseno-cv',['disenoSeleccionado'=>old('diseno',\App\Models\Configuracion::actual()->cv()['diseno_defecto'])])</div>
                    <fieldset id="post-paletas"><legend class="label-campo mb-2">Colores del currículum</legend><div class="post-palettes">
                        @foreach(\App\Models\Configuracion::PALETAS_PRO as $key=>$p)<label class="post-palette" title="{{ $p['nombre'] }}"><input type="radio" name="paleta" value="{{ $key }}" @checked(old('paleta','cyber')===$key)><span class="post-swatch" style="background:linear-gradient(135deg,{{ $p['lateral'] }} 55%,{{ $p['acento'] }} 55%)"></span><span>{{ $p['nombre'] }}</span></label>@endforeach
                    </div><p class="post-help mt-2" id="post-paleta-ayuda">Seleccione una combinación de colores.</p></fieldset>
                    <fieldset><legend class="label-campo mb-2">Idioma del CV</legend><div class="post-choice-row">@foreach(['es'=>'Español','en'=>'Inglés'] as $key=>$name)<label><input type="radio" name="idioma" value="{{ $key }}" @checked(old('idioma','es')===$key)><span><x-icono name="languages" class="size-4"/>{{ $name }}</span></label>@endforeach</div></fieldset>
                    <input type="hidden" name="foto" value="0"><label class="gestion-checkbox"><input type="checkbox" name="foto" id="post-foto" value="1" class="casilla" @checked(old('foto','1')==='1')><span>Incluir fotografía, si el diseño la admite.</span></label>
                    <p class="post-help">El PDF preparado es temporal. Se retira al terminar el envío; el registro conserva el diseño para regenerarlo con los datos actuales.</p>
                </div>
                <div class="post-preview-panel"><p class="label-campo flex items-center gap-2"><x-icono name="eye" class="size-4"/>Vista previa de su CV</p><p id="post-vista-estado" class="post-help" role="status">Seleccione una persona para ver su currículum.</p><div class="post-preview-empty" id="post-vista-vacia"><x-icono name="file-user" class="size-10"/><p>Aquí verá su CV con el diseño elegido.</p></div><iframe id="post-cv-preview" title="Vista previa del currículum con el diseño seleccionado" hidden></iframe></div>
            </div>
        </section>
        <section class="jz-seccion post-registration-section" data-titulo="Mensaje">
            <div class="post-message-heading"><h3 class="post-section-title"><x-icono name="mail-pen"/>Mensaje para la empresa</h3><x-ui.button id="post-redactar" variant="secondary" icon="text-cursor-input">Generar redacción</x-ui.button></div><p class="post-help mb-4">La propuesta usa sus datos del CV. Puede editarla antes de continuar.</p>
            <x-ui.field label="Asunto del correo" for="post-asunto"><input class="campo" id="post-asunto" name="asunto" value="{{ old('asunto') }}" required maxlength="200" placeholder="Ejemplo: Postulación a Asistente administrativo — Mi nombre"></x-ui.field>
            <div class="mt-4"><x-ui.field label="Mensaje de presentación" for="post-mensaje"><textarea class="campo" id="post-mensaje" name="mensaje" rows="11" required maxlength="10000" placeholder="Escriba su presentación o pulse Generar redacción.">{{ old('mensaje') }}</textarea></x-ui.field></div><p id="post-redaccion-estado" class="post-help" role="status" aria-live="polite"></p>
        </section>
        @include('partials.barra-guardar',['texto'=>'Preparar y revisar envío','textoMovil'=>'Revisar','iconoGuardar'=>'file-search','deshabilitado'=>$cuentas->isEmpty() || $clientes->isEmpty() || $empresas->isEmpty()])
    </form>
    @push('scripts')<script src="{{ asset('js/cv-gallery.js') }}?v={{ filemtime(public_path('js/cv-gallery.js')) }}" defer></script><script src="{{ asset('js/postulaciones.js') }}?v={{ filemtime(public_path('js/postulaciones.js')) }}" defer></script>@endpush
</x-postulaciones-shell>
