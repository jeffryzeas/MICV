<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reporte de ventas — {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; font-size: 11px; color: #1a2332; line-height: 1.4; }
    .page { padding: 28px 32px; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0B3C74; padding-bottom: 14px; margin-bottom: 18px; }
    .header .logo { max-height: 52px; max-width: 140px; object-fit: contain; }
    .header .biz { text-align: right; }
    .header .biz h1 { font-size: 16px; color: #0B3C74; margin-bottom: 2px; }
    .header .biz p { color: #5a6a7a; font-size: 10px; }
    .titulo { font-size: 18px; font-weight: 800; color: #0B3C74; margin-bottom: 4px; }
    .periodo { color: #5a6a7a; margin-bottom: 16px; }
    .cards { display: flex; gap: 10px; margin-bottom: 18px; }
    .card { flex: 1; background: #f4f7fb; border: 1px solid #e1e8f0; border-radius: 8px; padding: 10px 12px; }
    .card .label { font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #5a6a7a; font-weight: 700; }
    .card .value { font-size: 15px; font-weight: 800; color: #0B3C74; margin-top: 2px; }
    h2 { font-size: 12px; font-weight: 800; color: #0B3C74; margin: 16px 0 8px; border-bottom: 1px solid #e1e8f0; padding-bottom: 4px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    th { background: #0B3C74; color: #fff; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: .03em; }
    td { padding: 5px 8px; border-bottom: 1px solid #e8eef4; }
    tr:nth-child(even) td { background: #f8fafc; }
    .right { text-align: right; }
    .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e1e8f0; font-size: 9px; color: #8a96a5; display: flex; justify-content: space-between; }
    .badge { display: inline-block; background: #e8f0fe; color: #0B3C74; font-size: 9px; font-weight: 700; padding: 2px 6px; border-radius: 4px; }
</style>
</head>
<body>
<div class="page">
    <div class="header">
        <div>
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" class="logo" alt="Logo">
            @else
                <div class="titulo" style="margin:0">{{ $nombreNegocio ?? 'Mi CV' }}</div>
            @endif
        </div>
        <div class="biz">
            <h1>{{ $nombreNegocio ?? 'Reporte de ventas' }}</h1>
            <p>{{ $cyberNombre ?? '' }}</p>
            <p>Generado: {{ now()->timezone('America/Costa_Rica')->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    <div class="titulo">Reporte de ventas</div>
    <p class="periodo">Del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</p>

    <div class="cards">
        <div class="card">
            <div class="label">Ventas</div>
            <div class="value">{{ number_format($tot->n) }}</div>
        </div>
        <div class="card">
            <div class="label">Vendido</div>
            <div class="value">{{ $cfg->dinero($tot->total) }}</div>
        </div>
        <div class="card">
            <div class="label">Ticket promedio</div>
            <div class="value">{{ $cfg->dinero($tot->n ? $tot->total / $tot->n : 0) }}</div>
        </div>
        <div class="card">
            <div class="label">Ganancia bruta</div>
            <div class="value">{{ $cfg->dinero($ganancia) }}</div>
        </div>
    </div>

    @if(!empty($porMetodo))
    <h2>Por forma de pago</h2>
    <table>
        <thead><tr><th>Método</th><th class="right">Monto</th></tr></thead>
        <tbody>
            @foreach($porMetodo as $metodo => $monto)
                <tr>
                    <td>{{ $cfg->nombreMetodo($metodo) ?? ucfirst($metodo) }}</td>
                    <td class="right">{{ $cfg->dinero($monto) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <h2>Lo más vendido</h2>
    <table>
        <thead>
            <tr>
                <th>Producto / Servicio</th>
                <th class="right">Cantidad</th>
                <th class="right">Vendido</th>
            </tr>
        </thead>
        <tbody>
            @forelse($top as $t)
                <tr>
                    <td>{{ $t->descripcion }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float)$t->cant, 2, '.', ''), '0'), '.') }}</td>
                    <td class="right">{{ $cfg->dinero($t->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" style="text-align:center;color:#8a96a5">Sin ventas en el período</td></tr>
            @endforelse
        </tbody>
    </table>

    @if(!empty($porUsuario) && $porUsuario->isNotEmpty())
    <h2>Por usuario</h2>
    <table>
        <thead><tr><th>Usuario</th><th class="right">Ventas</th><th class="right">Total</th></tr></thead>
        <tbody>
            @foreach($porUsuario as $u)
                <tr>
                    <td>{{ $u->usuario?->name ?? '—' }}</td>
                    <td class="right">{{ $u->n }}</td>
                    <td class="right">{{ $cfg->dinero($u->total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        <span>Documento interno · No sustituye factura electrónica</span>
        <span>{{ $nombreNegocio ?? 'Mi CV' }} · {{ now()->format('Y') }}</span>
    </div>
</div>
</body>
</html>
