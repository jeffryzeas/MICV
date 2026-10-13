@php $pantalla = $modoPantalla ?? false; @endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $tituloTipo }} - {{ $cliente->nombreCompleto() }}</title>
<style>
    @page { size: 8.5in 11in; margin: 25mm 25mm 22mm; }
    * { box-sizing: border-box; }
    html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body { margin: 0; font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif; font-size: 12.5pt; color: #000; line-height: 1.6; text-align: justify; }
    .titulo { text-align: center; font-weight: bold; text-decoration: underline; text-transform: uppercase; margin-bottom: 22pt; letter-spacing: 0.5pt; }
    .fecha { text-align: right; font-style: italic; margin-bottom: 24pt; }
    p { margin: 0 0 12pt; }
    .cierre { margin-top: 18pt; }
    .firma-bloque { margin-top: 48pt; text-align: center; break-inside: avoid; }
    .firma-bloque .linea { border-top: 1pt solid #000; width: 240pt; margin: 0 auto 4pt auto; }
    .firma-bloque .contacto, .firma-bloque .cedula-blanco { font-size: 11pt; color: #333; }
    @if ($pantalla)
    html { background: #525659; }
    body { padding: 24px 0; }
    .hoja { width: 8.5in; min-height: 11in; margin: 0 auto; background: #fff; padding: 25mm; box-shadow: 0 4px 18px rgba(0,0,0,.35); }
    @media (max-width: 860px) { body { zoom: .45; } }
    @endif
</style>
</head>
<body>
<div class="hoja">
    <div class="titulo">{{ $tituloTipo }}</div>
    <div class="fecha">{{ $fechaTexto }}</div>

    {!! $cuerpo !!}

    <p class="cierre">Atentamente,</p>

    @if($firmaCliente)
    @php
        $tel = $cliente->telefono ? trim(($cliente->telefono_codigo_pais ? $cliente->telefono_codigo_pais . ' ' : '') . $cliente->telefono) : null;
        $contacto = array_filter([$tel ? 'Celular: ' . $tel : null, $cliente->correo ? 'Correo: ' . $cliente->correo : null]);
    @endphp
    <div class="firma-bloque">
        <div class="linea"></div>
        <strong>{{ $cliente->nombreCompleto() }}</strong><br>
        {{ $cliente->tipoDocumentoLegible() }}: {{ $cliente->documentoFormateado() ?: '____________________' }}
        @if($contacto)<div class="contacto">{{ implode(' · ', $contacto) }}</div>@endif
    </div>
    @elseif($firmante)
    <div class="firma-bloque">
        <div class="linea"></div>
        @if($firmante['nombre'])<strong>{{ $firmante['nombre'] }}</strong><br>@else<span class="cedula-blanco">Nombre: ____________________________________</span><br>@endif
        @if($firmante['cedula']){{ $firmante['doc'] ?? 'Cédula' }}: {{ $firmante['cedula'] }}<br>@else<span class="cedula-blanco">Cédula: ____________________________________</span><br>@endif
        <div class="contacto">
            Celular: {{ $firmante['celular'] ?: '____________________' }}
            @if($firmante['correo']) &nbsp;·&nbsp; Correo: {{ $firmante['correo'] }}@endif
        </div>
    </div>
    @endif
</div>
</body>
</html>
