@props([
    'titulo' => 'Mi CV',
    'activo' => '',
])
@php
    $configuracionSistema = \App\Models\Configuracion::actualSegura();
    $yo = auth()->user();
    $miCyber = $yo && !$yo->esAdmin() ? $yo->cyber : null;
    $rolBadge = $yo?->esAdmin() ? 'Admin' : ($yo?->esCliente() ? 'Mi cuenta' : ($miCyber?->nombre ?? 'Cyber'));
    $usuarioNombre = $yo?->name;
    $menuItems = \App\Support\Menu::para($yo);
    $logoutUrl = route('logout');
    $logoUrl = $miCyber?->logoUrl();
    $colorPrimario = $miCyber?->color_primario ?? $configuracionSistema?->color_primario ?? '#0B3C74';
    $colorSecundario = $miCyber?->color_secundario ?? $configuracionSistema?->color_secundario ?? '#c9971f';
    $nombreSistema = $configuracionSistema?->nombre_negocio ?? 'Mi CV';
    $logoSistema = ($configuracionSistema && \App\Support\Archivos::existe($configuracionSistema->logo)) ? route('marca.logo') : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<script>
    // Se aplica ANTES de que se pinte la página, para que no haya un
    // parpadeo blanco antes de pasar a oscuro. Recuerda la preferencia
    // guardada; si es la primera vez, usa la preferencia del sistema.
    (function () {
        const guardado = localStorage.getItem('jz-tema');
        const prefiereOscuro = guardado ? guardado === 'oscuro' : window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (prefiereOscuro) document.documentElement.classList.add('dark');
    })();
</script>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="{{ $colorPrimario }}">
@include('partials.head-app')

<title>{{ $titulo }} · {{ $nombreSistema }}</title>
<link rel="stylesheet" href="{{ asset('css/tema.css') }}?v={{ @filemtime(public_path('css/tema.css')) }}">
<style>:root{--jz-azul-800: {{ \App\Support\Color::rgb($colorPrimario) }};--jz-oro-500: {{ \App\Support\Color::rgb($colorSecundario, '#C9971F') }};--jz-oro-700: {{ \App\Support\Color::oscuro($colorSecundario) }};}</style>
<style>[x-cloak]{display:none!important}</style>
<script src="{{ asset('vendor/sweetalert2.all.min.js') }}"></script>
<script src="{{ asset('vendor/lucide.min.js') }}"></script>
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
.live-status { display:none; margin-top:10px; padding:10px 14px; border-radius:8px; font-size:13px; line-height:1.5; }
.live-status.show { display:block; }
.live-status.info { background:#eaf2fb; color:#0b3c74; border:1px solid #bcd6f2; }
.live-status.ok   { background:#e7f6ee; color:#1e8e5a; border:1px solid #b9e6cd; }
.live-status.warn { background:#fff8e6; color:#a9791c; border:1px solid #f0dca3; }
.live-status.err  { background:#fdecea; color:#c0392b; border:1px solid #f5c6c0; }
.cedula-box { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.cedula-box input { border:1.5px solid #d7dee6; border-radius:8px; padding:10px; font-size:16px; text-align:center; }
.cedula-box input:focus { outline:none; border-color:#c9971f; box-shadow:0 0 0 3px rgba(201,151,31,.2); }
.cedula-box .sep { color:#9aa7b5; font-weight:700; }

/* ===== MODO OSCURO ===== */
/* Overrides centralizados sobre las clases que ya usa TODO el sistema —
   no hace falta tocar cada vista una por una. Se activan solo cuando
   <html> tiene la clase "dark" (la pone jzModoOscuro() más abajo). */
.dark body { background-color: #0f1520; color: #d1d9e6; }
.dark main { background-color: #0f1520; }
.dark header.bg-white { background-color: #16202e !important; border-color: #263243 !important; }
.dark .bg-white { background-color: #182233 !important; }
.dark .bg-gray-50, .dark .bg-gray-100 { background-color: #1c2838 !important; }
.dark .bg-gray-200 { background-color: #263243 !important; }
.dark .text-azul-950 { color: #eef3fb !important; }
.dark .text-azul-800 { color: #7fb0e8 !important; }
.dark .text-gray-900, .dark .text-gray-800 { color: #e5ebf3 !important; }
.dark .text-gray-700 { color: #c3cbd8 !important; }
.dark .text-gray-600 { color: #aab6c7 !important; }
.dark .text-gray-500 { color: #8b96a8 !important; }
.dark .text-gray-400 { color: #6b7688 !important; }
.dark .border-gray-200, .dark .border-gray-300 { border-color: #2a3648 !important; }
.dark .border-b, .dark .border-t { border-color: #2a3648; }
.dark .divide-y > * + * { border-color: #2a3648 !important; }
.dark .shadow, .dark .shadow-2xl { box-shadow: 0 1px 3px rgba(0,0,0,.5) !important; }
.dark input, .dark select, .dark textarea {
    background-color: #101826 !important; color: #e5ebf3 !important; border-color: #2a3648 !important;
}
.dark input::placeholder, .dark textarea::placeholder { color: #5c6779; }
.dark .bg-blue-50 { background-color: #142338 !important; }
.dark .bg-emerald-50 { background-color: #0f2a20 !important; }
.dark .bg-red-50 { background-color: #2a1414 !important; }
.dark .bg-amber-50, .dark .from-amber-50 { background-color: #2a2210 !important; }
.dark .bg-gray-100.hover\:bg-gray-200:hover { background-color: #263243 !important; }
.dark code.bg-white { background-color: #101826 !important; color: #e5ebf3 !important; }

/* ===== ALTO REAL DEL MENU EN CELULAR =====
   En los navegadores de celular, 100vh NO es lo que se ve: incluye el
   espacio que tapa la barra de direcciones. Por eso el final del menu
   quedaba por debajo de la pantalla y no se podia alcanzar.
   "dvh" si mide el alto realmente visible y se ajusta solo. */
#appSidebar { height: 100vh; }
@supports (height: 100dvh) {
    #appSidebar { height: 100dvh; }
}
@media (min-width: 1024px) {
    #appSidebar { height: 100vh; }
}

/* Respiro al final para que el ultimo item no quede pegado al borde,
   y desplazamiento suave en iPhone. */
#jzMenu {
    padding-bottom: 28px;
    -webkit-overflow-scrolling: touch;
}
</style>
<style>
/* Al imprimir (reportes de caja, etc.) solo sale el contenido, sin menú ni encabezado. */
@media print {
    #appSidebar, body > div > div > header, body > div > div > footer, .swal2-container, .print\:hidden { display: none !important; }
    body, main { background: #fff !important; }
    main { padding: 0 !important; }
}
</style>
</head>
<body class="min-h-screen bg-gray-100 text-gray-800 antialiased">
<div class="flex min-h-screen">
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden" onclick="toggleSidebar()"></div>

    <aside id="appSidebar" class="fixed lg:sticky inset-y-0 left-0 lg:top-0 z-40 -translate-x-full lg:translate-x-0 transition-transform duration-200 w-64 h-screen shrink-0 bg-azul-950 text-white flex flex-col">
        <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
            @if(!empty($logoUrl))
                {{-- Logo propio del Cyber (lo pone el Cyber desde "Mi marca") --}}
                <img src="{{ $logoUrl }}" alt="Logo" class="h-9 max-w-[100px] object-contain bg-white rounded p-0.5">
            @elseif($logoSistema)
                {{-- Logo general del sistema (lo pone el admin principal) --}}
                <img src="{{ $logoSistema }}" alt="Logo" class="h-9 max-w-[100px] object-contain bg-white rounded p-0.5">
            @else
                <div class="size-9 rounded-lg bg-gradient-to-br from-azul-800 to-azul-400 flex items-center justify-center font-bold text-oro-300 text-sm">CV</div>
            @endif
            <div>
                <div class="font-bold text-sm">{{ $nombreSistema }}</div>
                <div class="text-[11px] text-white/50">{{ $rolBadge ?? '' }}</div>
            </div>
        </div>

        <div class="px-3 pt-3 pb-2 shrink-0">
            <div class="relative">
                <i data-lucide="search" class="absolute left-2.5 top-2.5 size-4 text-white/40"></i>
                <input type="text" id="jzBuscarMenu" placeholder="Buscar en el menú…" autocomplete="off"
                       class="w-full bg-white/10 border border-white/15 rounded-lg pl-8 pr-7 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-oro-300/60">
                <button type="button" id="jzLimpiarMenu" class="hidden absolute right-2 top-2 text-white/50 hover:text-white">
                    <i data-lucide="x" class="size-4"></i>
                </button>
            </div>
        </div>

        <nav id="jzMenu" class="flex-1 overflow-y-auto overscroll-contain py-1 px-3 space-y-1">
            @foreach(($menuItems ?? []) as $item)
                @if(!empty($item['hijos']))
                    @php $abierto = collect($item['hijos'])->contains(fn ($h) => ($activo ?? '') === $h['id']); @endphp
                    <details class="jz-grupo-menu group/g" @if($abierto) open data-abierto="1" @endif>
                        <summary class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium cursor-pointer list-none select-none {{ $abierto ? 'text-white' : 'text-white/75 hover:bg-white/5 hover:text-white' }}">
                            <i data-lucide="{{ $item['icon'] }}" class="size-[18px] shrink-0"></i>
                            <span>{{ $item['label'] }}</span>
                            <i data-lucide="chevron-down" class="size-4 ml-auto transition-transform group-open/g:rotate-180"></i>
                        </summary>
                        <div class="ml-4 pl-2 border-l border-white/10 space-y-0.5 mt-0.5 mb-1">
                            @foreach($item['hijos'] as $hijo)
                                @include('partials.menu-item', ['item' => $hijo, 'grupo' => $item['label']])
                            @endforeach
                        </div>
                    </details>
                @else
                    @include('partials.menu-item', ['item' => $item])
                @endif
            @endforeach
            <p id="jzMenuVacio" class="hidden text-center text-white/40 text-xs py-4">Nada coincide con esa búsqueda.</p>
        </nav>
    </aside>

    <div class="flex-1 flex flex-col min-w-0">
        <header class="bg-white border-b border-gray-200 px-5 py-3 flex items-center justify-between sticky top-0 z-20">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <button type="button" onclick="toggleSidebar()" class="lg:hidden text-gray-500">
                    <i data-lucide="menu" class="size-5"></i>
                </button>
                <h1 class="text-lg font-semibold text-azul-950 truncate" title="{{ $titulo }}">{{ $titulo ?? '' }}</h1>
            </div>
            <div class="flex items-center gap-2 text-sm shrink-0">
                <span class="px-2 py-0.5 rounded-full bg-oro-500/15 text-oro-700 text-xs font-bold uppercase">{{ $rolBadge ?? '' }}</span>
                <span class="text-gray-600 hidden sm:inline-flex items-center gap-1">{{ $usuarioNombre ?? '' }}@if($yo?->verificado())@include('partials.verificado', ['tam' => 16])@endif</span>
                <button type="button" onclick="jzAlternarModoOscuro()" title="Modo oscuro" class="text-gray-500 hover:text-azul-800 border border-gray-200 rounded-lg p-2 transition-colors">
                    <i data-lucide="moon" id="jzIconoOscuro" class="size-4"></i>
                    <i data-lucide="sun" id="jzIconoClaro" class="size-4 hidden"></i>
                </button>
                @if($yo && (\App\Services\ChatAcceso::habilitado($yo)))
                <a href="{{ route('chat.index') }}" id="jz-chat-notificacion" data-url="{{ route('chat.lista') }}" aria-label="Mensajes y notificaciones" style="position:relative;display:inline-flex;align-items:center;gap:5px;padding:8px;color:#087c58"><i data-lucide="bell" class="size-5"></i><span id="jz-chat-contador" hidden style="font-size:11px;border-radius:20px;background:#b42318;color:#fff;padding:2px 6px"></span></a>
                @endif
                <form method="POST" action="{{ $logoutUrl ?? '#' }}" class="m-0">
                    @csrf
                    <button type="submit" title="Cerrar sesión" class="flex items-center gap-1.5 text-gray-500 hover:text-red-600 border border-gray-200 hover:border-red-300 rounded-lg px-2.5 py-1.5 transition-colors">
                        <i data-lucide="log-out" class="size-4"></i>
                        <span class="hidden sm:inline text-xs font-semibold">Salir</span>
                    </button>
                </form>
            </div>
        </header>

        <main class="flex-1 p-5">
            @if(session('mensaje'))
                <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm">
                    {{ session('mensaje') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
                    <ul class="list-disc pl-5 space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer>@include('partials.pie-legal')</footer>
    </div>
</div>

<script>
    lucide.createIcons();
    function toggleSidebar() {
        const menu    = document.getElementById('appSidebar');
        const fondo   = document.getElementById('sidebarOverlay');
        const abierto = menu.classList.toggle('-translate-x-full') === false;

        fondo.classList.toggle('hidden', !abierto);
        // Se bloquea el scroll del fondo mientras el menu esta abierto en
        // celular: si no, se mueve la pagina de atras y parece trabado.
        document.body.style.overflow = abierto ? 'hidden' : '';

        if (abierto) {
            const buscador = document.getElementById('jzBuscarMenu');
            if (buscador && window.innerWidth >= 640) buscador.focus();
        }
    }

    function jzCerrarMenuMovil() {
        const menu = document.getElementById('appSidebar');
        if (menu.classList.contains('-translate-x-full')) return;
        if (window.innerWidth >= 1024) return;
        toggleSidebar();
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') jzCerrarMenuMovil();
    });

    // Buscador del menu: filtra los items mientras se escribe.
    (function () {
        const buscador = document.getElementById('jzBuscarMenu');
        const limpiar  = document.getElementById('jzLimpiarMenu');
        const vacio    = document.getElementById('jzMenuVacio');
        if (!buscador) return;

        function normalizar(t) {
            return (t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        function filtrar() {
            const q = normalizar(buscador.value.trim());
            let visibles = 0;

            document.querySelectorAll('.jz-item-menu').forEach(function (a) {
                const coincide = q === '' || normalizar(a.dataset.menuTexto).includes(q);
                a.classList.toggle('hidden', !coincide);
                if (coincide) visibles++;
            });

            // Grupos: al buscar se abren y se ocultan los que no tienen coincidencias.
            document.querySelectorAll('.jz-grupo-menu').forEach(function (g) {
                const hay = g.querySelector('.jz-item-menu:not(.hidden)');
                g.classList.toggle('hidden', !hay);
                g.open = q !== '' ? !!hay : g.dataset.abierto === '1';
            });
            if (vacio) vacio.classList.toggle('hidden', visibles > 0);
            if (limpiar) limpiar.classList.toggle('hidden', buscador.value === '');
        }

        buscador.addEventListener('input', filtrar);

        // Enter abre el primer resultado visible.
        buscador.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const primero = document.querySelector('.jz-item-menu:not(.hidden)');
            if (primero) primero.click();
        });

        if (limpiar) {
            limpiar.addEventListener('click', function () {
                buscador.value = '';
                filtrar();
                buscador.focus();
            });
        }
    })();

    function jzActualizarIconoModo() {
        const esOscuro = document.documentElement.classList.contains('dark');
        document.getElementById('jzIconoOscuro')?.classList.toggle('hidden', esOscuro);
        document.getElementById('jzIconoClaro')?.classList.toggle('hidden', !esOscuro);
    }
    function jzAlternarModoOscuro() {
        const esOscuro = document.documentElement.classList.toggle('dark');
        localStorage.setItem('jz-tema', esOscuro ? 'oscuro' : 'claro');
        jzActualizarIconoModo();
    }
    jzActualizarIconoModo();
    @if(session('mensaje'))
        // El mensaje ya sale arriba de la página: aquí solo un aviso pequeño que se va solo (no interrumpe).
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: @json(session('mensaje')), showConfirmButton: false, timer: 3200, timerProgressBar: true });
    @endif
    @if($errors->any() && $errors->has('general'))
        Swal.fire({ icon: 'error', title: 'Error', text: @json($errors->first('general')) });
    @endif
</script>
<script>window.jzCamposConError = @json(isset($errors) ? $errors->keys() : []);</script>
<script>
// Formularios largos por PASOS (Foto → Documento → Nombre → …) que se pueden saltar:
// los chips de arriba llevan a cualquier paso y la barra de abajo tiene
// Anterior / Siguiente / Guardar siempre a la mano. "Ver todo" muestra el formulario completo.
(function () {
    document.querySelectorAll('form').forEach(function (form) {
        const nav = form.querySelector('[data-indice]');
        if (!nav) return;
        const secs = Array.from(form.querySelectorAll('.jz-seccion[data-titulo]'));
        if (secs.length < 3) { nav.remove(); return; }
        const barra = form.querySelector('[data-barra]');
        const clave = 'jz-modo:' + location.pathname.replace(/\d+/g, '#');
        let modo = form.dataset.pasos === 'no' ? 'todo' : 'pasos';
        try { modo = localStorage.getItem(clave) || modo; } catch (e) {}
        let actual = 0, sucio = false, enviando = false;

        const textoLleno = el => !el.disabled && el.type !== 'hidden' && ['text', 'tel', 'email', 'date', 'number', 'url', 'textarea', 'search'].includes(el.type || el.tagName.toLowerCase()) && el.value.trim() !== '';
        const lleno = s => !!s.querySelector('[data-foto-lista]') || Array.from(s.querySelectorAll('input, textarea')).some(textoLleno);

        nav.className = 'sticky top-14 z-20 flex gap-2 overflow-x-auto py-2 mb-4 -mx-1 px-1 bg-white/95 dark:bg-gray-900/95 backdrop-blur';
        nav.innerHTML = secs.map((s, i) => '<button type="button" data-i="' + i + '" class="jz-chip shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border border-gray-300 bg-white text-azul-800 hover:border-azul-800"><span class="jz-num inline-flex items-center justify-center size-4 rounded-full bg-gray-200 text-[10px]">' + (i + 1) + '</span>' + s.dataset.titulo + '</button>').join('');
        const chips = Array.from(nav.querySelectorAll('.jz-chip'));

        function pintar() {
            chips.forEach(function (c, i) {
                const hecho = lleno(secs[i]), activo = modo === 'pasos' && i === actual;
                c.classList.toggle('bg-azul-800', activo); c.classList.toggle('text-white', activo); c.classList.toggle('border-azul-800', activo);
                c.classList.toggle('bg-white', !activo); c.classList.toggle('text-azul-800', !activo);
                const n = c.querySelector('.jz-num');
                n.textContent = hecho && !activo ? '✓' : (i + 1);
                n.className = 'jz-num inline-flex items-center justify-center size-4 rounded-full text-[10px] ' + (activo ? 'bg-white/25' : hecho ? 'bg-emerald-500 text-white' : 'bg-gray-200');
            });
            if (barra) {
                const hechos = secs.filter(lleno).length;
                barra.querySelector('[data-paso-texto]').textContent = modo === 'pasos'
                    ? (actual + 1) + '/' + secs.length + ' · ' + secs[actual].dataset.titulo
                    : hechos + ' de ' + secs.length + ' secciones con datos';
                barra.querySelector('[data-progreso]').style.width = Math.round(hechos / secs.length * 100) + '%';
                barra.querySelector('[data-modo]').textContent = modo === 'pasos' ? 'Ver todo' : 'Por pasos';
                barra.querySelector('[data-ant]').disabled = modo === 'pasos' && actual === 0;
                barra.querySelector('[data-sig]').disabled = modo === 'pasos' && actual === secs.length - 1;
                barra.querySelector('[data-ant]').classList.toggle('hidden', modo !== 'pasos');
                barra.querySelector('[data-sig]').classList.toggle('hidden', modo !== 'pasos');
            }
        }
        function arriba() {
            const y = form.getBoundingClientRect().top + window.scrollY - 70;
            if (window.scrollY > y) window.scrollTo({ top: y, behavior: 'smooth' });
        }
        function ir(i, desplazar) {
            actual = Math.max(0, Math.min(secs.length - 1, i));
            form.classList.toggle('jz-modo-pasos', modo === 'pasos');
            if (modo === 'pasos') {
                secs.forEach((s, j) => { s.hidden = j !== actual; s.classList.toggle('jz-paso-solo', true); });
                if (desplazar !== false) arriba();
            } else {
                secs.forEach(s => { s.hidden = false; s.classList.remove('jz-paso-solo'); });
                if (desplazar !== false) window.scrollTo({ top: secs[actual].getBoundingClientRect().top + window.scrollY - 130, behavior: 'smooth' });
            }
            chips[actual].scrollIntoView({ block: 'nearest', inline: 'center' });
            pintar();
            if (window.lucide) lucide.createIcons();
            secs[actual].dispatchEvent(new CustomEvent('jz-paso', { bubbles: true }));
        }
        function cambiarModo() {
            modo = modo === 'pasos' ? 'todo' : 'pasos';
            try { localStorage.setItem(clave, modo); } catch (e) {}
            ir(actual, true);
        }
        function seccionDe(el) { return secs.findIndex(s => s.contains(el)); }

        nav.addEventListener('click', e => { const b = e.target.closest('.jz-chip'); if (b) ir(+b.dataset.i); });
        if (barra) {
            barra.querySelector('[data-ant]').addEventListener('click', () => ir(actual - 1));
            barra.querySelector('[data-sig]').addEventListener('click', () => ir(actual + 1));
            barra.querySelector('[data-modo]').addEventListener('click', cambiarModo);
        }
        // Solo cuentan los cambios hechos por la persona (no los que hace el sistema al cargar).
        form.addEventListener('input', e => { if (e.isTrusted) sucio = true; pintar(); });
        form.addEventListener('change', e => { if (e.isTrusted) sucio = true; pintar(); });
        form.addEventListener('click', e => {
            const b = e.target.closest('button[type=button]');
            if (b && !b.closest('[data-indice], [data-barra]')) sucio = true;
        });
        document.addEventListener('click', e => { if (e.target.closest('.jz-frase, .jz-sug')) sucio = true; });

        // Si un campo obligatorio está en otro paso, se abre ese paso antes de avisar.
        form.querySelectorAll('button[type=submit], button:not([type])').forEach(function (b) {
            b.addEventListener('click', function (e) {
                if (form.noValidate || form.checkValidity()) { return; }
                e.preventDefault();
                const malo = form.querySelector(':invalid');
                const i = seccionDe(malo);
                if (i >= 0 && modo === 'pasos') ir(i, false);
                setTimeout(() => form.reportValidity(), 50);
            });
        });
        form.addEventListener('submit', () => { enviando = true; });
        // Cerrar la pestaña o recargar: el navegador obliga a usar su propio aviso.
        window.addEventListener('beforeunload', function (e) {
            if (sucio && !enviando) { e.preventDefault(); e.returnValue = ''; }
        });
        // Moverse dentro de la app (menú, enlaces, Salir): aviso con el diseño del sistema.
        function avisarSalida(seguir) {
            Swal.fire({
                icon: 'warning', title: 'Tiene cambios sin guardar',
                html: 'Si sale ahora, <b>se pierde lo que escribió</b> desde la última vez que guardó.',
                showDenyButton: true, showCancelButton: true,
                confirmButtonText: '<span style="display:inline-flex;align-items:center;gap:6px">💾 Guardar ahora</span>',
                denyButtonText: 'Salir sin guardar', cancelButtonText: 'Seguir editando',
                confirmButtonColor: '#0B3C74', denyButtonColor: '#dc2626', reverseButtons: false,
            }).then(function (r) {
                if (r.isConfirmed) { const b = (barra && barra.querySelector('button[type=submit]')) || form.querySelector('button[type=submit]'); if (b) b.click(); }
                else if (r.isDenied) { enviando = true; seguir(); }
            });
        }
        document.addEventListener('click', function (e) {
            const a = e.target.closest('a[href]');
            if (!a || !sucio || enviando || form.contains(a) && a.getAttribute('href').startsWith('#')) return;
            if (a.target === '_blank' || e.ctrlKey || e.metaKey || e.shiftKey || a.hasAttribute('download')) return;
            const href = a.getAttribute('href'); if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
            e.preventDefault(); avisarSalida(() => { window.location = a.href; });
        }, true);
        document.querySelectorAll('form').forEach(function (otro) {
            if (otro === form) return;
            otro.addEventListener('submit', function (e) {
                if (!sucio || enviando || otro.dataset.salirOk) return;
                e.preventDefault(); e.stopImmediatePropagation();
                avisarSalida(() => { otro.dataset.salirOk = 1; otro.submit(); });
            }, true);
        });

        // Al volver con errores del servidor, abrir el paso del primer campo con error.
        let inicio = 0;
        const errs = window.jzCamposConError || [];
        for (const k of errs) {
            const base = k.split('.')[0];
            const el = form.querySelector('[name="' + k + '"], [name^="' + base + '["], [name="' + base + '"]');
            if (el && seccionDe(el) >= 0) { inicio = seccionDe(el); break; }
        }
        if (!errs.length && location.hash) {
            const t = form.querySelector(location.hash.replace(/[^#\w-]/g, ''));
            if (t) inicio = Math.max(0, seccionDe(t));
        }
        ir(inicio, false);
    });
})();
</script>
<style>
.jz-seccion.jz-paso-solo { border-top: 0 !important; padding-top: 0 !important; margin-top: 0 !important; }
.jz-seccion[hidden] { display: none !important; }
.jz-modo-pasos .jz-separador { display: none !important; }
</style>
@stack('scripts')

<div id="jzCargando" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(6,26,51,.42); backdrop-filter:blur(3px);">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);">
        <div style="background:#fff; border-radius:16px; padding:26px 34px; box-shadow:0 12px 40px rgba(0,0,0,.28); text-align:center; min-width:190px;">
            <div style="position:relative; width:46px; height:46px; margin:0 auto 14px auto;">
                <div style="position:absolute; inset:0; border:4px solid #e8edf4; border-radius:50%;"></div>
                <div style="position:absolute; inset:0; border:4px solid transparent; border-top-color:#0B3C74; border-right-color:#0B3C74; border-radius:50%; animation:jzGirar .7s cubic-bezier(.5,.15,.5,.85) infinite;"></div>
                <div style="position:absolute; inset:9px; border:3px solid transparent; border-bottom-color:#c9971f; border-radius:50%; animation:jzGirar 1s linear infinite reverse;"></div>
            </div>
            <div id="jzCargandoTexto" style="color:#0B3C74; font-size:13.5px; font-weight:700; letter-spacing:.2px;">Procesando…</div>
            <div style="color:#98a2b3; font-size:11px; margin-top:3px;">Un momento por favor</div>
        </div>
    </div>
</div>
<style>
@keyframes jzGirar { to { transform: rotate(360deg); } }
#jzCargando.jz-visible { display:block !important; }
</style>
<script>
(function () {
    const capa  = document.getElementById('jzCargando');
    const texto = document.getElementById('jzCargandoTexto');
    let temporizadorSeguridad = null;

    window.jzMostrarCargando = function (mensaje) {
        if (texto && mensaje) texto.textContent = mensaje;
        capa.classList.add('jz-visible');

        // SEGURO ANTI-CONGELAMIENTO: si por lo que sea la pagina no
        // responde, la capa se quita sola y se devuelven los botones.
        clearTimeout(temporizadorSeguridad);
        temporizadorSeguridad = setTimeout(function () {
            jzOcultarCargando();
            document.querySelectorAll('button[type="submit"][data-jz-bloqueado]').forEach(function (b) {
                b.disabled = false;
                b.style.opacity = '';
                b.removeAttribute('data-jz-bloqueado');
            });
        }, 20000);
    };

    window.jzOcultarCargando = function () {
        clearTimeout(temporizadorSeguridad);
        capa.classList.remove('jz-visible');
    };

    // Escape siempre libera la pantalla.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') jzOcultarCargando();
    });

    // Frases segun la accion, para que el mensaje diga lo que de verdad
    // esta pasando en vez de un "Guardando" generico.
    const FRASES = [
        [/ingresar|iniciar sesi/i,        'Ingresando'],
        [/cerrar sesi|salir/i,            'Cerrando sesión'],
        [/eliminar|borrar/i,              'Eliminando'],
        [/enviar prueba/i,                'Enviando prueba'],
        [/enviar|mandar/i,                'Enviando'],
        [/generar|crear/i,                'Generando'],
        [/registrar/i,                    'Registrando'],
        [/buscar/i,                       'Buscando'],
        [/actualizar/i,                   'Actualizando'],
        [/instalar/i,                     'Instalando'],
        [/restablecer/i,                  'Restableciendo'],
        [/desconectar/i,                  'Desconectando'],
        [/compartir|vincular/i,           'Vinculando'],
        [/guardar/i,                      'Guardando'],
    ];

    function frasePara(form) {
        if (form.dataset.cargando) return form.dataset.cargando;

        const boton = form._jzBoton || form.querySelector('button[type="submit"]');
        const etiqueta = boton ? (boton.innerText || boton.textContent || '').trim() : '';

        for (const [patron, frase] of FRASES) {
            if (patron.test(etiqueta)) return frase + '…';
        }

        return etiqueta ? etiqueta + '…' : 'Procesando…';
    }

    // Se recuerda cual boton se presiono, para leer su texto.
    document.addEventListener('click', function (e) {
        const b = e.target.closest('button[type="submit"]');
        if (b && b.form) b.form._jzBoton = b;
    }, true);

    function jzActivarEnvio(form) {
        if (!(form instanceof HTMLFormElement)) return;
        if (form.hasAttribute('data-sin-cargando')) return;
        if (form.getAttribute('target') === '_blank') return;

        form.querySelectorAll('button[type="submit"]').forEach(function (b) {
            b.disabled = true;
            b.style.opacity = '.6';
            b.setAttribute('data-jz-bloqueado', '1');
        });

        jzMostrarCargando(frasePara(form));
    }

    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        jzActivarEnvio(e.target);
    });

    // La mayoria de formularios confirman con SweetAlert y luego llaman
    // form.submit() por codigo, lo cual NO dispara el evento de arriba.
    const envioOriginal = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        jzActivarEnvio(this);
        return envioOriginal.apply(this, arguments);
    };

    window.addEventListener('pageshow', function () { jzOcultarCargando(); });
    window.addEventListener('error', function () { jzOcultarCargando(); });
})();
</script>

<script src="{{ asset('js/chat-notificaciones.js') }}" defer></script>
</body>
</html>
