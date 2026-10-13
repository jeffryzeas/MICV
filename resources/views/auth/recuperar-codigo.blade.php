<x-publico titulo="Nueva contraseña">
    <h2 class="font-bold text-lg text-[#061a33] mb-1">Cree su nueva contraseña</h2>
    <p class="text-sm text-gray-500 mb-5">Escriba el código que le llegó al correo y su nueva contraseña.</p>
    @if(session('codigo_debug'))
        <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">Modo de prueba (APP_DEBUG): el código es <b class="font-mono">{{ session('codigo_debug') }}</b></div>
    @endif
    <form method="POST" action="{{ route('recuperar.restablecer') }}" class="space-y-4">
        @csrf
        <div><label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Correo</label>
            <input type="email" name="email" value="{{ old('email', $email) }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
        <div><label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Código de 6 dígitos</label>
            <input type="text" name="codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required placeholder="000000" class="w-full text-center tracking-[0.6em] font-mono text-2xl border-2 border-gray-300 rounded-xl px-3 py-3"></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Nueva contraseña</label>
                <input type="password" name="password" required autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
            <div><label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Repetirla</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
        </div>
        <button type="submit" class="w-full text-white font-semibold uppercase text-sm tracking-wide py-3 rounded-lg" style="background-color:#0B3C74;">Cambiar contraseña</button>
    </form>
    <p class="text-center text-sm text-gray-500 mt-5"><a href="{{ route('recuperar.mostrar') }}" class="underline">Pedir otro código</a></p>
</x-publico>
