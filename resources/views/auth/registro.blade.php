<x-publico titulo="Crear mi cuenta" ancho="max-w-lg">
    <h2 class="font-bold text-lg text-[#061a33] mb-1">Cree su cuenta y haga su CV</h2>
    <p class="text-sm text-gray-500 mb-5">Es gratis. Le enviaremos un <b>código de 6 dígitos</b> a su correo para verificar la cuenta.</p>
    <form method="POST" action="{{ route('registro.guardar') }}" class="space-y-4">
        @csrf @include('partials.antibot')
        <div>
            <label for="regPais" class="block text-xs font-semibold uppercase text-gray-500 mb-1">¿De qué país es su documento?</label>
            <div class="relative">
                <span id="regBandera" class="absolute left-3 top-1/2 -translate-y-1/2 text-xl leading-none pointer-events-none">{{ \App\Support\Pais::de(old('pais', 'NI'))['bandera'] }}</span>
                <select name="pais" id="regPais" required class="w-full appearance-none border border-gray-300 rounded-lg pl-11 pr-9 py-2.5 bg-white font-semibold text-gray-800 focus:outline-none focus:border-[#0B3C74]">
                    @foreach(\App\Support\Pais::porRegion() as $region => $lista)
                        <optgroup label="{{ $region }}">
                            @foreach($lista as $cod => $p)<option value="{{ $cod }}" data-bandera="{{ $p['bandera'] }}" @selected(old('pais', 'NI') === $cod)>{{ $p['nombre'] }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
                <i data-lucide="chevron-down" class="absolute right-3 top-1/2 -translate-y-1/2 size-4 text-gray-400 pointer-events-none"></i>
            </div>
            <script>document.getElementById('regPais').addEventListener('change', function () { document.getElementById('regBandera').textContent = this.selectedOptions[0].dataset.bandera; });</script>
            <p class="text-[11px] text-gray-400 mt-1">Con esto adaptamos sus documentos, el seguro social, el código de teléfono y el CV. Si su país no está, elija "Otro país".</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Primer nombre</label>
                <input type="text" name="primer_nombre" value="{{ old('primer_nombre') }}" required autocomplete="given-name" maxlength="60" placeholder="Ej: María" class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
            <div><label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Primer apellido</label>
                <input type="text" name="primer_apellido" value="{{ old('primer_apellido') }}" required autocomplete="family-name" maxlength="60" placeholder="Ej: López" class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
        </div>
        <div class="relative">
            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Correo electrónico</label>
            <i data-lucide="mail" class="absolute left-3 top-[30px] size-4 text-gray-400"></i>
            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" inputmode="email" placeholder="Ej: maria.lopez@gmail.com" class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Contraseña (mín. 8)</label>
                <input type="password" name="password" id="regPass" required minlength="8" autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                <div class="h-1 rounded-full bg-gray-100 mt-1.5 overflow-hidden"><div id="regFuerza" class="h-full w-0 transition-all"></div></div>
                <p id="regFuerzaTxt" class="text-[11px] text-gray-400 mt-0.5">Use letras y números.</p></div>
            <div><label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Repetir contraseña</label>
                <input type="password" name="password_confirmation" id="regPass2" required minlength="8" autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                <p id="regIgual" class="text-[11px] mt-0.5 h-4"></p></div>
        </div>
        <label class="flex items-start gap-2 text-sm text-gray-600">
            <input type="checkbox" name="acepto" value="1" class="mt-1" @checked(old('acepto'))>
            <span>Leí y acepto los <a href="{{ route('legal.ver', 'terminos') }}" target="_blank" class="underline font-semibold text-[#0B3C74]">Términos y condiciones</a> y la <a href="{{ route('legal.ver', 'privacidad') }}" target="_blank" class="underline font-semibold text-[#0B3C74]">Política de privacidad</a>. Doy mi consentimiento para que mis datos se usen para crear mi CV y mis cartas. Tengo 18 años o más (o tengo entre 15 y 17 años y el permiso de mi madre, padre o representante legal).</span>
        </label>
        <button type="submit" class="w-full text-white font-semibold uppercase text-sm tracking-wide py-3 rounded-lg" style="background-color:#0B3C74;"
                onmouseover="this.style.backgroundColor='#C9971F'" onmouseout="this.style.backgroundColor='#0B3C74'" data-cargando="Creando su cuenta…">Crear cuenta</button>
        @include('partials.recaptcha', ['accion' => 'registro'])
    </form>
    <p class="text-center text-sm text-gray-500 mt-5">¿Ya tiene cuenta? <a href="{{ route('login') }}" class="font-semibold text-[#0B3C74] underline">Inicie sesión</a></p>
<script>
(function () {
    const p1 = document.getElementById('regPass'), p2 = document.getElementById('regPass2');
    const barra = document.getElementById('regFuerza'), txt = document.getElementById('regFuerzaTxt'), igual = document.getElementById('regIgual');
    function fuerza() {
        const v = p1.value; let n = 0;
        if (v.length >= 8) n++; if (v.length >= 12) n++; if (/[a-z]/.test(v) && /[A-Z]/.test(v)) n++; if (/\d/.test(v)) n++; if (/[^A-Za-z0-9]/.test(v)) n++;
        const niveles = [['0%', '#e5e7eb', 'Use letras y números.'], ['25%', '#ef4444', 'Muy débil'], ['45%', '#f59e0b', 'Débil'], ['65%', '#eab308', 'Aceptable'], ['85%', '#22c55e', 'Buena'], ['100%', '#16a34a', 'Muy buena']];
        const x = v ? niveles[Math.max(1, n)] : niveles[0];
        barra.style.width = x[0]; barra.style.background = x[1]; txt.textContent = v.length && v.length < 8 ? 'Mínimo 8 caracteres' : x[2];
        comparar();
    }
    function comparar() {
        if (!p2.value) { igual.textContent = ''; p2.setCustomValidity(''); return; }
        const ok = p1.value === p2.value;
        igual.textContent = ok ? '✓ Coinciden' : 'No coinciden'; igual.style.color = ok ? '#16a34a' : '#dc2626';
        p2.setCustomValidity(ok ? '' : 'Las contraseñas no coinciden.');
    }
    p1.addEventListener('input', fuerza); p2.addEventListener('input', comparar);
})();
</script>
</x-publico>
