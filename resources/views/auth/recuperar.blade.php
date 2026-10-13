<x-publico titulo="Recuperar contraseña">
    <h2 class="font-bold text-lg text-[#061a33] mb-1">¿Olvidó su contraseña?</h2>
    <p class="text-sm text-gray-500 mb-5">Escriba el correo de su cuenta y le enviaremos un código de 6 dígitos para crear una nueva.</p>
    <form method="POST" action="{{ route('recuperar.enviar') }}" class="space-y-4">
        @csrf
        <div class="relative">
            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Correo</label>
            <i data-lucide="mail" class="absolute left-3 top-[30px] size-4 text-gray-400"></i>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5">
        </div>
        <button type="submit" class="w-full text-white font-semibold uppercase text-sm tracking-wide py-3 rounded-lg" style="background-color:#0B3C74;">Enviar código</button>
        @include('partials.recaptcha', ['accion' => 'recuperar'])
    </form>
    <p class="text-center text-sm text-gray-500 mt-5"><a href="{{ route('login') }}" class="underline">Volver a iniciar sesión</a></p>
</x-publico>
