@php $in = 'w-full border border-gray-300 rounded-lg px-3 py-2.5'; $lb = 'block text-xs font-semibold uppercase text-azul-800 mb-1'; @endphp
<x-layout titulo="Google reCAPTCHA v3" activo="recaptcha">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-azul-950 mb-1 flex items-center gap-2"><x-icono name="shield-check" class="size-[18px] text-oro-500" /> Protección contra robots</h2>
            <p class="text-sm text-gray-500 mb-5">
                Estado:
                @if($cfg->recaptchaListo())<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">Activo en login y registro</span>
                @else<span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Apagado</span>@endif
            </p>
            <form method="POST" action="{{ route('admin.recaptcha.update') }}" class="space-y-4">
                @csrf @method('PUT')
                <div><label class="{{ $lb }}">Llave del sitio (site key)</label><input type="text" name="recaptcha_site_key" value="{{ old('recaptcha_site_key', $cfg->recaptcha_site_key) }}" class="{{ $in }} font-mono text-sm"></div>
                <div><label class="{{ $lb }}">Llave secreta (secret key)</label><input type="password" name="recaptcha_secret_key" autocomplete="new-password" placeholder="{{ $cfg->secretoRecaptcha() ? '•••• guardada — escriba otra solo si quiere cambiarla' : '' }}" class="{{ $in }} font-mono text-sm"></div>
                <div class="max-w-xs"><label class="{{ $lb }}">Umbral (0.1 a 0.9)</label><input type="number" step="0.05" min="0.1" max="0.9" name="recaptcha_umbral" value="{{ old('recaptcha_umbral', $cfg->recaptcha_umbral ?: 0.5) }}" class="{{ $in }}">
                    <p class="text-[11px] text-gray-400 mt-1">0.5 es lo normal. Más alto = más estricto.</p></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="recaptcha_activo" value="1" @checked(old('recaptcha_activo', $cfg->recaptcha_activo))> Activar reCAPTCHA v3</label>
                <button class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg"><x-icono name="check" class="size-4" /> Guardar</button>
            </form>
        </div>
        <div class="bg-white rounded-xl shadow p-6 text-sm text-gray-700">
            <h3 class="font-semibold text-azul-950 mb-3">Cómo obtener las llaves</h3>
            <ol class="list-decimal pl-5 space-y-2">
                <li>Entre a <b>google.com/recaptcha/admin/create</b>.</li>
                <li>Tipo: <b>reCAPTCHA v3</b> (basado en puntuación).</li>
                <li>Dominios: su dominio (ej. <i>micv.com</i>) y, para probar, <i>localhost</i>.</li>
                <li>Copie la <b>clave del sitio</b> y la <b>clave secreta</b> aquí.</li>
            </ol>
            <p class="mt-4 text-xs text-gray-500">No le pide nada a la persona: se ve la insignia de Google en la esquina del login y del registro. Si Google no responde, el sistema deja pasar y lo anota en "Errores del sistema".</p>
        </div>
    </div>
</x-layout>
