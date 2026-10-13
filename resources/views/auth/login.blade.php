<x-publico titulo="Iniciar sesión">
    <form method="POST" action="{{ route('login.iniciar') }}" class="space-y-4">
        @csrf
        <div class="relative">
            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Usuario o correo</label>
            <x-icono name="user" class="absolute left-3 top-[30px] size-4 text-gray-400" />
            <input type="text" name="usuario" value="{{ old('usuario') }}" required autofocus autocomplete="username"
                   class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5 focus:outline-none focus:border-[#0B3C74] focus:ring-1 focus:ring-[#0B3C74]">
        </div>
        <div class="relative">
            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Contraseña</label>
            <x-icono name="lock" class="absolute left-3 top-[30px] size-4 text-gray-400" />
            <input type="password" name="password" required autocomplete="current-password"
                   class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5 focus:outline-none focus:border-[#0B3C74] focus:ring-1 focus:ring-[#0B3C74]">
        </div>
        <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2 text-sm text-gray-500"><input type="checkbox" name="recordar" value="1" class="rounded"> Recordarme</label>
            <a href="{{ route('recuperar.mostrar') }}" class="text-sm font-semibold text-[#0B3C74] hover:underline">¿Olvidó su contraseña?</a>
        </div>
        <button type="submit" data-cargando="Ingresando…"
                class="w-full text-white font-semibold uppercase text-sm tracking-wide py-3 rounded-lg transition-colors"
                style="background-color:#0B3C74;"
                onmouseover="this.style.backgroundColor='#C9971F'" onmouseout="this.style.backgroundColor='#0B3C74'">
            Ingresar
        </button>
        @include('partials.recaptcha', ['accion' => 'login'])
    </form>
    @if(\App\Models\Configuracion::actualSegura()?->registro_abierto)
        <div class="mt-6 pt-5 border-t border-gray-100 text-center">
            <p class="text-sm text-gray-500">¿Quiere hacer su propio currículum?</p>
            <a href="{{ route('registro.mostrar') }}" class="mt-2 inline-flex items-center justify-center gap-2 w-full border-2 border-[#C9971F] text-[#0B3C74] hover:bg-[#C9971F] hover:text-white font-semibold text-sm py-2.5 rounded-lg transition-colors">
                <x-icono name="user-plus" class="size-4" /> Crear mi cuenta gratis
            </a>
        </div>
    @endif
</x-publico>
