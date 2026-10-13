@php
    $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5';
    $lb = 'block text-xs font-semibold uppercase text-azul-800 mb-1';
    $tieneLogo = \App\Support\Archivos::existe($cfg->logo);
@endphp
<x-layout titulo="Marca del sistema" activo="marca-sistema">
    <p class="text-gray-500 mb-5 text-sm">Así se presenta su plataforma: en el menú, en el inicio de sesión, en los correos, en los textos legales y en la app instalada.</p>

    <form method="POST" action="{{ route('admin.marca.update') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow p-5 sm:p-6">
            <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><i data-lucide="badge-check" class="size-[18px] text-oro-500"></i> Identidad</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><label class="{{ $lb }}">Nombre del sistema *</label>
                    <input type="text" name="nombre_negocio" value="{{ old('nombre_negocio', $cfg->nombre_negocio) }}" required maxlength="120" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">Eslogan</label>
                    <input type="text" name="eslogan" value="{{ old('eslogan', $cfg->eslogan) }}" maxlength="160" placeholder="Su CV profesional en minutos" class="{{ $in }}"></div>
                <div class="sm:col-span-2">
                    <label class="{{ $lb }}">Logo</label>
                    <div class="flex flex-wrap items-center gap-4">
                        @if($tieneLogo)<img src="{{ route('marca.logo') }}?v={{ $cfg->updated_at?->timestamp }}" class="h-14 object-contain bg-gray-50 border rounded p-1" alt="Logo">@endif
                        <input type="file" name="logo" accept="image/*" class="text-sm max-w-full">
                        @if($tieneLogo)<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="quitar_logo" value="1"> Quitar logo</label>@endif
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">PNG con fondo transparente, cuadrado o horizontal. Máx. 4 MB.</p>
                </div>
                <div><label class="{{ $lb }}">Color primario</label>
                    <input type="color" name="color_primario" value="{{ old('color_primario', $cfg->color_primario ?: '#1e3a8a') }}" class="w-full h-11 border border-gray-300 rounded-lg"></div>
                <div><label class="{{ $lb }}">Color secundario</label>
                    <input type="color" name="color_secundario" value="{{ old('color_secundario', $cfg->color_secundario ?: '#d4a017') }}" class="w-full h-11 border border-gray-300 rounded-lg"></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow p-5 sm:p-6">
            <h2 class="font-semibold text-azul-950 mb-1 flex items-center gap-2"><i data-lucide="phone" class="size-[18px] text-oro-500"></i> Contacto y redes</h2>
            <p class="text-xs text-gray-500 mb-4">Se muestra en el pie de página, en los correos y en las políticas (la ley pide que la persona sepa cómo contactarle).</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><label class="{{ $lb }}">Teléfono</label><input type="text" name="marca_telefono" value="{{ old('marca_telefono', $cfg->marca_telefono) }}" placeholder="+506 8888 8888" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">WhatsApp</label><input type="text" name="marca_whatsapp" value="{{ old('marca_whatsapp', $cfg->marca_whatsapp) }}" placeholder="+505 8888 8888" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">Correo de soporte</label><input type="email" name="marca_correo" value="{{ old('marca_correo', $cfg->marca_correo) }}" placeholder="soporte@midominio.com" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">Sitio web</label><input type="url" name="marca_sitio_web" value="{{ old('marca_sitio_web', $cfg->marca_sitio_web) }}" placeholder="https://" class="{{ $in }}"></div>
                <div class="sm:col-span-2"><label class="{{ $lb }}">Dirección</label><input type="text" name="marca_direccion" value="{{ old('marca_direccion', $cfg->marca_direccion) }}" class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">Facebook</label><input type="url" name="marca_facebook" value="{{ old('marca_facebook', $cfg->marca_facebook) }}" placeholder="https://facebook.com/..." class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">Instagram</label><input type="url" name="marca_instagram" value="{{ old('marca_instagram', $cfg->marca_instagram) }}" placeholder="https://instagram.com/..." class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">TikTok</label><input type="url" name="marca_tiktok" value="{{ old('marca_tiktok', $cfg->marca_tiktok) }}" placeholder="https://tiktok.com/@..." class="{{ $in }}"></div>
                <div><label class="{{ $lb }}">Texto del pie de página</label><input type="text" name="pie_pagina" value="{{ old('pie_pagina', $cfg->pie_pagina) }}" maxlength="255" placeholder="© {{ date('Y') }} {{ $cfg->nombre_negocio }}" class="{{ $in }}"></div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow p-5 sm:p-6">
            <h2 class="font-semibold text-azul-950 mb-1 flex items-center gap-2"><i data-lucide="smartphone" class="size-[18px] text-oro-500"></i> App Android (Google Play)</h2>
            <p class="text-xs text-gray-500 mb-4">Solo si publica la app como TWA (Bubblewrap / PWABuilder). Con esto Android confía en su dominio y la app abre sin barra del navegador.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><label class="{{ $lb }}">Nombre del paquete</label>
                    <input type="text" name="android_paquete" value="{{ old('android_paquete', $cfg->android_paquete) }}" placeholder="com.micv.app" class="{{ $in }} font-mono"></div>
                <div><label class="{{ $lb }}">Huella SHA-256 del certificado</label>
                    <textarea name="android_sha256" rows="2" placeholder="AB:CD:EF:... (puede poner varias, una por línea)" class="{{ $in }} font-mono text-xs">{{ old('android_sha256', $cfg->android_sha256) }}</textarea></div>
            </div>
            <div class="mt-4 text-xs text-gray-600 bg-gray-50 border rounded-lg p-3 space-y-1 break-all">
                <div><b>Verificación de dominio:</b> <a href="{{ url('.well-known/assetlinks.json') }}" target="_blank" class="text-azul-800 underline">{{ url('.well-known/assetlinks.json') }}</a></div>
                <div><b>Manifiesto de la app:</b> <a href="{{ url('manifest.webmanifest') }}" target="_blank" class="text-azul-800 underline">{{ url('manifest.webmanifest') }}</a></div>
                <div><b>URL de eliminación de cuenta (la pide Google Play):</b> <a href="{{ route('eliminar.mostrar') }}" target="_blank" class="text-azul-800 underline">{{ route('eliminar.mostrar') }}</a></div>
                <div><b>Política de privacidad:</b> <a href="{{ route('legal.ver', 'privacidad') }}" target="_blank" class="text-azul-800 underline">{{ route('legal.ver', 'privacidad') }}</a></div>
            </div>
        </div>

        <button type="submit" class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-6 py-3 rounded-lg transition-colors w-full sm:w-auto justify-center">
            <i data-lucide="check" class="size-4"></i> Guardar marca
        </button>
    </form>
</x-layout>
