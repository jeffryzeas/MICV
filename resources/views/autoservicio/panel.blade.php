@php $card = 'bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5'; $yo = auth()->user(); @endphp
<x-layout titulo="Autoservicio por QR" activo="autoservicio">
    <p class="text-gray-500 text-sm mb-4">Ponga el afiche en el mostrador. La gente escanea el QR, llena sus datos desde su celular y le da un número de turno. Aquí le aparecen al instante: toque <b>Atender</b>, revise y guarde la ficha.</p>

    @if($yo->esAdmin() && $cybers->isNotEmpty())
        <form class="mb-4 flex items-center gap-2 text-sm"><label class="text-gray-500">Cyber:</label>
            <select name="cyber" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2">@foreach($cybers as $cy)<option value="{{ $cy->id }}" @selected($cyber?->id === $cy->id)>{{ $cy->nombre }}</option>@endforeach</select></form>
    @endif

    @if(!$cyber)
        <div class="{{ $card }} p-6 text-gray-500">Primero registre un Cyber.</div>
    @else
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <section class="{{ $card }} p-5 text-center">
            <div class="mx-auto w-52 rounded-2xl ring-4 p-3 {{ $cyber->autoservicio ? 'ring-oro-500' : 'ring-gray-200 opacity-40' }}" id="qrPanel"></div>
            <p class="text-xs text-gray-500 mt-3 break-all" id="enlace">{{ $enlace }}</p>
            <div class="mt-3 flex flex-wrap gap-2 justify-center">
                <button type="button" id="copiar" class="inline-flex items-center gap-1.5 text-sm font-semibold border border-gray-300 hover:border-azul-800 text-azul-800 px-3 py-2 rounded-lg"><i data-lucide="copy" class="size-4"></i> Copiar enlace</button>
                <a href="{{ route('autoservicio.afiche', $yo->esAdmin() ? ['cyber' => $cyber->id] : []) }}" target="_blank" class="inline-flex items-center gap-1.5 text-sm font-semibold bg-azul-800 hover:bg-oro-500 text-white px-3 py-2 rounded-lg"><i data-lucide="printer" class="size-4"></i> Afiche para imprimir</a>
            </div>
            <form method="POST" action="{{ route('autoservicio.alternar', $yo->esAdmin() ? ['cyber' => $cyber->id] : []) }}" class="mt-4">@csrf
                <button class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-full {{ $cyber->autoservicio ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                    <span class="size-2.5 rounded-full {{ $cyber->autoservicio ? 'bg-emerald-500 animate-pulse' : 'bg-gray-400' }}"></span>
                    {{ $cyber->autoservicio ? 'Recibiendo datos · tocar para pausar' : 'En pausa · tocar para activar' }}
                </button>
            </form>
            <p class="text-[11px] text-gray-400 mt-3">Los datos que nadie atiende se borran solos a los {{ \App\Models\AutoservicioSolicitud::DIAS_GUARDADO }} días.</p>
        </section>

        <section class="{{ $card }} p-4 sm:p-5 lg:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-azul-950 flex items-center gap-2"><i data-lucide="list-ordered" class="size-[18px] text-oro-500"></i> Turnos de hoy y ayer</h2>
                <span class="text-[11px] text-gray-400 flex items-center gap-1.5"><span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span> Se actualiza sola</span>
            </div>
            <ul id="turnos" class="divide-y divide-gray-100"><li class="py-8 text-center text-gray-400 text-sm">Cargando…</li></ul>
        </section>
    </div>
    @endif

    @if($cyber)
    @push('scripts')
    <script src="{{ asset('vendor/qr-code-styling.min.js') }}"></script>
    <script>
    (function () {
        const ES_ADMIN = @json($yo->esAdmin()), enlace = @json($enlace), token = document.querySelector('meta[name=csrf-token]').content;
        const urlLista = @json(route('autoservicio.lista', $yo->esAdmin() ? ['cyber' => $cyber->id] : []));
        const urlDescartar = @json(route('autoservicio.descartar', '__ID__'));
        new QRCodeStyling({ width: 400, height: 400, type: 'svg', data: enlace, margin: 0, qrOptions: { errorCorrectionLevel: 'Q' },
            dotsOptions: { type: 'rounded', color: '#0B3C74' }, cornersSquareOptions: { type: 'extra-rounded', color: '#0B3C74' }, cornersDotOptions: { type: 'dot', color: '#C9971F' } })
            .getRawData('svg').then(b => b.text()).then(svg => { const q = document.getElementById('qrPanel'); q.innerHTML = svg; q.querySelector('svg').style.cssText = 'width:100%;height:auto;display:block'; });
        document.getElementById('copiar').addEventListener('click', async () => { try { await navigator.clipboard.writeText(enlace); Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Enlace copiado', showConfirmButton: false, timer: 1800 }); } catch (e) {} });

        const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        let conocidos = null;
        function sonar() { try { const a = new AudioContext(), o = a.createOscillator(), g = a.createGain(); o.connect(g); g.connect(a.destination); o.frequency.value = 880; g.gain.setValueAtTime(.15, a.currentTime); g.gain.exponentialRampToValueAtTime(.001, a.currentTime + .5); o.start(); o.stop(a.currentTime + .5); } catch (e) {} }
        async function cargar() {
            try {
                const r = await fetch(urlLista, { headers: { 'Accept': 'application/json' } }); if (!r.ok) return;
                const j = await r.json(), ul = document.getElementById('turnos');
                const ids = new Set(j.filas.filter(f => f.estado === 'pendiente').map(f => f.id));
                if (conocidos && [...ids].some(id => !conocidos.has(id))) sonar();
                conocidos = ids;
                document.title = (j.pendientes ? '(' + j.pendientes + ') ' : '') + 'Autoservicio por QR';
                if (!j.filas.length) { ul.innerHTML = '<li class="py-10 text-center text-gray-400"><div class="text-4xl mb-2">📱</div>Todavía nadie ha enviado datos.<br><span class="text-xs">Cuando alguien escanee el QR y envíe, aparece aquí.</span></li>'; return; }
                ul.innerHTML = j.filas.map(f => `
                    <li class="py-3 flex items-center gap-3 ${f.estado !== 'pendiente' ? 'opacity-50' : ''}">
                        <span class="size-12 shrink-0 rounded-xl ${f.estado === 'pendiente' ? 'bg-azul-800 text-white' : 'bg-gray-100 text-gray-500'} font-black text-xl flex items-center justify-center tabular-nums">${f.turno}</span>
                        <div class="min-w-0 flex-1"><div class="font-semibold text-azul-950 truncate">${esc(f.nombre)}</div>
                            <div class="text-xs text-gray-500"><span class="font-mono">${esc(f.documento)}</span> · ${esc(f.hace)}${f.cyber && ES_ADMIN ? ' · ' + esc(f.cyber) : ''}</div></div>
                        ${f.estado === 'pendiente'
                            ? `<a href="${f.atender}" class="inline-flex items-center gap-1.5 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-3.5 py-2 rounded-lg"><i data-lucide="user-check" class="size-4"></i> Atender</a>
                               <button type="button" data-descartar="${f.id}" title="Descartar" class="size-9 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 flex items-center justify-center"><i data-lucide="x" class="size-4"></i></button>`
                            : (f.ficha ? `<a href="${f.ficha}" class="text-xs font-semibold text-emerald-700">✓ Atendido · ver ficha</a>` : `<span class="text-xs text-gray-400">Descartado</span>`)}
                    </li>`).join('');
                lucide.createIcons();
            } catch (e) {}
        }
        document.getElementById('turnos').addEventListener('click', async e => {
            const b = e.target.closest('[data-descartar]'); if (!b) return;
            const r = await Swal.fire({ title: '¿Descartar este turno?', text: 'Sus datos se borrarán en unos días.', icon: 'question', showCancelButton: true, confirmButtonText: 'Descartar', cancelButtonText: 'No', confirmButtonColor: '#c0392b' });
            if (!r.isConfirmed) return;
            await fetch(urlDescartar.replace('__ID__', b.dataset.descartar), { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } });
            cargar();
        });
        cargar(); setInterval(cargar, 8000);
    })();
    </script>
    @endpush
    @endif
</x-layout>
