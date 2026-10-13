<x-publico titulo="Eliminar mi cuenta" ancho="max-w-lg">
    <div class="text-center mb-4">
        <div class="size-14 rounded-full bg-red-50 text-red-600 flex items-center justify-center mx-auto mb-3"><x-icono name="user-x" class="size-7" /></div>
        <h2 class="font-bold text-lg text-[#061a33]">Cómo eliminar su cuenta y sus datos</h2>
    </div>
    <p class="text-sm text-gray-600 mb-4">Por su seguridad, la eliminación se pide <b>desde dentro de la app</b>, con su contraseña. Así nadie más puede pedir que borren su cuenta.</p>
    <ol class="space-y-3 text-sm text-gray-700 mb-5">
        <li class="flex gap-3"><span class="size-6 shrink-0 rounded-full bg-[#0B3C74] text-white text-xs font-bold flex items-center justify-center">1</span><span>Inicie sesión con su correo y su contraseña. Si la olvidó, use <a href="{{ route('recuperar.mostrar') }}" class="underline font-semibold text-[#0B3C74]">¿Olvidó su contraseña?</a></span></li>
        <li class="flex gap-3"><span class="size-6 shrink-0 rounded-full bg-[#0B3C74] text-white text-xs font-bold flex items-center justify-center">2</span><span>Abra <b>Privacidad y cuenta</b> en el menú.</span></li>
        <li class="flex gap-3"><span class="size-6 shrink-0 rounded-full bg-[#0B3C74] text-white text-xs font-bold flex items-center justify-center">3</span><span>Toque <b>Solicitar eliminación</b> y confirme con su contraseña. Antes, si quiere, descargue una copia de sus datos.</span></li>
        <li class="flex gap-3"><span class="size-6 shrink-0 rounded-full bg-[#0B3C74] text-white text-xs font-bold flex items-center justify-center">4</span><span>Un administrador la revisa y le avisamos por correo cuando su cuenta, su CV, su foto y su tarjeta estén borrados.</span></li>
    </ol>
    <a href="{{ auth()->check() ? route('mi-cuenta.mostrar') : route('login') }}" class="block w-full text-center text-white font-semibold uppercase text-sm tracking-wide py-3 rounded-lg" style="background-color:#0B3C74;">{{ auth()->check() ? 'Ir a Privacidad y cuenta' : 'Iniciar sesión' }}</a>
    <p class="text-xs text-gray-400 mt-4">Solo se conserva un registro mínimo de la solicitud, sin sus datos personales, para demostrar que cumplimos la ley. Si ya no tiene acceso a su correo, escríbanos a {{ \App\Models\Configuracion::actualSegura()?->marca_correo ?: 'nuestro correo de soporte' }}.</p>
</x-publico>
