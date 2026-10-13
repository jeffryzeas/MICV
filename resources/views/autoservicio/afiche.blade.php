@php $pri = $cyber->color_primario ?: '#0B3C74'; $sec = $cyber->color_secundario ?: '#C9971F'; @endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
{!! $fuentesCss !!}
@page { size: 215.9mm 279.4mm; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { width: 215.9mm; height: 279.4mm; font-family: 'Montserrat', Arial, sans-serif; color: #0f172a; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.hoja { position: relative; height: 100%; overflow: hidden; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 18mm 16mm 14mm; }
.banda { position: absolute; inset: 0 0 auto 0; height: 92mm; background: {{ $pri }}; }
.banda::after { content: ''; position: absolute; left: -10%; right: -10%; bottom: -26mm; height: 52mm; background: #fff; border-radius: 50%; }
.cab { position: relative; color: #fff; }
.logo { width: 24mm; height: 24mm; border-radius: 6mm; background: #fff; padding: 2mm; object-fit: contain; margin-bottom: 4mm; }
.ini { width: 24mm; height: 24mm; border-radius: 6mm; background: {{ $sec }}; color: {{ $pri }}; font-weight: 800; font-size: 30pt; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 4mm; }
.cab h2 { font-size: 15pt; font-weight: 700; opacity: .9; }
h1 { position: relative; margin-top: 16mm; font-size: 34pt; line-height: 1.05; font-weight: 800; color: {{ $pri }}; }
.sub { margin-top: 4mm; font-size: 14pt; color: #475569; }
.qr { position: relative; margin-top: 10mm; width: 92mm; height: 92mm; padding: 5mm; background: #fff; border-radius: 8mm; box-shadow: 0 0 0 2.2mm {{ $sec }}; }
.qr svg { width: 100%; height: 100%; display: block; }
.pasos { display: flex; gap: 6mm; margin-top: 12mm; }
.paso { width: 52mm; }
.num { width: 11mm; height: 11mm; border-radius: 50%; background: {{ $sec }}; color: #fff; font-weight: 800; font-size: 14pt; display: inline-flex; align-items: center; justify-content: center; }
.paso p { margin-top: 2.5mm; font-size: 11pt; font-weight: 600; color: #1e293b; line-height: 1.3; }
.url { margin-top: auto; font-size: 10pt; color: #64748b; }
</style>
</head>
<body>
<div class="hoja">
    <div class="banda"></div>
    <div class="cab">
        @if($logo)<img class="logo" src="{{ $logo }}" alt="">@else<div class="ini">{{ mb_strtoupper(mb_substr($cyber->nombre, 0, 1)) }}</div>@endif
        <h2>{{ $cyber->nombre }}</h2>
    </div>
    <h1>¿Viene por su CV?<br>Adelante sus datos</h1>
    <p class="sub">Escanee con la cámara de su celular y llénelos mientras espera</p>
    <div class="qr" id="qr"></div>
    <div class="pasos">
        <div class="paso"><span class="num">1</span><p>Abra la cámara y apunte al código</p></div>
        <div class="paso"><span class="num">2</span><p>Llene sus datos y envíelos</p></div>
        <div class="paso"><span class="num">3</span><p>Diga su número de turno en el mostrador</p></div>
    </div>
    <p class="url">{{ $enlace }}</p>
</div>
<script>{!! $qrJs !!}</script>
<script>
(async () => {
    try {
        const qr = new QRCodeStyling({ width: 800, height: 800, type: 'svg', data: @json($enlace), margin: 0,
            qrOptions: { errorCorrectionLevel: 'Q' },
            dotsOptions: { type: 'rounded', color: @json(\App\Support\Color::luminancia($pri) < 0.2 ? $pri : '#0f172a') },
            cornersSquareOptions: { type: 'extra-rounded', color: @json(\App\Support\Color::luminancia($pri) < 0.2 ? $pri : '#0f172a') },
            cornersDotOptions: { type: 'dot', color: '#0f172a' }, backgroundOptions: { color: '#ffffff' } });
        document.getElementById('qr').innerHTML = await (await qr.getRawData('svg')).text();
        if (document.fonts) await document.fonts.ready;
    } catch (e) { console.error(e); }
    window.tarjetaLista = true;
})();
</script>
</body>
</html>
