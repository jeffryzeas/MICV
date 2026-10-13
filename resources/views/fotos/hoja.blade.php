<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Fotos</title>
<style>
@page { size: {{ $pw }}mm {{ $ph }}mm; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { width: {{ $pw }}mm; height: {{ $ph }}mm; background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.hoja { position: relative; width: {{ $pw }}mm; height: {{ $ph }}mm; overflow: hidden; }
.f { position: absolute; width: {{ $w }}mm; height: {{ $h }}mm; }
.f img { display: block; width: 100%; height: 100%; object-fit: cover; }
/* Marcas de corte: líneas finas que salen de cada esquina, sin tocar la foto. */
.m { position: absolute; background: #9ca3af; }
.mh { height: .12mm; width: 1.2mm; }
.mv { width: .12mm; height: 1.2mm; }
</style>
</head>
<body>
<div class="hoja">
@foreach($pos as [$x, $y])
    <div class="f" style="left: {{ $x }}mm; top: {{ $y }}mm"><img src="{{ $imagen }}" alt=""></div>
    @if($marcas)
        @foreach([[$x, $y], [$x + $w, $y], [$x, $y + $h], [$x + $w, $y + $h]] as [$cx, $cy])
            <span class="m mh" style="left: {{ $cx === $x ? $cx - 1.4 : $cx + .2 }}mm; top: {{ $cy - .06 }}mm"></span>
            <span class="m mv" style="left: {{ $cx - .06 }}mm; top: {{ $cy === $y ? $cy - 1.4 : $cy + .2 }}mm"></span>
        @endforeach
    @endif
@endforeach
</div>
<script>
Promise.all([...document.images].map(i => i.complete ? null : new Promise(r => { i.onload = i.onerror = r; })))
    .then(() => { window.tarjetaLista = true; });
</script>
</body>
</html>
