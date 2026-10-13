@php
    $W = \App\Services\TarjetaRender::ANCHO_MM; $H = \App\Services\TarjetaRender::ALTO_MM;
    $hoja = $modo === 'hoja';
    $copias = $hoja ? 10 : 1;
    $conQr = filled($qrTexto);
    $nombre = $t->d('nombre') ?: 'Su nombre';
    $iniciales = collect(preg_split('/\s+/', $nombre))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tarjeta — {{ $nombre }}</title>
<style>
{!! $fuentesCss !!}
@page { size: {{ $hoja ? '8.5in 11in' : $W . 'mm ' . $H . 'mm' }}; margin: 0; }
* { box-sizing: border-box; margin: 0; padding: 0; }
html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { font-family: 'CvSans', 'Montserrat', Arial, sans-serif; background: {{ $modo === 'pantalla' ? '#e9edf3' : 'transparent' }}; }
@if ($modo === 'pantalla')
body { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 18px; }
.escena { zoom: var(--zoom, 2.4); filter: drop-shadow(0 1.2mm 2.2mm rgba(15, 23, 42, .28)); }
@endif

/* ── Tarjeta ───────────────────────────────────────────── */
.tj { --p: {{ $col['p'] }}; --s: {{ $col['s'] }}; --f: {{ $col['f'] }}; --t: {{ $col['t'] }}; --tp: {{ $textoP }}; --ts: {{ $textoS }};
      position: relative; width: {{ $W }}mm; height: {{ $H }}mm; overflow: hidden; background: var(--f); color: var(--t);
      border-radius: {{ $hoja || $modo === 'pdf' ? '0' : '3.2mm' }}; }
.tj svg { display: block; }
.ico svg, .con svg { width: 100%; height: 100%; }
.agua { position: absolute; width: 30mm; height: 30mm; opacity: .07; color: var(--p); pointer-events: none; }
.cab { display: flex; align-items: center; gap: 2.4mm; }
.ava { flex-shrink: 0; width: 13mm; height: 13mm; border-radius: 50%; overflow: hidden; background: var(--p); color: var(--tp);
       display: flex; align-items: center; justify-content: center; border: .6mm solid var(--s); }
.ava img { width: 100%; height: 100%; object-fit: cover; }
.ava.logo { border-radius: 2.2mm; background: #fff; border-width: .35mm; }
.ava.logo img { object-fit: contain; padding: .6mm; }
.ava .ini { font-weight: 800; font-size: 13pt; letter-spacing: .02em; }
.ava .ico { width: 6.5mm; height: 6.5mm; }
.nom { font-weight: 800; font-size: 10.8pt; line-height: 1.05; letter-spacing: -.01em; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.car { margin-top: .9mm; font-size: 5.4pt; font-weight: 700; letter-spacing: .1em; line-height: 1.25; text-transform: uppercase; color: var(--p); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.con { list-style: none; display: grid; gap: 1.05mm; }
.con li { display: flex; align-items: center; gap: 1.5mm; font-size: 6.1pt; line-height: 1.15; min-width: 0; }
.con li span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.con .i { flex-shrink: 0; width: 3.6mm; height: 3.6mm; border-radius: 50%; background: var(--p); color: var(--tp); padding: .7mm; }
.frase { font-size: 5.4pt; font-style: italic; opacity: .85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.qrz { position: absolute; text-align: center; }
.qrbox { width: 19mm; height: 19mm; background: #fff; border-radius: 1.8mm; padding: 1.1mm; box-shadow: 0 .3mm 1.2mm rgba(0,0,0,.18); }
.qr, .qr svg { width: 100%; height: 100%; }
.qrz small { display: block; margin-top: .8mm; font-size: 4.6pt; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
.trail { position: absolute; display: flex; gap: 1.6mm; }
.trail .ico { width: 3.8mm; height: 3.8mm; }

/* ── 1. Franja lateral ─────────────────────────────────── */
.l-lateral .franja { position: absolute; left: 0; top: 0; bottom: 0; width: 9mm; background: var(--p); }
.l-lateral .franja::after { content: ""; position: absolute; right: -1.2mm; top: 0; bottom: 0; width: 1.2mm; background: var(--s); }
.l-lateral .trail { left: 0; top: 0; bottom: 0; width: 9mm; flex-direction: column; justify-content: center; align-items: center; gap: 4mm; color: var(--tp); }
.l-lateral .trail .ico { width: 4.4mm; height: 4.4mm; opacity: .95; }
.l-lateral .cuerpo { position: absolute; left: 14mm; right: {{ $conQr ? '26mm' : '5mm' }}; top: 5.2mm; bottom: 4.5mm; display: flex; flex-direction: column; justify-content: space-between; }
.l-lateral .qrz { right: 4.5mm; bottom: 5mm; } .l-lateral .qrz small { color: var(--p); }
.l-lateral .agua { right: -6mm; top: -7mm; }
.l-lateral .linea { height: .5mm; width: 9mm; background: var(--s); border-radius: 1mm; margin: 1.6mm 0 0; }

/* ── 2. Encabezado de color ───────────────────────────── */
.l-superior .banda { position: absolute; left: 0; right: 0; top: 0; height: 20mm; background: var(--p); }
.l-superior .banda::after { content: ""; position: absolute; left: 0; right: 0; bottom: -1mm; height: 1mm; background: var(--s); }
.l-superior .cab { position: absolute; left: 5mm; right: {{ $conQr ? '27mm' : '5mm' }}; top: 3.6mm; color: var(--tp); }
.l-superior .car { color: var(--tp); opacity: .85; }
.l-superior .ava { border-color: #fff; }
.l-superior .con { position: absolute; left: 5mm; right: {{ $conQr ? '27mm' : '5mm' }}; top: 24.5mm; }
.l-superior .frase { position: absolute; left: 5mm; right: 27mm; bottom: 3.6mm; }
.l-superior .qrz { right: 4.5mm; top: 9.5mm; } .l-superior .qrz small { color: var(--p); }
.l-superior .trail { right: 5mm; top: 3.4mm; color: var(--tp); opacity: .55; {{ $conQr ? 'display:none;' : '' }} }
.l-superior .agua { left: -5mm; bottom: -9mm; width: 26mm; height: 26mm; }

/* ── 3. Corte diagonal ─────────────────────────────────── */
.l-diagonal .corte { position: absolute; inset: 0; background: var(--p); clip-path: polygon(64% 0, 100% 0, 100% 100%, 50% 100%); }
.l-diagonal .corte2 { position: absolute; inset: 0; background: var(--s); clip-path: polygon(61.5% 0, 64% 0, 50% 100%, 47.5% 100%); }
.l-diagonal .cuerpo { position: absolute; left: 5mm; right: 40mm; top: 5mm; bottom: 4.5mm; display: flex; flex-direction: column; justify-content: space-between; }
.l-diagonal .qrz { right: 5mm; top: 7mm; } .l-diagonal .qrz small { color: var(--tp); }
.l-diagonal .trail { right: 5mm; bottom: 4.2mm; color: var(--tp); }
.l-diagonal .agua { right: 9mm; bottom: -8mm; color: var(--tp); opacity: .12; }
@if (!$conQr) .l-diagonal .cuerpo { right: 34mm; } .l-diagonal .agua { width: 26mm; height: 26mm; right: 5mm; top: 12mm; opacity: .25; } @endif

/* ── 4. Elegante con marco ─────────────────────────────── */
.l-elegante { color: #fff; }
.l-elegante .marco { position: absolute; inset: 2.6mm; border: .3mm solid var(--s); border-radius: 1.6mm; }
.l-elegante .marco::before, .l-elegante .marco::after { content: ""; position: absolute; width: 4mm; height: 4mm; border: .6mm solid var(--s); }
.l-elegante .marco::before { left: -1mm; top: -1mm; border-right: 0; border-bottom: 0; }
.l-elegante .marco::after { right: -1mm; bottom: -1mm; border-left: 0; border-top: 0; }
.l-elegante .cuerpo { position: absolute; left: 7mm; right: {{ $conQr ? '29mm' : '7mm' }}; top: 6.2mm; bottom: 6mm; display: flex; flex-direction: column; justify-content: space-between; }
.l-elegante .nom { font-family: 'CvSerif', Georgia, serif; font-weight: 700; font-size: 11.5pt; color: #fff; }
.l-elegante .car { color: var(--s); }
.l-elegante .ava { border-color: var(--s); background: rgba(255,255,255,.08); color: var(--s); }
.l-elegante .con .i { background: transparent; color: var(--s); border: .25mm solid var(--s); }
.l-elegante .qrz { right: 7mm; top: 50%; transform: translateY(-52%); } .l-elegante .qrz small { color: var(--s); }
.l-elegante .qrbox { box-shadow: 0 0 0 .4mm var(--s); }
.l-elegante .agua { color: var(--s); opacity: .08; left: 22mm; top: 10mm; width: 36mm; height: 36mm; }
.l-elegante .trail { display: none; }

/* ── 5. Oscuro moderno ─────────────────────────────────── */
.l-oscuro .brillo { position: absolute; width: 60mm; height: 60mm; right: -22mm; top: -26mm; border-radius: 50%; background: radial-gradient(circle, var(--p) 0%, transparent 65%); opacity: .35; }
.l-oscuro .puntos { position: absolute; inset: 0; background-image: radial-gradient(rgba(255,255,255,.09) .25mm, transparent .3mm); background-size: 2.6mm 2.6mm; }
.l-oscuro .barra { position: absolute; left: 0; top: 0; bottom: 0; width: 1.3mm; background: linear-gradient(var(--p), var(--s)); }
.l-oscuro .cuerpo { position: absolute; left: 6mm; right: {{ $conQr ? '27mm' : '6mm' }}; top: 5.2mm; bottom: 4.6mm; display: flex; flex-direction: column; justify-content: space-between; }
.l-oscuro .nom { color: #fff; }
.l-oscuro .ava { background: rgba(255,255,255,.06); color: var(--p); border-color: var(--p); box-shadow: 0 0 2.5mm color-mix(in srgb, var(--p) 60%, transparent); }
.l-oscuro .con .i { background: color-mix(in srgb, var(--p) 22%, transparent); color: var(--p); }
.l-oscuro .qrz { right: 4.6mm; bottom: 5mm; } .l-oscuro .qrz small { color: var(--p); }
.l-oscuro .qrbox { box-shadow: 0 0 0 .35mm var(--p), 0 0 3mm color-mix(in srgb, var(--p) 55%, transparent); }
.l-oscuro .trail { right: 5mm; top: 4.6mm; color: var(--s); {{ $conQr ? '' : 'top:auto;bottom:4.6mm;' }} }
.l-oscuro .agua { color: var(--p); opacity: .06; left: 30mm; top: 14mm; width: 34mm; height: 34mm; }

/* ── 6. Foto grande ────────────────────────────────────── */
.l-foto .mancha { position: absolute; left: -14mm; top: -10mm; width: 48mm; height: 74mm; border-radius: 50%; background: var(--p); }
.l-foto .mancha2 { position: absolute; left: -16mm; top: -12mm; width: 52mm; height: 78mm; border-radius: 50%; border: .7mm solid var(--s); }
.l-foto .ava { position: absolute; left: 4mm; top: 50%; transform: translateY(-50%); width: 23mm; height: 23mm; border-width: .9mm; border-color: #fff; box-shadow: 0 .6mm 2mm rgba(0,0,0,.25); }
.l-foto .ava .ini { font-size: 20pt; } .l-foto .ava .ico { width: 11mm; height: 11mm; }
.l-foto .cuerpo { position: absolute; left: 36.5mm; right: {{ $conQr ? '19.5mm' : '5mm' }}; top: 5mm; bottom: 4.6mm; display: flex; flex-direction: column; justify-content: space-between; }
.l-foto .qrz { right: 3.6mm; bottom: 4.4mm; } .l-foto .qrbox { width: 14.5mm; height: 14.5mm; padding: .8mm; } .l-foto .qrz small { color: var(--p); font-size: 4pt; }
.l-foto .trail { right: 4mm; top: 4.4mm; color: var(--p); opacity: .8; }
.l-foto .agua { right: -8mm; top: 8mm; }

/* ── Hoja carta con 10 tarjetas y guías de corte ───────── */
@if ($hoja)
.hoja { position: relative; width: 8.5in; height: 11in; background: #fff; }
.rejilla { position: absolute; left: calc((8.5in - {{ 2 * $W }}mm) / 2); top: calc((11in - {{ 5 * $H }}mm) / 2); display: grid; grid-template-columns: repeat(2, {{ $W }}mm); grid-auto-rows: {{ $H }}mm; }
.rejilla .tj { outline: .12mm dashed rgba(120,120,120,.55); outline-offset: -.06mm; }
.guia { position: absolute; background: #6b7280; }
.pie-hoja { position: absolute; left: 0; right: 0; bottom: .8mm; text-align: center; font-size: 5.5pt; color: #9ca3af; }
@endif
</style>
</head>
<body>
@php
    $tarjetaHtml = function () use ($t, $layout, $imagen, $iconos, $contactos, $nombre, $iniciales, $conQr, $I) {
        ob_start(); ?>
<div class="tj l-<?= e($layout) ?>">
    <?php if ($layout === 'lateral'): ?><div class="franja"></div>
    <?php elseif ($layout === 'superior'): ?><div class="banda"></div>
    <?php elseif ($layout === 'diagonal'): ?><div class="corte"></div><div class="corte2"></div>
    <?php elseif ($layout === 'elegante'): ?><div class="marco"></div>
    <?php elseif ($layout === 'oscuro'): ?><div class="puntos"></div><div class="brillo"></div><div class="barra"></div>
    <?php elseif ($layout === 'foto'): ?><div class="mancha2"></div><div class="mancha"></div><?php endif; ?>
    <div class="agua ico"><?= $iconos[0] ?></div>
    <div class="trail"><?php foreach ($iconos as $ic): ?><span class="ico"><?= $ic ?></span><?php endforeach; ?></div>

    <?php $ava = '<div class="ava' . ($t->imagen === 'logo' && $imagen ? ' logo' : '') . '">'
        . ($imagen ? '<img src="' . e($imagen) . '" alt="">' : ($t->imagen === 'ninguna' ? '<span class="ico">' . $iconos[0] . '</span>' : '<span class="ini">' . e($iniciales) . '</span>'))
        . '</div>'; ?>

    <?php if ($layout === 'superior'): ?>
        <div class="cab"><?= $ava ?><div style="min-width:0"><div class="nom"><?= e($nombre) ?></div><?php if ($t->d('cargo')): ?><div class="car"><?= e($t->d('cargo')) ?></div><?php endif; ?></div></div>
        <ul class="con"><?php foreach ($contactos as [$ic, $tx]): ?><li><span class="i"><?= $I($ic) ?></span><span><?= e($tx) ?></span></li><?php endforeach; ?></ul>
        <?php if ($t->d('frase')): ?><div class="frase">“<?= e($t->d('frase')) ?>”</div><?php endif; ?>
    <?php elseif ($layout === 'foto'): ?>
        <?= $ava ?>
        <div class="cuerpo">
            <div><div class="nom"><?= e($nombre) ?></div><?php if ($t->d('cargo')): ?><div class="car"><?= e($t->d('cargo')) ?></div><?php endif; ?></div>
            <ul class="con"><?php foreach ($contactos as [$ic, $tx]): ?><li><span class="i"><?= $I($ic) ?></span><span><?= e($tx) ?></span></li><?php endforeach; ?></ul>
            <?php if ($t->d('frase')): ?><div class="frase">“<?= e($t->d('frase')) ?>”</div><?php else: ?><div></div><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="cuerpo">
            <div>
                <div class="cab"><?= $ava ?><div style="min-width:0"><div class="nom"><?= e($nombre) ?></div><?php if ($t->d('cargo')): ?><div class="car"><?= e($t->d('cargo')) ?></div><?php endif; ?></div></div>
                <?php if ($layout === 'lateral'): ?><div class="linea"></div><?php endif; ?>
            </div>
            <ul class="con"><?php foreach ($contactos as [$ic, $tx]): ?><li><span class="i"><?= $I($ic) ?></span><span><?= e($tx) ?></span></li><?php endforeach; ?></ul>
            <?php if ($t->d('frase')): ?><div class="frase">“<?= e($t->d('frase')) ?>”</div><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($conQr): ?><div class="qrz"><div class="qrbox"><div class="qr"></div></div><small><?= $t->qr === 'whatsapp' ? 'WhatsApp' : ($t->qr === 'contacto' ? 'Guardar contacto' : 'Escanéame') ?></small></div><?php endif; ?>
</div>
<?php   return ob_get_clean();
    };
@endphp

@if ($hoja)
    <div class="hoja">
        <div class="rejilla">@for ($i = 0; $i < $copias; $i++){!! $tarjetaHtml() !!}@endfor</div>
        @php
            $x0 = '((8.5in - ' . (2 * $W) . 'mm) / 2)'; $y0 = '((11in - ' . (5 * $H) . 'mm) / 2)';
        @endphp
        {{-- Marcas de corte en los márgenes --}}
        @for ($r = 0; $r <= 5; $r++)
            <div class="guia" style="left:0; width:calc({{ $x0 }} - 1.5mm); top:calc({{ $y0 }} + {{ $r * $H }}mm); height:.15mm"></div>
            <div class="guia" style="right:0; width:calc({{ $x0 }} - 1.5mm); top:calc({{ $y0 }} + {{ $r * $H }}mm); height:.15mm"></div>
        @endfor
        @for ($c2 = 0; $c2 <= 2; $c2++)
            <div class="guia" style="top:0; height:calc({{ $y0 }} - .8mm); left:calc({{ $x0 }} + {{ $c2 * $W }}mm); width:.15mm"></div>
            <div class="guia" style="bottom:0; height:calc({{ $y0 }} - .8mm); left:calc({{ $x0 }} + {{ $c2 * $W }}mm); width:.15mm"></div>
        @endfor
    </div>
@else
    <div class="escena">{!! $tarjetaHtml() !!}</div>
@endif

<script>{!! $qrJs !!}</script>
<script>
(async function () {
    try {
        @if ($modo === 'pantalla')
        const ajustar = () => { const z = Math.min((innerWidth - 36) / 324, (innerHeight - 36) / 204, 3.2); document.documentElement.style.setProperty('--zoom', Math.max(z, .8)); };
        ajustar(); addEventListener('resize', ajustar);
        @endif
        const datos = @json($qrTexto);
        if (datos && window.QRCodeStyling) {
            const qr = new QRCodeStyling({
                width: 600, height: 600, type: 'svg', data: datos, margin: 0,
                qrOptions: { errorCorrectionLevel: @json($qrCentro ? 'H' : 'Q') },
                @if ($qrCentro) image: @json($qrCentro), imageOptions: { margin: 6, imageSize: 0.3, hideBackgroundDots: true }, @endif
                dotsOptions: { type: 'rounded', gradient: { type: 'linear', rotation: 0.785, colorStops: [{ offset: 0, color: @json($qrColor) }, { offset: 1, color: @json($qrColor2) }] } },
                cornersSquareOptions: { type: 'extra-rounded', color: @json($qrColor) },
                cornersDotOptions: { type: 'dot', color: @json($col['s'] && \App\Support\Color::luminancia($col['s']) < 0.12 ? $col['s'] : $qrColor) },
                backgroundOptions: { color: '#ffffff' },
            });
            const blob = await qr.getRawData('svg');
            const svg = await blob.text();
            document.querySelectorAll('.qr').forEach(el => { el.innerHTML = svg; });
        }
        if (document.fonts && document.fonts.ready) await document.fonts.ready;
        await Promise.all([...document.images].map(i => i.complete ? null : new Promise(r => { i.onload = i.onerror = r; })));
    } catch (e) { console.error(e); }
    window.tarjetaLista = true;
})();
</script>
</body>
</html>
