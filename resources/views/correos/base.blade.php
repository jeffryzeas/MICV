@php
    $cfgCorreo = \App\Models\Configuracion::actualSegura();
    $inicial = mb_strtoupper(mb_substr($marca ?: 'M', 0, 1));
    $logoUrl = ($cfgCorreo && \App\Support\Archivos::existe($cfgCorreo->logo)) ? route('marca.logo') : null;
    $redes = array_filter([
        'Facebook' => $cfgCorreo?->marca_facebook, 'Instagram' => $cfgCorreo?->marca_instagram,
        'TikTok' => $cfgCorreo?->marca_tiktok, 'Sitio web' => $cfgCorreo?->marca_sitio_web,
    ]);
    $contacto = array_filter([$cfgCorreo?->marca_telefono, $cfgCorreo?->marca_whatsapp ? 'WhatsApp ' . $cfgCorreo->marca_whatsapp : null, $cfgCorreo?->marca_correo]);
@endphp
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{{ $marca }}</title>
<style>
  @media only screen and (max-width: 600px) {
    .jz-cont { padding: 22px 18px !important; }
    .jz-cab { padding: 18px !important; }
    .jz-codigo { font-size: 30px !important; letter-spacing: 7px !important; }
  }
  a { color: {{ $colorP }}; }
</style>
</head>
<body style="margin:0;padding:0;background:#eef1f6;font-family:'Segoe UI',Roboto,Arial,Helvetica,sans-serif;color:#1f2937;-webkit-text-size-adjust:100%">
{{-- Texto de vista previa en la bandeja de entrada --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">@yield('preheader')&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef1f6">
  <tr><td align="center" style="padding:28px 12px">
    <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 18px rgba(16,24,40,.08)">
      {{-- Encabezado --}}
      <tr><td class="jz-cab" style="background:{{ $colorP }};padding:22px 28px">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="vertical-align:middle;padding-right:12px">
            @if($logoUrl)
              <img src="{{ $logoUrl }}" width="44" height="44" alt="{{ $marca }}" style="display:block;width:44px;height:44px;border-radius:10px;background:#ffffff;object-fit:contain">
            @else
              <div style="width:44px;height:44px;line-height:44px;text-align:center;border-radius:10px;background:{{ $colorS }};color:{{ $colorP }};font-size:22px;font-weight:bold">{{ $inicial }}</div>
            @endif
          </td>
          <td style="vertical-align:middle">
            <div style="color:#ffffff;font-size:19px;font-weight:bold;line-height:1.2">{{ $marca }}</div>
            @if($cfgCorreo?->eslogan)<div style="color:rgba(255,255,255,.75);font-size:12.5px;margin-top:2px">{{ $cfgCorreo->eslogan }}</div>@endif
          </td>
        </tr></table>
      </td></tr>
      <tr><td style="height:4px;background:{{ $colorS }};line-height:4px;font-size:0">&nbsp;</td></tr>

      {{-- Cuerpo --}}
      <tr><td class="jz-cont" style="padding:30px 32px;font-size:15px;line-height:1.6">@yield('cuerpo')</td></tr>

      {{-- Pie --}}
      <tr><td style="padding:18px 32px 22px;background:#f8fafc;border-top:1px solid #edf0f4;color:#8a94a6;font-size:12px;line-height:1.6">
        @if($contacto)<div style="margin-bottom:6px"><b style="color:#4b5563">¿Necesita ayuda?</b> {{ implode(' · ', $contacto) }}</div>@endif
        @if($redes)
          <div style="margin-bottom:6px">@foreach($redes as $red => $url)<a href="{{ $url }}" style="color:#4b5563;text-decoration:underline;margin-right:10px">{{ $red }}</a>@endforeach</div>
        @endif
        <div>Recibió este correo porque tiene una cuenta o hizo una solicitud en {{ $marca }}. Si no fue usted, puede ignorarlo.</div>
        <div style="margin-top:6px">
          <a href="{{ route('legal.ver', 'privacidad') }}" style="color:#8a94a6">Privacidad</a> ·
          <a href="{{ route('legal.ver', 'terminos') }}" style="color:#8a94a6">Términos</a> ·
          <a href="{{ url('/') }}" style="color:#8a94a6">{{ parse_url(url('/'), PHP_URL_HOST) }}</a>
        </div>
        @if($cfgCorreo?->marca_direccion)<div style="margin-top:6px">{{ $cfgCorreo->marca_direccion }}</div>@endif
      </td></tr>
    </table>
    <div style="color:#a0a8b8;font-size:11px;margin-top:14px">© {{ date('Y') }} {{ $marca }}</div>
  </td></tr>
</table>
</body>
</html>
