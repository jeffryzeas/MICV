<x-publico titulo="Eliminar mi cuenta" ancho="max-w-lg">
    <h2 class="font-bold text-lg text-[#061a33] mb-1">Solicitar la eliminación de su cuenta</h2>
    <div class="text-sm text-gray-600 space-y-2 mb-5">
        <p>Puede pedir que borremos su cuenta y todos sus datos personales: datos del CV, foto, estudios, experiencia y referencias.</p>
        <p><b>La forma más rápida:</b> inicie sesión y vaya a <b>Privacidad y cuenta → Solicitar eliminación</b>.</p>
        <p><b>Si no puede entrar,</b> use este formulario. Un administrador revisa cada solicitud y le avisamos por correo cuando esté lista.</p>
        <p class="text-xs text-gray-400">Se conserva solo un registro mínimo de la solicitud, sin sus datos personales, para demostrar que cumplimos la ley.</p>
    </div>
    <form method="POST" action="{{ route('eliminar.guardar') }}" class="space-y-4">
        @csrf
        <div><label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Nombre completo</label>
            <input type="text" name="nombre" value="{{ old('nombre') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
        <div><label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Correo de la cuenta</label>
            <input type="email" name="email" value="{{ old('email') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
        <div><label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Motivo (opcional)</label>
            <textarea name="motivo" rows="3" maxlength="1000" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">{{ old('motivo') }}</textarea></div>
        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold uppercase text-sm tracking-wide py-3 rounded-lg">Enviar solicitud</button>
        @include('partials.recaptcha', ['accion' => 'eliminar'])
    </form>
    <p class="text-center text-sm text-gray-500 mt-5"><a href="{{ route('login') }}" class="underline">Volver al inicio</a></p>
</x-publico>
