@extends('correos.base')
@section('cuerpo')
<p style="font-size:16px;margin:0 0 12px">Hola <b>{{ $nombre }}</b>,</p>
<p style="font-size:15px;margin:0 0 18px">{{ ($tipo ?? '') === 'recuperar' ? 'Recibimos una solicitud para cambiar su contraseña. Su código es:' : 'Su código para verificar la cuenta es:' }}</p>
<div style="font-size:34px;letter-spacing:10px;font-weight:bold;text-align:center;background:#f3f5f9;border:2px dashed {{ $colorS }};border-radius:12px;padding:16px;color:{{ $colorP }}">{{ $codigo }}</div>
<p style="font-size:14px;color:#6b7280;margin:18px 0 0">Vence en {{ $minutos }} minutos. No comparta este código con nadie: el equipo de {{ $marca }} nunca se lo va a pedir.</p>
@if(($tipo ?? '') === 'recuperar')<p style="font-size:13px;color:#6b7280;margin:10px 0 0">Si usted no pidió cambiar la contraseña, ignore este correo: su cuenta sigue segura.</p>@endif
@endsection
