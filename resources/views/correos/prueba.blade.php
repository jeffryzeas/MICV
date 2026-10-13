@extends('correos.base')
@section('preheader')El correo de {{ $marca }} quedó bien configurado.@endsection
@section('cuerpo')
<p style="font-size:22px;margin:0 0 8px">✅</p>
<p style="font-size:17px;margin:0 0 10px;color:#111827"><b>¡El correo funciona!</b></p>
<p style="margin:0;color:#374151">El correo de <b>{{ $marca }}</b> quedó bien configurado. Desde aquí saldrán los códigos de verificación, los avisos y los CV que las personas se envíen.</p>
<p style="font-size:13px;color:#6b7280;margin-top:16px">Enviado el {{ now()->format('d/m/Y h:i A') }}.</p>
@endsection
