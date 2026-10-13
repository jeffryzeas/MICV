@extends('correos.base')
@section('cuerpo')
<p style="font-size:16px;margin:0 0 12px">¡Funciona! ✅</p>
<p style="font-size:15px;margin:0">El correo de <b>{{ $marca }}</b> quedó bien configurado. Desde aquí saldrán los códigos de verificación.</p>
<p style="font-size:13px;color:#6b7280;margin-top:16px">Enviado el {{ now()->format('d/m/Y h:i A') }}.</p>
@endsection
