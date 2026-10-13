@extends('correos.base')
@section('preheader')Su currículum en PDF va adjunto, listo para enviar a las empresas.@endsection
@section('cuerpo')
<p style="font-size:17px;margin:0 0 12px;color:#111827">Hola <b>{{ $nombre }}</b>,</p>
<p style="margin:0 0 14px;color:#374151">Aquí tiene {{ $idioma === 'en' ? 'su currículum en inglés' : 'su currículum' }} en PDF. Lo encuentra <b>adjunto a este correo</b>: <span style="color:{{ $colorP }}">📎 {{ $archivo }}</span></p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f6f8fb;border-radius:12px"><tr><td style="padding:16px 18px">
  <div style="font-weight:bold;color:#111827;margin-bottom:6px">Consejos para enviarlo</div>
  <div style="color:#4b5563;font-size:14px;line-height:1.7">
    • Escriba en el asunto el puesto al que aplica, por ejemplo: <i>"Solicitud para el puesto de {{ $puesto ?: 'Cajero(a)' }}"</i>.<br>
    • Agregue un mensaje corto y amable; no envíe el correo vacío.<br>
    • Acompáñelo con una carta de presentación (la hace en {{ $marca }} en un minuto).<br>
    • Revise que su celular y su correo estén bien escritos.
  </div>
</td></tr></table>
@include('correos._boton', ['texto' => 'Abrir ' . $marca, 'url' => url('/')])
<p style="margin:14px 0 0;color:#374151">¡Mucha suerte en su búsqueda de empleo!<br><b>Equipo de {{ $marca }}</b></p>
@endsection
