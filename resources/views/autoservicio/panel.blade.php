@php $card = 'bg-white rounded-2xl shadow-sm ring-1 ring-gray-900/5'; $yo = auth()->user(); @endphp
<x-layout titulo="Autoservicio por QR" activo="autoservicio">
    @if($local)
        <p class="text-gray-500 text-sm mb-4">Turnos guardados en este equipo. Para recibir nuevos formularios o administrar el QR, abra la sesión conectada.</p>
    @else
    <p class="text-gray-500 text-sm mb-4">Ponga el afiche en el mostrador. La gente escanea el QR, llena sus datos desde su celular y le da un número de turno. Aquí le aparecen al instante: toque <b>Atender</b>, revise y guarde la ficha.</p>
    @endif

    @if($yo->esAdmin() && $cybers->isNotEmpty())
        <form class="mb-4 flex items-center gap-2 text-sm"><label class="text-gray-500">Negocio:</label>
            <select name="cyber" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2">@foreach($cybers as $cy)<option value="{{ $cy->id }}" @selected($cyber?->id === $cy->id)>{{ $cy->nombre }}</option>@endforeach</select></form>
    @endif

    @if(!$cyber)
        <div class="{{ $card }} p-6 text-gray-500">Primero registre un Negocio.</div>
    @else
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        @unless($local)
        <section class="{{ $card }} p-5 text-center">
            <div class="mx-auto w-52 rounded-2xl ring-4 p-3 {{ $cyber->qrAbierto() ? 'ring-oro-500' : 'ring-gray-200 opacity-40' }}" id="qrPanel"></div>
            <p class="text-xs text-gray-500 mt-3 break-all" id="enlace">{{ $enlace }}</p>
            <div class="mt-3 flex flex-wrap gap-2 justify-center">
                <button type="button" id="copiar" class="inline-flex items-center gap-1.5 text-sm font-semibold border border-gray-300 hover:border-azul-800 text-azul-800 px-3 py-2 rounded-lg"><x-icono name="copy" class="size-4" /> Copiar enlace</button>
                <a href="{{ route('autoservicio.afiche', $yo->esAdmin() ? ['cyber' => $cyber->id] : []) }}" target="_blank" class="inline-flex items-center gap-1.5 text-sm font-semibold bg-azul-800 hover:bg-oro-500 text-white px-3 py-2 rounded-lg"><x-icono name="printer" class="size-4" /> Afiche para imprimir</a>
            </div>
            <div style="padding:16px;margin-top:18px;border:2px solid {{ $cyber->qrAbierto() ? '#1d9664' : '#b54a38' }};border-radius:14px;background:#f6faf8">
            <h3 id="qr-estado" style="font-weight:800;font-size:18px;color:#173e62">{{ $cyber->qrAbierto() ? '● QR ABIERTO' : '● QR CERRADO' }}</h3>
            <p style="font-size:12px;margin:8px 0">@if($cyber->qrAbierto())Cierre automático: <time id="qr-vence" datetime="{{ $cyber->autoservicio_hasta->toIso8601String() }}">{{ $cyber->autoservicio_hasta->format('d/m H:i') }}</time><span id="qr-cuenta"></span>@elseActive el QR durante su jornada. Se cerrará aunque cierre esta pantalla.@endif</p>
            <form method="POST" action="{{ route('autoservicio.alternar', $yo->esAdmin() ? ['cyber'=>$cyber->id] : []) }}">@csrf
            <input type="hidden" name="activar" value="{{ $cyber->qrAbierto() ? '0' : '1' }}">
            @if(!$cyber->qrAbierto())<label style="display:block;font-size:13px;margin-bottom:10px">Abrir durante <select name="horas" style="padding:8px;border:1px solid #b9cbd1;border-radius:8px"><option value="1">1 hora</option><option value="2">2 horas</option><option value="4" selected>4 horas</option><option value="8">8 horas</option></select></label>@endif
            <button style="display:block;width:100%;padding:16px;border-radius:10px;font-weight:800;color:white;background:{{ $cyber->qrAbierto() ? '#b83632' : '#087c58' }}">{{ $cyber->qrAbierto() ? 'CERRAR QR AHORA' : 'ACTIVAR QR' }}</button>
            </form></div>
            @unless(\App\Services\Recaptcha::activo())<p style="color:#b42318;font-size:12px;margin-top:10px">El administrador debe configurar reCAPTCHA para recibir formularios.</p>@endunless
            <p class="text-[11px] text-gray-400 mt-3">Los datos que nadie atiende se borran solos a los {{ \App\Models\AutoservicioSolicitud::DIAS_GUARDADO }} días.</p>
        </section>
        @endunless

        <section class="{{ $card }} p-4 sm:p-5 {{ $local ? 'lg:col-span-3' : 'lg:col-span-2' }}">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-azul-950 flex items-center gap-2"><x-icono name="list-ordered" class="size-[18px] text-oro-500" /> Turnos de hoy y ayer</h2>
                <span class="text-[11px] text-gray-400 flex items-center gap-1.5"><span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span> {{ $local ? 'Copia local' : 'Se actualiza sola' }}</span>
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
        const QR_ABIERTO=@json($cyber->qrAbierto());
        const ES_ADMIN = @json($yo->esAdmin()), enlace = @json($enlace), token = document.querySelector('meta[name=csrf-token]').content;
        const urlLista = @json(route('autoservicio.lista', $yo->esAdmin() ? ['cyber' => $cyber->id] : []));
        const urlDescartar = @json(route('autoservicio.descartar', '__ID__'));
        @unless($local)
        new QRCodeStyling({ width: 400, height: 400, type: 'svg', data: enlace, margin: 0, qrOptions: { errorCorrectionLevel: 'Q' },
            dotsOptions: { type: 'rounded', color: '#0B3C74' }, cornersSquareOptions: { type: 'extra-rounded', color: '#0B3C74' }, cornersDotOptions: { type: 'dot', color: '#C9971F' } })
            .getRawData('svg').then(b => b.text()).then(svg => { const q = document.getElementById('qrPanel'); q.innerHTML = svg; q.querySelector('svg').style.cssText = 'width:100%;height:auto;display:block'; });
        document.getElementById('copiar').addEventListener('click', async () => { try { await navigator.clipboard.writeText(enlace); Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Enlace copiado', showConfirmButton: false, timer: 1800 }); } catch (e) {} });

        @endunless
        const LOCAL = @json($local);
        const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        let conocidos = null;
        function sonar() { try { const a = new AudioContext(), o = a.createOscillator(), g = a.createGain(); o.connect(g); g.connect(a.destination); o.frequency.value = 880; g.gain.setValueAtTime(.15, a.currentTime); g.gain.exponentialRampToValueAtTime(.001, a.currentTime + .5); o.start(); o.stop(a.currentTime + .5); } catch (e) {} }
        async function cargar() {
            try {
                const r = await fetch(urlLista, { headers: { 'Accept': 'application/json' } }); if (!r.ok) return;
                const j = await r.json(); if(!LOCAL&&typeof j.qr_abierto==='boolean'&&j.qr_abierto!==QR_ABIERTO){location.reload();return;} const ul = document.getElementById('turnos');
                const ids = new Set(j.filas.filter(f => f.estado === 'pendiente').map(f => f.id));
                if (conocidos && [...ids].some(id => !conocidos.has(id))) sonar();
                conocidos = ids;
                document.title = (j.pendientes ? '(' + j.pendientes + ') ' : '') + 'Autoservicio por QR';
                if (!j.filas.length) { ul.innerHTML = LOCAL ? '<li class="py-10 text-center text-gray-500">Sin turnos en la copia local.</li>' : '<li class="py-10 text-center text-gray-400"><div class="text-4xl mb-2"><x-icono name="smartphone" class="size-10"/></div>Todavía nadie ha enviado datos.<br><span class="text-xs">Cuando alguien escanee el QR y envíe, aparece aquí.</span></li>'; return; }
                ul.innerHTML = j.filas.map(f => `
                    <li class="py-3 flex items-center gap-3 ${f.estado !== 'pendiente' ? 'opacity-50' : ''}">
                        <span class="size-12 shrink-0 rounded-xl ${f.estado === 'pendiente' ? 'bg-azul-800 text-white' : 'bg-gray-100 text-gray-500'} font-black text-xl flex items-center justify-center tabular-nums">${f.turno}</span>
                        <div class="min-w-0 flex-1"><div class="font-semibold text-azul-950 truncate">${esc(f.nombre)}</div>
                            <div class="text-xs text-gray-500"><span class="font-mono">${esc(f.documento)}</span> · ${esc(f.hace)}${f.cyber && ES_ADMIN ? ' · ' + esc(f.cyber) : ''}</div></div>
                        ${f.estado === 'pendiente'
                            ? `${f.atender ? `<a href="${f.atender}" class="inline-flex items-center gap-1.5 bg-azul-800 hover:bg-oro-500 text-white text-sm font-semibold px-3.5 py-2 rounded-lg"><x-icono name="user-check" class="size-4" /> Atender</a>` : ''}
                               ${f.descartar ? `<button type="button" data-descartar="${f.id}" title="Descartar" class="size-9 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 flex items-center justify-center"><x-icono name="x" class="size-4" /></button>` : ''}`
                            : (f.ficha ? `<a href="${f.ficha}" class="text-xs font-semibold text-emerald-700">Atendido · ver ficha</a>` : `<span class="text-xs text-gray-400">${f.estado === 'atendida' ? 'Atendido' : 'Descartado'}</span>`)}
                    </li>`).join('');
                lucide.createIcons();
            } catch (e) {}
        }
        document.getElementById('turnos').addEventListener('click', async e => {
            const b = e.target.closest('[data-descartar]'); if (!b) return;
            const r = await Swal.fire({ title: '¿Descartar este turno?', text: 'Sus datos se borrarán en unos días.', icon: 'question', showCancelButton: true, confirmButtonText: 'Descartar', cancelButtonText: 'No', confirmButtonColor: '#c0392b' });
            if (!r.isConfirmed) return;
            const respuesta = await fetch(urlDescartar.replace('__ID__', b.dataset.descartar), { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } });
            if (!respuesta.ok) {
                const error = await respuesta.json().catch(() => ({}));
                await Swal.fire({ icon: 'error', text: error.message || 'No se pudo descartar el turno.' });
            }
            cargar();
        });
        const vence=document.getElementById('qr-vence');if(vence){const tick=()=>{const ms=new Date(vence.dateTime)-Date.now();document.getElementById('qr-cuenta').textContent=' · faltan '+Math.max(0,Math.ceil(ms/60000))+' min';if(ms<=0)location.reload();};tick();setInterval(tick,1000);}
        cargar(); if (!LOCAL) setInterval(cargar, 8000);
    })();
    </script>
    @endpush
    @endif
</x-layout>
