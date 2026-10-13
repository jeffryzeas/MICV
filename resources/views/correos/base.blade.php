<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"></head>
<body style="margin:0;background:#f3f5f9;font-family:Arial,Helvetica,sans-serif;color:#1f2937">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f5f9;padding:24px 0"><tr><td align="center">
  <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb">
    <tr><td style="background:{{ $colorP }};padding:22px 28px;color:#fff;font-size:20px;font-weight:bold">
      {{ $marca }}<div style="height:3px;width:48px;background:{{ $colorS }};margin-top:8px;border-radius:2px"></div>
    </td></tr>
    <tr><td style="padding:28px">@yield('cuerpo')</td></tr>
    @php $cfgCorreo = \App\Models\Configuracion::actualSegura(); @endphp
    <tr><td style="padding:16px 28px;background:#f9fafb;color:#9ca3af;font-size:12px">
      Este correo se envió automáticamente desde {{ $marca }}. Si usted no lo solicitó, puede ignorarlo.
      @if($cfgCorreo?->marca_telefono || $cfgCorreo?->marca_correo)<br>Contacto: {{ collect([$cfgCorreo->marca_telefono, $cfgCorreo->marca_correo])->filter()->implode(' · ') }}@endif
      <br><a href="{{ route('legal.ver', 'privacidad') }}" style="color:#9ca3af">Política de privacidad</a>
    </td></tr>
  </table>
</td></tr></table>
</body></html>
