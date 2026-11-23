<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $p->asunto }}</title></head>
<body style="margin:0;padding:0;background:#f3f5f7;color:#263445;">
<!-- micv-postulacion-formal-v2 -->
@php
    $texto = str_replace(["\r\n", "\r"], "\n", trim((string) $p->mensaje));
    $parrafos = preg_split('/\n[\t ]*\n+/', $texto, -1, PREG_SPLIT_NO_EMPTY);
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr><td style="padding:24px 12px;">
<table role="presentation" align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width:660px;margin:0 auto;border-collapse:collapse;background:#ffffff;border:1px solid #e1e6eb;">
<tr><td style="padding:32px 28px 20px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.75;text-align:left;overflow-wrap:anywhere;word-wrap:break-word;">
@foreach($parrafos as $parrafo)
<p style="margin:0 0 18px;">{!! nl2br(e($parrafo), false) !!}</p>
@endforeach
</td></tr>
<tr><td style="padding:20px 28px 24px;border-top:1px solid #e6eaef;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.65;color:#687586;overflow-wrap:anywhere;">
<p style="margin:0 0 8px;"><strong style="font-weight:600;color:#536172;">Aviso de privacidad</strong></p>
<p style="margin:0 0 8px;">Este correo fue enviado a <strong>{{ $p->destinatario }}</strong>@if($p->empresa), como dirección de contacto de {{ $p->empresa }}@endif, para presentar una solicitud de empleo. El mensaje y el currículum adjunto contienen información personal destinada exclusivamente al proceso de selección.</p>
<p style="margin:0;">Si usted no es el destinatario o recibió este correo por error, por favor elimine el mensaje y sus archivos adjuntos. Le solicitamos no compartir, copiar ni utilizar su contenido.</p>
</td></tr></table>
</td></tr></table>
</body></html>
