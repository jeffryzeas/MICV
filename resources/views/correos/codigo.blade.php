@extends('correos.base')
@php $esRecuperar = ($tipo ?? '') === 'recuperar'; @endphp
@section('preheader'){{ $esRecuperar ? 'Su código para cambiar la contraseña' : 'Su código de verificación' }}: {{ $codigo }} · vence en {{ $minutos }} minutos.@endsection
@section('cuerpo')
<p style="font-size:17px;margin:0 0 10px;color:#111827">Hola <b>{{ $nombre }}</b>,</p>
<p style="margin:0 0 20px;color:#374151">
  {{ $esRecuperar ? 'Recibimos una solicitud para cambiar la contraseña de su cuenta. Escriba este código en la página para continuar:' : '¡Ya casi termina! Escriba este código en la página para verificar su correo y crear su cuenta:' }}
</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center"
    style="background:#f6f8fb;border:2px dashed {{ $colorS }};border-radius:14px;padding:18px 10px">
  <div class="jz-codigo" style="font-family:'Courier New',Courier,monospace;font-size:36px;letter-spacing:10px;font-weight:bold;color:{{ $colorP }}">{{ $codigo }}</div>
  <div style="font-size:12.5px;color:#6b7280;margin-top:6px">Vence en {{ $minutos }} minutos</div>
</td></tr></table>
<p style="font-size:13.5px;color:#6b7280;margin:20px 0 0">🔒 No comparta este código con nadie. El equipo de {{ $marca }} nunca se lo va a pedir por teléfono, mensaje ni redes sociales.</p>
@if($esRecuperar)
<p style="font-size:13.5px;color:#6b7280;margin:10px 0 0">Si usted no pidió cambiar la contraseña, ignore este correo: su cuenta sigue segura.</p>
@else
<p style="font-size:13.5px;color:#6b7280;margin:10px 0 0">Si usted no se registró en {{ $marca }}, ignore este correo: no se creará ninguna cuenta.</p>
@endif
@endsection
