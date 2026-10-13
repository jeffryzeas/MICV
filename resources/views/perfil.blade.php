<x-layout titulo="Mi perfil y contraseña" activo="perfil">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><i data-lucide="user-circle" class="size-[18px] text-oro-500"></i> Mis datos</h2>
            <form method="POST" action="{{ route('perfil.update') }}" class="space-y-4">
                @csrf @method('PUT')
                <div><label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Usuario</label>
                    <input type="text" value="{{ $user->usuario }}" disabled class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2.5 font-mono"></div>
                <div><label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Nombre completo</label>
                    @if($user->esCliente() && $user->cliente)
                        <input type="hidden" name="name" value="{{ $user->cliente->nombreCompleto() }}">
                        <input type="text" value="{{ $user->cliente->nombreCompleto() }}" disabled class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2.5">
                        <p class="text-[11px] text-gray-500 mt-1">Es el mismo nombre de su CV. Para cambiarlo vaya a <a href="{{ route('mi-cv.editar') }}#sec-nombre" class="underline font-semibold">Mis datos</a>.</p>
                    @else
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5">
                    @endif
                </div>
                <div><label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Correo</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
                <p class="text-xs text-gray-500">{{ $user->rolLegible() }}@if($user->cyber) · {{ $user->cyber->nombre }}@endif</p>
                <button class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg"><i data-lucide="check" class="size-4"></i> Guardar</button>
            </form>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <h2 class="font-semibold text-azul-950 mb-4 flex items-center gap-2"><i data-lucide="lock" class="size-[18px] text-oro-500"></i> Cambiar contraseña</h2>
            <form method="POST" action="{{ route('perfil.password') }}" class="space-y-4">
                @csrf @method('PUT')
                <div><label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Contraseña actual</label>
                    <input type="password" name="actual" required autocomplete="current-password" class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
                <div><label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Nueva contraseña (mín. 8)</label>
                    <input type="password" name="password" required autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
                <div><label class="block text-xs font-semibold uppercase text-azul-800 mb-1">Repetir nueva contraseña</label>
                    <input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
                <button class="inline-flex items-center gap-2 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg"><i data-lucide="key-round" class="size-4"></i> Cambiar contraseña</button>
            </form>
        </div>
    </div>
</x-layout>
