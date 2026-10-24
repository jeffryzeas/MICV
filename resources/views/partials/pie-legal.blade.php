{{-- Pie de página con enlaces legales + aviso de cookies + registro de la app (PWA) --}}
@php $cfgPie = \App\Models\Configuracion::actualSegura(); $oscuro = $oscuro ?? false; @endphp
<div class="text-center text-xs {{ $oscuro ? 'text-white/50' : 'text-gray-400' }} py-4 px-3 space-y-1">
    <div class="flex flex-wrap justify-center gap-x-3 gap-y-1">
        <a href="{{ route('legal.ver', 'terminos') }}" class="hover:underline">Términos</a>
        <a href="{{ route('legal.ver', 'privacidad') }}" class="hover:underline">Privacidad</a>
        <a href="{{ route('legal.ver', 'cookies') }}" class="hover:underline">Cookies</a>
        <a href="{{ route('legal.ver', 'contrato') }}" class="hover:underline">Contrato Negocios</a>
        <a href="{{ route('eliminar.mostrar') }}" class="hover:underline">Eliminar mi cuenta</a>
    </div>
    <div>&copy; {{ date('Y') }} {{ $cfgPie?->nombre_negocio ?? 'Mi CV' }}@if($cfgPie?->pie_pagina) · {{ $cfgPie->pie_pagina }}@endif</div>
</div>
<div id="jzCookies" style="display:none" class="fixed bottom-3 left-3 right-3 sm:left-auto sm:max-w-sm z-[60] bg-white text-gray-700 border border-gray-200 rounded-xl shadow-2xl p-4 text-sm">
    <p class="mb-3">Usamos solo cookies necesarias para la sesión y la seguridad. <a href="{{ route('legal.ver', 'cookies') }}" class="underline font-semibold text-[#0B3C74]">Saber más</a></p>
    <button type="button" onclick="try{localStorage.setItem('jz-cookies','1')}catch(e){};document.getElementById('jzCookies').style.display='none'" class="w-full bg-[#0B3C74] text-white font-semibold rounded-lg py-2">Entendido</button>
</div>
<script>
try { if (!localStorage.getItem('jz-cookies')) document.getElementById('jzCookies').style.display = 'block'; } catch (e) {}
// Mantiene vigente el token de seguridad de los formularios (evita "Página expirada"
// cuando la página queda abierta mucho tiempo o se vuelve con el botón Atrás).
(function () {
    let ultimo = Date.now();
    function refrescar() {
        fetch(@json(route('csrf.refrescar')), { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : null).then(function (d) {
                if (!d || !d.token) return;
                ultimo = Date.now();
                document.querySelectorAll('input[name="_token"]').forEach(i => i.value = d.token);
                const m = document.querySelector('meta[name="csrf-token"]'); if (m) m.setAttribute('content', d.token);
            }).catch(function () {});
    }
    setInterval(refrescar, 10 * 60 * 1000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden && Date.now() - ultimo > 5 * 60 * 1000) refrescar(); });
    window.addEventListener('pageshow', function (e) { if (e.persisted) refrescar(); });
    window.jzRefrescarToken = refrescar;
})();
if ('serviceWorker' in navigator) { window.addEventListener('load', function () { navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(function () {}); }); }
</script>
