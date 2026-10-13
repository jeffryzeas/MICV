<x-publico titulo="Instalación" ancho="max-w-lg">
    <h2 class="font-bold text-lg text-[#061a33] mb-1">Primera vez: crear el administrador</h2>
    <p class="text-sm text-gray-500 mb-5">Esta pantalla solo aparece mientras no exista ningún usuario.</p>
    <form method="POST" action="{{ route('instalar.guardar') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Nombre del sistema</label>
            <input type="text" name="nombre_negocio" value="{{ old('nombre_negocio', 'Mi CV') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Su nombre completo</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Usuario</label>
                <input type="text" name="usuario" value="{{ old('usuario', 'ADMIN') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5 uppercase">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Correo</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Contraseña (mín. 8)</label>
                <input type="password" name="password" required autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Repetir contraseña</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
            </div>
        </div>
        <button type="submit" class="w-full text-white font-semibold uppercase text-sm tracking-wide py-3 rounded-lg" style="background-color:#0B3C74;">
            Crear administrador e ingresar
        </button>
    </form>
</x-publico>
