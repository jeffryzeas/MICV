@php
 $zona=$filtros['zona']??\App\Services\TikTokConsulta::zona();
 $local=fn($ms)=>\App\Services\TikTokConsulta::fechaEn($ms,$zona);
 $utc=fn($ms)=>\App\Services\TikTokIntegridad::utc($ms);
 $ref=$exportacion->metadatos['referencia']??null;
@endphp
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Informe técnico · {{ '@'.$cuenta->usuario }}</title>
<style>
@page{size:A4;margin:16mm 15mm 20mm}*{box-sizing:border-box}body{margin:0;color:#202c3b;font:10px Arial,DejaVu Sans,sans-serif;line-height:1.5}h1,h2,h3,p{margin:0}h1{font-size:30px;line-height:1.15;font-weight:700;letter-spacing:-1px;color:#102a46}h2{font-size:15px;color:#143b63;margin:25px 0 11px;padding-bottom:7px;border-bottom:1px solid #dbe2eb}h3{font-size:11px;color:#173c61;margin:13px 0 5px}.kicker{font-size:8px;font-weight:bold;letter-spacing:1.8px;text-transform:uppercase;color:#87661f}.brand{display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #b99343;padding-bottom:13px;margin-bottom:27px}.brand strong{font-size:15px;color:#143b63}.muted{color:#637285}.cover{page-break-after:always}.cover-title{max-width:500px;margin:18px 0 8px}.sub{font-size:13px;color:#556980;margin-bottom:25px}.subject{border:1px solid #dbe2eb;border-left:4px solid #143b63;padding:17px;background:#f7f9fc;margin:23px 0}.subject strong{font-size:22px;color:#143b63}.subject p{margin-top:5px}.meta{display:grid;grid-template-columns:1fr 1fr;gap:15px 24px;margin:20px 0}.meta small,.metric small{display:block;font-size:8px;letter-spacing:.5px;text-transform:uppercase;color:#637285;margin-bottom:4px}.meta div{overflow-wrap:anywhere}.metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:23px 0}.metric{padding:13px;border:1px solid #dbe2eb}.metric strong{display:block;font-size:24px;color:#143b63;line-height:1.2}.notice{padding:12px 14px;border-left:3px solid #b99343;background:#faf8f1;color:#5e563e;font-size:9px;margin:18px 0}.hash{font-family:monospace;font-size:8px;word-break:break-all;line-height:1.6}.integrity{border:1px solid #cfdbe8;padding:14px;margin-top:24px}.section-top{display:flex;justify-content:space-between;align-items:center;color:#60718a;font-size:8px;margin:0 0 20px;border-bottom:1px solid #dbe2eb;padding-bottom:8px}.section-top strong{color:#143b63;font-size:10px}.participant{margin-top:20px}.participant-comments{margin-top:10px}.author{break-after:avoid;background:#f6f8fb}.author{display:flex;gap:11px;border:1px solid #dbe2eb;padding:10px;break-inside:avoid}.photo{width:43px;height:43px;object-fit:cover;flex-shrink:0;border:1px solid #dbe2eb;border-radius:4px}.placeholder{display:flex;align-items:center;justify-content:center;text-align:center;background:#f2f5f9;color:#8290a0;font-size:7px}.author-info{min-width:0;overflow-wrap:anywhere}.author strong{color:#143b63}.author small{display:block;color:#637285;font-size:8px}.author .bio{font-size:8px;margin-top:4px}.timeline{margin-top:13px}.entry{break-inside:avoid;border:1px solid #dbe2eb;border-left:3px solid #b9cadd;padding:12px;margin-bottom:10px}.entry-header{display:flex;gap:10px;align-items:flex-start;margin-bottom:8px}.entry .photo{width:31px;height:31px}.entry-identity{flex:1;min-width:0;overflow-wrap:anywhere}.entry-identity strong{color:#143b63;font-size:11px}.entry-identity small{display:block;font-size:8px;color:#637285}.entry-time{text-align:right;font-size:8px;color:#52667e}.text{white-space:pre-wrap;overflow-wrap:anywhere;font-size:11px;color:#182a3d;padding:5px 0 10px}.entry-foot{padding-top:7px;border-top:1px solid #e8edf3;font-size:7px;color:#637285;display:grid;grid-template-columns:1fr 1fr;gap:4px 12px;overflow-wrap:anywhere}.entry-foot .hash{font-size:7px;grid-column:1/-1}.tag{display:inline-block;padding:2px 6px;background:#edf3f9;border:1px solid #d4e0ec;color:#335774;font-size:7px}.bad{color:#9e3434}.body-text{font-size:9px;line-height:1.7}.body-text p{margin-bottom:8px}.page-break{break-before:page}table{border-collapse:collapse;width:100%;font-size:8px;table-layout:fixed}th{background:#edf3f8;text-align:left;color:#335774;padding:8px}td{border-bottom:1px solid #dbe2eb;padding:8px;vertical-align:top;overflow-wrap:anywhere}tr{break-inside:avoid}thead{display:table-header-group}a{color:#143b63;text-decoration:none}.footer-note{font-size:8px;margin-top:15px;color:#637285}
</style></head><body>
<section class="cover">
 <div class="brand"><strong>Mi CV <span class="muted">/ Registro digital</span></strong><span class="kicker">Informe técnico de captura</span></div>
 @if($exportacion->metadatos['demostracion']??false)<p class="notice"><b>MUESTRA DE DISEÑO · DATOS FICTICIOS.</b> No corresponde a un LIVE real. Las imágenes son siluetas de prueba.</p>@endif
 <p class="kicker">Actividad pública · TikTok LIVE</p>
 <h1 class="cover-title">Registro de comentarios<br>y datos públicos observados</h1>
 <p class="sub">Documentación de adquisición, participantes e integridad de archivos.</p>
 <div class="subject"><p class="kicker">Cuenta monitoreada</p><strong>{{ '@'.$cuenta->usuario }}</strong><p>{{ $live?->titulo?:'Selección de registros archivados' }}</p>@if($live)<p class="muted">Sala TikTok {{ $live->sala }} · LIVE local #{{ $live->id }}</p>@endif</div>
 <div class="meta"><div><small>Referencia / expediente</small>{{ $ref?:'Sin referencia asignada' }}</div><div><small>Identificador del informe</small><span class="hash">{{ $exportacion->id }}</span></div><div><small>Solicitado por</small>{{ $exportacion->metadatos['solicitante']['nombre']??'Administrador autorizado' }}</div><div><small>Preparación del snapshot · UTC</small>{{ $generado }}</div><div><small>Fechas seleccionadas</small>{{ $filtros['desde'] }} — {{ $filtros['hasta'] }}</div><div><small>Zona de visualización</small>{{ $zona }} · AM/PM</div></div>
 <div class="metrics"><div class="metric"><small>Comentarios</small><strong>{{ number_format($resumen['comentarios']) }}</strong></div><div class="metric"><small>Identificadores</small><strong>{{ number_format($resumen['identificadores']) }}</strong></div><div class="metric"><small>Fotos preservadas</small><strong>{{ $resumen['fotos_disponibles'] }}</strong></div><div class="metric"><small>Parte del informe</small><strong>{{ $parte }} / {{ $totalPartes }}</strong></div></div>
 <div class="integrity"><p class="kicker">SHA-256 del snapshot · datos.ndjson</p><p class="hash">{{ $hashDatos }}</p><p class="footer-note">El paquete de evidencia incorpora los PDF, registros estructurados, fotos disponibles, manifiesto de archivos y verificador sin conexión.</p></div>
 <div class="notice">Documento técnico generado a partir de registros recibidos por el sistema. No certifica identidad, autenticidad de una cuenta ni exhaustividad del contenido de TikTok. Las fotos corresponden a datos públicos entregados; no se accede a contenido privado.</div>
</section>
<div class="section-top"><strong>{{ '@'.$cuenta->usuario }} · {{ $live?'Sala '.$live->sala:'Selección archivada' }}</strong><span>INFORME {{ substr($exportacion->id,0,8) }} · PARTE {{ $parte }}/{{ $totalPartes }}</span></div>
<h2>1. Contexto y alcance de la adquisición</h2>
<div class="meta"><div><small>Primer evento seleccionado · hora local</small>{{ $local($resumen['primero_ms']) }}<br><span class="muted">{{ $utc($resumen['primero_ms']) }}</span></div><div><small>Último evento seleccionado · hora local</small>{{ $local($resumen['ultimo_ms']) }}<br><span class="muted">{{ $utc($resumen['ultimo_ms']) }}</span></div><div><small>Primera recepción del capturador · UTC</small>{{ $utc($resumen['primera_captura_ms']) }}</div><div><small>Última recepción seleccionada · UTC</small>{{ $utc($resumen['ultima_captura_ms']) }}</div></div>
<div class="body-text"><p>La captura de eventos de LIVE utiliza la biblioteca no oficial TikTokLive 7.0.1 y el adaptador Mi CV. El registro distingue la hora declarada del evento y la recepción en el capturador. El reloj del VPS no constituye un sello de tiempo de una autoridad externa.</p><p>El conjunto de comentarios queda limitado al identificador local {{ $exportacion->hasta_id }} y a los filtros indicados. Los estados de adjuntos se fijan durante la preparación. Los datos importados se identifican expresamente y no se presentan como recibidos del LIVE.</p>@if(!empty($filtros['q']))<p><b>Búsqueda aplicada:</b> {{ $filtros['q'] }}</p>@endif @if($exportacion->metadatos['observaciones']??null)<p><b>Observaciones del solicitante:</b> {{ $exportacion->metadatos['observaciones'] }}</p>@endif</div>
@if($live?->deteccion_inicio)
<div class="notice"><b>Detección automática.</b> Cuenta consultada: {{ '@'.$live->deteccion_inicio['cuenta_consultada'] }}. Fuente de asociación: {{ $live->deteccion_inicio['fuente_sala'] }}.
@if($live->perfil_inicio['usuario']??null)
Titular de la sala entregado por TikTok: {{ '@'.$live->perfil_inicio['usuario'] }}.
@endif
La cuenta consultada no se identifica automáticamente como titular ni como participante confirmado; los comentarios pertenecen a la sala indicada.</div>
@endif
@if($referenciaSala['anfitrion_declarado']??null)
<div class="notice"><b>Fuente pública observada: {{ '@'.$referenciaSala['cuenta_observada'] }}.</b> Cuenta relacionada por el administrador con {{ '@'.$referenciaSala['anfitrion_declarado'] }} como invitado de una sala compartida. Esta relación no fue verificada por TikTok. Los registros corresponden únicamente a la cuenta y sala observadas; no prueban acceso al chat ni al contenido del otro perfil.</div>
@endif
@if($sesiones)
<h3>Ventanas de conexión registradas</h3><table><thead><tr><th>Inicio · UTC</th><th>Última señal observada · UTC</th><th>Cierre registrado / motivo</th></tr></thead><tbody>@foreach($sesiones as $s)<tr><td>{{ $utc($s['inicio_ms']) }}</td><td>{{ $utc($s['ultimo_ms']) }}</td><td>{{ $s['fin_ms']?$utc($s['fin_ms']):'Abierta al preparar' }}<br>{{ $s['motivo']?:'Sin cierre registrado' }}</td></tr>@endforeach</tbody></table><p class="footer-note">Estas ventanas no demuestran recepción continua de todos los mensajes. Un cierre por reinicio indica cuándo el servicio registró el cierre, no el instante exacto de una interrupción previa.</p>
@else<p class="notice">No existe una bitácora de conexiones para esta selección o captura anterior.</p>@endif
<h2>2. Perfiles y comentarios observados</h2>
<p class="body-text muted">Cada perfil reúne sus comentarios escritos, ordenados por fecha. Se agrupan por ID público cuando está disponible y, en su defecto, por usuario observado. El nombre y la foto pueden cambiar entre eventos; no equivalen a identidad personal verificada.</p>
@if($live && !collect($autores)->contains('es_creador',true))
<p class="notice"><b>Titular de la sala:</b> No hay comentarios escritos del creador identificados en esta parte. La voz de la transmisión no se transcribe ni se considera un comentario escrito.</p>
@endif
@foreach($autores as $a)
<section class="participant">
<div class="author">
@if(isset($imagenes[$a['foto']['id']??'']))
<img class="photo" src="{{ $imagenes[$a['foto']['id']] }}" alt="Foto pública observada">
@else
<div class="photo placeholder">Vista no<br>disponible</div>
@endif
<div class="author-info"><strong>{{ $a['nombre']?:'Nombre no informado' }}</strong><small>{{ '@'.$a['autor'] }} · ID público: {{ $a['id']?:'No entregado' }}</small><small>{{ $a['comentarios'] }} comentarios en esta parte</small>
@if($a['es_creador'])
<span class="tag">Creador del LIVE · coincidencia de datos públicos</span><small>{{ $a['criterio_creador'] }}</small>
@endif
@if($a['bio'])
<p class="bio">{{ $a['bio'] }}</p>
@endif
</div></div>
<div class="participant-comments">
@foreach($a['mensajes'] as $m)
@include('admin.tiktok.reporte-comentario')
@endforeach
</div></section>
@endforeach
<h2 class="page-break">3. Índice cronológico general</h2><p class="body-text muted">Referencia cruzada de los comentarios detallados bajo cada perfil. Orden por fecha de evento y registro local.</p>
<table><thead><tr><th style="width:34%">Fecha y hora local · {{ $zona }}</th><th>Usuario observado</th><th style="width:18%">Registro local</th></tr></thead><tbody>
@foreach($filas as $m)
<tr><td>{{ $local($m['registro']['ocurrido_ms']) }}</td><td>{{ '@'.($m['registro']['publicos']['usuario']??$m['registro']['autor']) }}</td><td>#{{ $m['id'] }}</td></tr>
@endforeach
</tbody></table>
<h2>4. Integridad, adjuntos y limitaciones</h2><div class="body-text"><p><b>Registros con hash de captura coincidente:</b> {{ $resumen['sellados'] }}. <b>Sin hash original:</b> {{ $resumen['sin_sello_original'] }}. <b>Hashes no coincidentes:</b> {{ $resumen['hash_no_coincide'] }}.</p><p><b>Fotos disponibles:</b> {{ $resumen['fotos_disponibles'] }}. <b>Pendientes:</b> {{ $resumen['fotos_pendientes'] }}. <b>No disponibles:</b> {{ $resumen['fotos_no_disponibles'] }}. El manifiesto identifica cada archivo con tamaño y SHA-256. Las vistas del PDF pueden ser miniaturas derivadas; los originales disponibles se incluyen sin modificar en el paquete.</p><p>La foto puede descargarse después del comentario. La privacidad del perfil no se utiliza para descartar comentarios públicos. La identidad, el domicilio, la nacionalidad y la titularidad real de las cuentas no se verifican ni se infieren.</p><p>Interrupciones, límites de TikTok, cambios de protocolo o fallos de red pueden provocar pérdidas. Los hashes permiten comparar archivos y detectar cambios respecto del manifiesto, pero no sustituyen una firma de TikTok, una cadena de custodia externa o una pericia acreditada. El video se reproduce en directo; este módulo archiva comentarios y metadatos.</p></div><div class="integrity"><span class="kicker">Verificación sin conexión</span><p>Extraiga el paquete completo y ejecute <b>python3 verificar.py</b>.</p><p class="hash">Snapshot SHA-256: {{ $hashDatos }}</p></div>
</body></html>
