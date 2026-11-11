<!doctype html>
<html lang="es">
<head>@include('exports._style')<title>Cotización</title></head>
<body>
@php $importe = fn ($n) => $datos['simbolo'] . ' ' . number_format($n, $datos['decimales']); @endphp
<header>
    @if($logo)<img src="{{ $logo }}" alt="Logo del negocio">@endif
    <div>
        <span class="meta">{{ $cfg->nombre_comercial ?: $cyberPos->nombre }}</span>
        <h1>Cotización</h1>
        <p class="meta">{{ strtoupper(substr($registro->id, 0, 8)) }} · {{ $registro->updated_at->format('d/m/Y H:i') }} · {{ $datos['moneda'] }}</p>
    </div>
</header>
<h2>{{ $datos['nombre'] }}</h2>
<table>
    <thead><tr><th style="width:38%">Artículo o servicio</th><th class="num">Cantidad</th><th class="num">Unitario</th><th class="num">Descuento</th><th class="num">Total</th></tr></thead>
    <tbody>
        @foreach($datos['lineas'] as $linea)
            <tr>
                <td>{{ $linea['descripcion'] }}</td>
                <td class="num">{{ number_format($linea['cantidad'], 2) }}</td>
                <td class="num">{{ $importe($linea['precio']) }}</td>
                <td class="num">{{ $importe($linea['descuento']) }}</td>
                <td class="num">{{ $importe($linea['total']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
<div class="metrics">
    <div class="metric">Impuesto incluido en el total<b>{{ $importe(array_sum(array_column($datos['lineas'], 'impuesto'))) }}</b></div>
    <div class="metric">Total cotizado<b>{{ $importe($datos['total']) }}</b></div>
</div>
<footer>Cotización informativa sujeta a disponibilidad. El cobro se registra al confirmar la venta.</footer>
</body>
</html>
