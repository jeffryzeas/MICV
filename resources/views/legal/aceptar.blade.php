<x-publico titulo="Términos actualizados" ancho="max-w-lg">
    <h2 class="font-bold text-lg text-[#061a33] mb-1">Antes de continuar</h2>
    <p class="text-sm text-gray-500 mb-5">Por favor lea y acepte la versión <b>{{ $cfg->legal_version }}</b> de nuestros textos legales.</p>
    <form method="POST" action="{{ route('legal.aceptar.guardar') }}" class="space-y-4">
        @csrf
        <label class="flex items-start gap-2 text-sm text-gray-600">
            <input type="checkbox" name="acepto" value="1" class="mt-1">
            <span>Leí y acepto los <a href="{{ route('legal.ver', 'terminos') }}" target="_blank" class="underline font-semibold text-[#0B3C74]">Términos y condiciones</a> y la <a href="{{ route('legal.ver', 'privacidad') }}" target="_blank" class="underline font-semibold text-[#0B3C74]">Política de privacidad</a>.</span>
        </label>
        @if($user->esCyber())
        <label class="flex items-start gap-2 text-sm text-gray-600">
            <input type="checkbox" name="contrato" value="1" class="mt-1">
            <span>En nombre de mi Cyber acepto el <a href="{{ route('legal.ver', 'contrato') }}" target="_blank" class="underline font-semibold text-[#0B3C74]">Contrato de servicio y tratamiento de datos</a>.</span>
        </label>
        @endif
        <button type="submit" class="w-full text-white font-semibold uppercase text-sm tracking-wide py-3 rounded-lg" style="background-color:#0B3C74;">Aceptar y continuar</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">@csrf<button class="text-sm text-gray-500 hover:text-red-600">Salir sin aceptar</button></form>
</x-publico>
