@php($r=$m['registro'])
<article class="entry">
 <div class="entry-header">
  @if(isset($imagenes[$m['foto']['id']??'']))
  <img class="photo" src="{{ $imagenes[$m['foto']['id']] }}" alt="Foto pública">
  @else
  <div class="photo placeholder">Sin vista</div>
  @endif
  <div class="entry-identity"><strong>{{ $r['nombre']?:'Nombre no informado' }}</strong><small>{{ !empty($r['publicos']['usuario'])?'@'.$r['publicos']['usuario']:$r['autor'] }} · ID {{ $r['autor_id']?:'no entregado' }}</small></div>
  <div class="entry-time">{{ $local($r['ocurrido_ms']) }}<br>{{ $utc($r['ocurrido_ms']) }}</div>
 </div>
 <div class="text">{{ $r['texto'] }}</div>
 <div class="entry-foot">
  @if($r['publicos']['usuario_sala']??null)
  <span>Fuente del chat: {{ '@'.$r['publicos']['usuario_sala'] }} · {{ ($r['publicos']['solo_comentarios']??false)?'Solo comentarios públicos':'Captura pública' }}</span>
  @endif
  <span>Registro local #{{ $m['id'] }} · Evento {{ $r['publicos']['id_evento']??'no registrado' }} · {{ ['tiktok'=>'TikTok','generado_por_adaptador'=>'Generado por adaptador'][$r['publicos']['id_evento_fuente']??'']??'Origen no declarado' }}</span>
  <span>
   @if($m['contexto']['sala']??null)
   Sala {{ $m['contexto']['sala'] }}
   @elseif($m['contexto']['video_id']??null)
   Publicación {{ $m['contexto']['video_id'] }}
   @else
   Sala o publicación no registrada
   @endif
  </span>
  <span>Origen: {{ $r['origen']==='live_publico'?'LIVE público':'Importación autorizada' }}</span>
  <span>Hora del evento: {{ $r['publicos']['fecha_evento_origen']??'no declarada' }} · Idioma: {{ $r['publicos']['idioma_comentario']??'no entregado' }}</span>
  @if($r['publicos']['hora_ajustada_por_validacion']??false)
  <span>Hora normalizada por validación. Valor declarado: {{ $r['publicos']['fecha_declarada_original']??'no registrado' }}</span>
  @endif
  @if(($r['publicos']['texto_recortado']??false)||($r['publicos']['texto_normalizado_por_validacion']??false))
  <span>Texto normalizado o recortado. SHA-256 original: {{ $r['publicos']['sha256_texto_original']??'no recibido' }}</span>
  @endif
  <span>Recepción UTC: {{ $utc($r['capturado_ms']) }}</span>
  <span>Foto: {{ $m['foto']['estado']??'No recibida' }}
   @if($m['foto']['descargado_ms']??null)
   · Descargada {{ $utc($m['foto']['descargado_ms']) }}
   @endif
  </span>
  <span class="hash {{ $m['hash_captura_coincide']===false?'bad':'' }}">SHA-256 de captura: {{ $m['hash_captura']?:'No registrado al capturar; registro anterior incluido en el snapshot de exportación.' }}</span>
  @if($m['hash_captura_coincide']===false)
  <span class="bad">El hash original no coincide con los datos actuales.</span>
  @endif
 </div>
</article>
