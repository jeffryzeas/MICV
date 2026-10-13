@extends('correos.base')
@section('preheader')Su cuenta en {{ $marca }} está lista. Así hace su CV en pocos minutos.@endsection
@section('cuerpo')
<p style="font-size:17px;margin:0 0 12px;color:#111827">¡Bienvenido(a), <b>{{ $nombre }}</b>! 🎉</p>
<p style="margin:0 0 16px;color:#374151">Su cuenta en {{ $marca }} ya está lista. Con estos tres pasos tiene su currículum:</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
  @foreach([['1', 'Llene sus datos', 'Foto, documento, estudios, experiencia e idiomas. Puede ir paso a paso.'], ['2', 'Elija un diseño', 'Hay varios diseños profesionales, en español o en inglés.'], ['3', 'Descárguelo o envíeselo', 'En PDF, listo para imprimir o mandar a las empresas.']] as [$n, $t, $d])
  <tr>
    <td style="width:40px;vertical-align:top;padding:0 12px 14px 0">
      <div style="width:32px;height:32px;line-height:32px;text-align:center;border-radius:50%;background:{{ $colorS }};color:#ffffff;font-weight:bold">{{ $n }}</div>
    </td>
    <td style="vertical-align:top;padding-bottom:14px"><div style="font-weight:bold;color:#111827">{{ $t }}</div><div style="color:#6b7280;font-size:14px">{{ $d }}</div></td>
  </tr>
  @endforeach
</table>
@include('correos._boton', ['texto' => 'Hacer mi CV ahora', 'url' => route('mi-cv.panel')])
<p style="margin:16px 0 0;color:#374151">También puede hacer cartas de presentación, de solicitud de empleo, de renuncia y de recomendación.</p>
<p style="margin:14px 0 0;color:#374151">Saludos,<br><b>Equipo de {{ $marca }}</b></p>
@endsection
