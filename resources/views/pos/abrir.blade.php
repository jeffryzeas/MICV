<x-layout titulo="Punto de venta" activo="pos-vender">
    @include('pos._barra')
    <div class="max-w-md mx-auto mt-6">
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5 p-6 text-center">
            <div class="mx-auto size-16 rounded-2xl bg-gradient-to-br from-azul-800 to-[#1d5fa8] text-white flex items-center justify-center shadow"><x-icono name="wallet" class="size-8" /></div>
            <h2 class="text-xl font-bold text-azul-950 mt-4">Abrir caja</h2>
            <p class="text-sm text-gray-500 mt-1">Cuente el efectivo con el que empieza el día (el "fondo" para dar cambio).</p>
            @if($ultima)<p class="text-xs text-gray-400 mt-2">El último cierre fue el {{ $ultima->cerrada_en?->format('d/m/Y H:i') }} con {{ $cfg->dinero($ultima->efectivo_contado) }} contados.</p>@endif
            <form method="POST" action="{{ route('pos.caja.abrir') }}" class="mt-5 space-y-4">
                @csrf
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-bold text-gray-400">{{ $cfg->simbolo }}</span>
                    <input type="number" name="monto_inicial" min="0" step="{{ $cfg->decimales ? '0.01' : '1' }}" value="{{ old('monto_inicial', 0) }}" required autofocus
                           class="w-full border border-gray-300 rounded-xl pl-14 pr-4 py-4 text-3xl font-bold text-center tabular-nums">
                </div>
                <button class="w-full inline-flex items-center justify-center gap-2 bg-azul-800 hover:bg-oro-500 text-white font-semibold py-3.5 rounded-xl"><x-icono name="lock-open" class="size-5" /> Abrir caja y empezar a vender</button>
            </form>
        </div>
        @if(!\App\Models\Pos\PosProducto::where('cyber_id', $cyberPos->id)->exists())
            <div class="mt-4 bg-blue-50 border border-blue-100 rounded-2xl p-4 text-sm text-azul-800">
                <p class="font-semibold">¿Primera vez?</p>
                <p class="mt-1">Revise la <a class="underline font-semibold" href="{{ route('pos.config') }}">configuración</a> (moneda, impuesto y datos del negocio) y agregue sus <a class="underline font-semibold" href="{{ route('pos.productos') }}">productos y servicios</a>. Puede cargar de una vez los servicios típicos de un Cyber.</p>
            </div>
        @endif
    </div>
</x-layout>
