<x-publico titulo="Crear mi cuenta" ancho="max-w-lg">
    <h2 class="font-bold text-lg text-[#061a33] mb-1">Cree su cuenta y haga su CV</h2>
    <p class="text-sm text-gray-500 mb-5">Es gratis. Le enviaremos un <b>código de 6 dígitos</b> a su correo para verificar la cuenta.</p>
    <form method="POST" action="{{ route('registro.guardar') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-semibold uppercase text-gray-500 mb-2">1. ¿Cuál es su nacionalidad?</label>
            <div class="grid grid-cols-3 gap-2">
                @foreach(\App\Support\Pais::DATOS as $cod => $p)
                    <label class="cursor-pointer">
                        <input type="radio" name="pais" value="{{ $cod }}" class="peer hidden" @checked(old('pais', 'NI') === $cod)>
                        <span class="flex flex-col items-center gap-1 border-2 border-gray-200 rounded-xl py-3 text-sm font-semibold text-gray-700 peer-checked:border-[#0B3C74] peer-checked:bg-blue-50 peer-checked:text-[#0B3C74]">
                            <span class="text-2xl">{{ $p['bandera'] }}</span>{{ $p['nombre'] }}
                        </span>
                    </label>
                @endforeach
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Con esto adaptamos sus documentos (cédula, DIMEX, INSS o CCSS) y el escudo del CV.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Primer nombre</label>
                <input type="text" name="primer_nombre" value="{{ old('primer_nombre') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
            <div><label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Primer apellido</label>
                <input type="text" name="primer_apellido" value="{{ old('primer_apellido') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
        </div>
        <div class="relative">
            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Correo electrónico</label>
            <i data-lucide="mail" class="absolute left-3 top-[30px] size-4 text-gray-400"></i>
            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2.5">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Contraseña (mín. 8)</label>
                <input type="password" name="password" required autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
            <div><label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Repetir contraseña</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full border border-gray-300 rounded-lg px-3 py-2.5"></div>
        </div>
        <label class="flex items-start gap-2 text-sm text-gray-600">
            <input type="checkbox" name="acepto" value="1" class="mt-1" @checked(old('acepto'))>
            <span>Leí y acepto los <a href="{{ route('legal.ver', 'terminos') }}" target="_blank" class="underline font-semibold text-[#0B3C74]">Términos y condiciones</a> y la <a href="{{ route('legal.ver', 'privacidad') }}" target="_blank" class="underline font-semibold text-[#0B3C74]">Política de privacidad</a>. Doy mi consentimiento para que mis datos se usen para crear mi CV y mis cartas. Soy mayor de edad o tengo permiso de mi representante legal.</span>
        </label>
        <button type="submit" class="w-full text-white font-semibold uppercase text-sm tracking-wide py-3 rounded-lg" style="background-color:#0B3C74;"
                onmouseover="this.style.backgroundColor='#C9971F'" onmouseout="this.style.backgroundColor='#0B3C74'">Crear cuenta</button>
        @include('partials.recaptcha', ['accion' => 'registro'])
    </form>
    <p class="text-center text-sm text-gray-500 mt-5">¿Ya tiene cuenta? <a href="{{ route('login') }}" class="font-semibold text-[#0B3C74] underline">Inicie sesión</a></p>
</x-publico>
