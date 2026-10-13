@php
    $ancho = (int) ($cfg->ancho_tiquete ?: 80);
    $util = $ancho === 58 ? 48 : 72; // zona imprimible de las impresoras térmicas
    $f = fn ($n) => $cfg->dinero($n);
    $cant = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
    $nombre = $cfg->nombre_comercial ?: $v->cyber->nombre;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Comprobante {{ $v->numero }}</title>
<style>
@page { size: {{ $ancho }}mm auto; margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
html, body { background: {{ $modo === 'pantalla' ? '#e5e7eb' : '#fff' }}; }
body { font-family: 'Courier New', ui-monospace, monospace; color: #000; font-size: {{ $ancho === 58 ? '10.5px' : '12px' }}; line-height: 1.35; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.tiq { width: {{ $util }}mm; margin: {{ $modo === 'pantalla' ? '16px auto' : '0 auto' }}; padding: {{ $modo === 'imagen' ? '5mm 4mm' : '3mm 0' }}; background: #fff; {{ $modo === 'pantalla' ? 'box-shadow: 0 4px 18px rgba(0,0,0,.15); padding: 5mm 4mm;' : '' }} }
.c { text-align: center; } .r { text-align: right; } .b { font-weight: 700; }
.logo { max-width: 26mm; max-height: 18mm; display: block; margin: 0 auto 2mm; filter: grayscale(1) contrast(1.2); }
.nom { font-size: 1.35em; font-weight: 700; letter-spacing: .02em; }
hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
table { width: 100%; border-collapse: collapse; }
td { vertical-align: top; padding: .3mm 0; }
.item td { padding-top: 1mm; }
.total td { font-size: 1.3em; font-weight: 700; padding-top: 1mm; }
.anulada { border: 2px solid #000; text-align: center; font-weight: 700; font-size: 1.3em; padding: 1mm; margin: 2mm 0; letter-spacing: .2em; }
.peq { font-size: .85em; }
@media print { html, body { background: #fff; } .tiq { margin: 0 auto; box-shadow: none; padding: 2mm 0; } }
</style>
</head>
<body>
<div class="tiq">
    @if($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
    <div class="c">
        <div class="nom">{{ $nombre }}</div>
        @if($cfg->razon_social && $cfg->razon_social !== $nombre)<div>{{ $cfg->razon_social }}</div>@endif
        @if($cfg->id_fiscal)<div>{{ $cfg->id_fiscal_tipo ?: 'ID' }}: {{ $cfg->id_fiscal }}</div>@endif
        @if($cfg->direccion)<div class="peq">{{ $cfg->direccion }}</div>@endif
        @if($cfg->telefono || $cfg->correo)<div class="peq">{{ implode(' · ', array_filter([$cfg->telefono ? 'Tel. ' . $cfg->telefono : null, $cfg->correo])) }}</div>@endif
    </div>
    <hr>
    <table>
        <tr><td>Comprobante</td><td class="r b">{{ $v->numero }}</td></tr>
        <tr><td>Fecha</td><td class="r">{{ $v->created_at->format('d/m/Y h:i a') }}</td></tr>
        <tr><td>Atendió</td><td class="r">{{ \Illuminate\Support\Str::limit($v->usuario?->name ?? '—', 22) }}</td></tr>
        <tr><td>Cliente</td><td class="r">{{ \Illuminate\Support\Str::limit($v->nombreCliente(), 24) }}</td></tr>
        @if($v->cliente?->numero_documento)<tr><td>Doc.</td><td class="r">{{ $v->cliente->documentoFormateado() }}</td></tr>@endif
    </table>
    @if($v->estado === 'anulada')<div class="anulada">ANULADA</div>@endif
    <hr>
    <table>
        @foreach($v->items as $it)
            <tr class="item"><td colspan="2">{{ $it->descripcion }}</td></tr>
            <tr><td class="peq">{{ $cant($it->cantidad) }} × {{ $f($it->precio) }}@if((float) $it->descuento > 0) − {{ $f($it->descuento) }}@endif @if((float) $it->impuesto_tasa == 0 && (float) $cfg->impuesto_tasa > 0)<span>(E)</span>@endif</td><td class="r">{{ $f($it->total) }}</td></tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>Subtotal</td><td class="r">{{ $f($v->subtotal) }}</td></tr>
        @if((float) $v->descuento > 0)<tr><td>Descuento</td><td class="r">−{{ $f($v->descuento) }}</td></tr>@endif
        @if((float) $cfg->impuesto_tasa > 0 || (float) $v->impuesto > 0)
            <tr><td>{{ $cfg->impuesto_nombre }}{{ $cfg->precios_con_impuesto ? ' incluido' : '' }}</td><td class="r">{{ $f($v->impuesto) }}</td></tr>
        @endif
        <tr class="total"><td>TOTAL</td><td class="r">{{ $f($v->total) }}</td></tr>
    </table>
    <hr>
    <table>
        @foreach($v->pagos as $p)<tr><td>{{ $cfg->nombreMetodo($p->metodo) }}@if($p->referencia) <span class="peq">({{ $p->referencia }})</span>@endif</td><td class="r">{{ $f($p->monto) }}</td></tr>@endforeach
        @if((float) $v->cambio > 0)<tr><td class="b">Cambio</td><td class="r b">{{ $f($v->cambio) }}</td></tr>@endif
    </table>
    @if($v->items->contains(fn ($it) => (float) $it->impuesto_tasa == 0) && (float) $cfg->impuesto_tasa > 0)<p class="peq" style="margin-top:1mm">(E) Exento de {{ $cfg->impuesto_nombre }}</p>@endif
    @if($v->notas)<hr><p class="peq">{{ $v->notas }}</p>@endif
    <hr>
    @if($cfg->pie_tiquete)<p class="c b">{{ $cfg->pie_tiquete }}</p>@endif
    @if($cfg->aviso_fiscal)<p class="c peq" style="margin-top:1.5mm">{{ $cfg->aviso_fiscal }}</p>@endif
    <p class="c peq" style="margin-top:1.5mm">{{ $v->items->sum(fn ($i) => (float) $i->cantidad) == 1 ? '1 artículo' : $cant($v->items->sum(fn ($i) => (float) $i->cantidad)) . ' artículos' }}</p>
</div>
<script>
window.tarjetaLista = true;
@if($modo === 'imprimir') window.addEventListener('load', () => setTimeout(() => window.print(), 200)); @endif
</script>
</body>
</html>
