@php $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5'; $lb = 'block text-xs font-semibold uppercase text-azul-800 mb-1'; @endphp
<x-layout titulo="Correo Gmail" activo="correo">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-azul-950 mb-1 flex items-center gap-2"><i data-lucide="mail-check" class="size-[18px] text-oro-500"></i> Cuenta que envía los códigos</h2>
            <p class="text-sm text-gray-500 mb-5">
                Estado:
                @if($cfg->correoListo())<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">Activo · {{ $cfg->smtp_desde_correo }}</span>
                @else<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700">Sin configurar o apagado</span>@endif
            </p>
            <form method="POST" action="{{ route('admin.correo.update') }}" class="space-y-4">
                @csrf @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><label class="{{ $lb }}">Correo Gmail</label><input type="email" name="smtp_desde_correo" value="{{ old('smtp_desde_correo', $cfg->smtp_desde_correo) }}" required placeholder="micv.notificaciones@gmail.com" class="{{ $in }}"></div>
                    <div><label class="{{ $lb }}">Nombre que verá la persona</label><input type="text" name="smtp_desde_nombre" value="{{ old('smtp_desde_nombre', $cfg->smtp_desde_nombre ?: $cfg->nombre_negocio) }}" class="{{ $in }}"></div>
                    <div class="sm:col-span-2">
                        <label class="{{ $lb }}">Contraseña de aplicación (16 letras)</label>
                        <input type="password" name="smtp_password" autocomplete="new-password" placeholder="{{ $cfg->passwordSmtp() ? '•••• guardada — escriba otra solo si quiere cambiarla' : 'abcd efgh ijkl mnop' }}" class="{{ $in }} font-mono">
                        <p class="text-[11px] text-gray-400 mt-1">No es su contraseña normal de Gmail. Se guarda cifrada.</p>
                    </div>
                    <div><label class="{{ $lb }}">Servidor</label><input type="text" name="smtp_host" value="{{ old('smtp_host', $cfg->smtp_host ?: 'smtp.gmail.com') }}" class="{{ $in }}"></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="{{ $lb }}">Puerto</label><input type="number" name="smtp_puerto" value="{{ old('smtp_puerto', $cfg->smtp_puerto ?: 587) }}" class="{{ $in }}"></div>
                        <div><label class="{{ $lb }}">Seguridad</label>
                            <select name="smtp_encriptacion" class="{{ $in }}">
                                <option value="tls" @selected(old('smtp_encriptacion', $cfg->smtp_encriptacion ?: 'tls') === 'tls')>TLS (587)</option>
                                <option value="ssl" @selected(old('smtp_encriptacion', $cfg->smtp_encriptacion) === 'ssl')>SSL (465)</option>
                            </select></div>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="smtp_activo" value="1" @checked(old('smtp_activo', $cfg->smtp_activo))> Usar este correo para enviar los códigos de verificación</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="registro_abierto" value="1" @checked(old('registro_abierto', $cfg->registro_abierto))> Permitir que cualquier persona se registre en <code class="bg-gray-100 px-1 rounded">/registro</code> para crear su CV</label>
                <button class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg"><i data-lucide="check" class="size-4"></i> Guardar</button>
            </form>

            <hr class="my-6">
            <form method="POST" action="{{ route('admin.correo.probar') }}" class="flex flex-wrap gap-3 items-end">
                @csrf
                <div class="flex-1 min-w-[220px]"><label class="{{ $lb }}">Enviar correo de prueba a</label><input type="email" name="para" value="{{ auth()->user()->email }}" required class="{{ $in }}"></div>
                <button class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:border-azul-800 text-azul-800 text-sm font-semibold px-4 py-2.5 rounded-lg"><i data-lucide="send" class="size-4"></i> Enviar prueba</button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow p-6 text-sm text-gray-700">
            <h3 class="font-semibold text-azul-950 mb-3 flex items-center gap-2"><i data-lucide="key-round" class="size-4 text-oro-500"></i> Cómo sacar la contraseña de aplicación</h3>
            <ol class="list-decimal pl-5 space-y-2">
                <li>Entre a <b>myaccount.google.com</b> con el Gmail que va a enviar.</li>
                <li>Vaya a <b>Seguridad</b> y active la <b>Verificación en 2 pasos</b> (es obligatoria).</li>
                <li>Busque <b>"Contraseñas de aplicaciones"</b> (o abra myaccount.google.com/apppasswords).</li>
                <li>Escriba un nombre, por ejemplo <i>Mi CV</i>, y toque <b>Crear</b>.</li>
                <li>Copie las <b>16 letras</b> que salen y péguelas aquí. Los espacios no importan.</li>
            </ol>
            <p class="mt-4 text-xs text-gray-500">Datos de Gmail: servidor <b>smtp.gmail.com</b>, puerto <b>587</b>, seguridad <b>TLS</b>. Algunos VPS bloquean el puerto 587: si la prueba falla con "connection", pruebe 465/SSL o pida al proveedor que lo abra.</p>
        </div>
    </div>
</x-layout>
