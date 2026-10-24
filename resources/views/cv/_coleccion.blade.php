@php
$lat=$paleta['lateral'];$ac=$paleta['acento'];$suave=$paleta['suave'];
@endphp
<!doctype html><html lang="{{ $lang }}"><head><meta charset="utf-8"><title>CV — {{ $c->nombreCompleto() }}</title>
@include('cv._base-pro')
<style>
.fit{padding:.43in .48in .36in}.cab{display:flex;align-items:center;gap:.24in;padding-bottom:.2in;border-bottom:3px solid {{ $lat }}}.cab .txt{flex:1;min-width:0}.nombre{font-size:26pt;font-weight:800;color:{{ $lat }};line-height:1.1;overflow-wrap:anywhere}.puesto{font-size:10.5pt;color:{{ $ac }};font-weight:700;margin-top:7px}.contactos{font-size:8pt;line-height:1.6;color:#475569;margin-top:9px;overflow-wrap:anywhere}.foto,.iniciales{width:1.05in;height:1.22in;object-fit:cover;flex-shrink:0;border-radius:8px}.iniciales{display:flex;align-items:center;justify-content:center;background:{{ $suave }};color:{{ $lat }};font-size:25pt;font-weight:700}.contenido{display:grid;grid-template-columns:minmax(0,1fr) 2.05in;gap:.28in;margin-top:.2in}.principal,.lateral{min-width:0}.lateral{border-left:1px solid #dce3eb;padding-left:.2in}.sec{margin-bottom:.18in}.sec h2{font-size:10pt;text-transform:uppercase;letter-spacing:.05em;font-weight:800;color:{{ $lat }};border-bottom:2px solid {{ $ac }};padding-bottom:5px;margin-bottom:9px;break-after:avoid}.perfil{font-size:9pt;color:#334155;text-align:left;white-space:pre-line}.item{margin-bottom:11px;break-inside:avoid}.cargo{font-size:9.5pt;font-weight:700;color:#172033}.empresa{font-size:8.8pt;color:{{ $lat }};font-weight:600}.fecha{font-size:7.8pt;color:#64748b;margin:2px 0}.detalle{font-size:8.6pt;color:#475569;white-space:pre-line}.lista{list-style:none;padding:0}.lista li{font-size:8.7pt;line-height:1.5;padding:4px 0;border-bottom:1px solid #edf0f4;overflow-wrap:anywhere}.dato{font-size:8pt;line-height:1.5;margin:6px 0;overflow-wrap:anywhere}.dato strong{display:block;font-size:7pt;letter-spacing:.04em;text-transform:uppercase;color:#64748b}.ref{font-size:8pt;margin:8px 0;overflow-wrap:anywhere}
.balance .cab{border-bottom-width:1px}.balance .contenido{grid-template-columns:minmax(0,1fr) 2.15in}.balance .lateral{background:{{ $suave }};border:0;padding:.18in;border-radius:10px}.balance .foto{border-radius:50%;width:1.15in;height:1.15in}
.directivo{font-family:Georgia,serif}.directivo .nombre{font:700 30pt Georgia,serif}.directivo .contenido{display:block}.directivo .lateral{border:0;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:0 .25in}.directivo .sec h2{letter-spacing:.11em;border-bottom:1px solid #cbd5e1}.directivo .iniciales{display:none}
.portafolio .cab{background:{{ $lat }};padding:.22in;border-radius:0 18px 0 18px;border:0}.portafolio .nombre,.portafolio .contactos{color:white}.portafolio .puesto{color:#f1d48a}.portafolio .lateral{border:0;background:{{ $suave }};padding:.18in;border-radius:14px}.portafolio .foto{border-radius:0 12px 0 12px}
.consultor .contenido{display:block}.consultor .lateral{padding:0;border:0;display:grid;grid-template-columns:1fr 1fr;gap:0 .3in}.consultor .sec h2{background:{{ $suave }};padding:7px 10px;border:0;border-left:4px solid {{ $lat }}}.consultor .cab{border-bottom:5px double {{ $lat }}}.consultor .nombre{font-size:27pt}.consultor .iniciales{display:none}
.tecnico .cab{border-bottom:6px solid {{ $ac }}}.tecnico .nombre{font-size:25pt;letter-spacing:-.02em}.tecnico .contenido{grid-template-columns:1.9in minmax(0,1fr)}.tecnico .principal{grid-column:2;grid-row:1}.tecnico .lateral{grid-column:1;grid-row:1;border:0;border-right:2px solid {{ $lat }};padding:0 .18in 0 0}.tecnico .sec h2{font-family:'Courier New',monospace;letter-spacing:0}.tecnico .lista li{border-bottom:1px dashed #cbd5e1}.tecnico .foto{border-radius:0}
.cronologia .contenido{display:block}.cronologia .lateral{display:grid;grid-template-columns:1fr 1fr;gap:0 .3in;border:0;padding:0}.cronologia .experiencia .item{border-left:2px solid {{ $ac }};padding-left:.16in;position:relative}.cronologia .experiencia .item:before{content:'';position:absolute;left:-5px;top:4px;width:8px;height:8px;background:{{ $lat }};border-radius:50%}.cronologia .cab{border-bottom:1px solid #cbd5e1}.cronologia .foto{border-radius:50%;width:1.05in;height:1.05in}
.sereno .contenido{grid-template-columns:1.9in minmax(0,1fr);gap:.32in}.sereno .principal{grid-column:2;grid-row:1}.sereno .lateral{grid-column:1;grid-row:1;border:0;background:#f5f6f8;padding:.16in}.sereno .nombre{font-family:Georgia,serif;font-size:30pt;font-weight:400}.sereno .sec h2{letter-spacing:.1em;border:0}.sereno .cab{border-bottom:1px solid #cbd5e1}.sereno .iniciales{display:none}
.impacto .cab{background:{{ $lat }};border:0;padding:.22in .24in;border-bottom:8px solid {{ $ac }}}.impacto .nombre{font-size:30pt;text-transform:uppercase;color:white;letter-spacing:-.03em}.impacto .contactos{color:white}.impacto .puesto{color:#f8e8b8}.impacto .contenido{grid-template-columns:minmax(0,1fr) 2in}.impacto .lateral{border:0;background:#f0f3f7;padding:.15in}.impacto .sec h2{border-bottom-width:3px}
.editorial .nombre{font-family:Georgia,serif;font-size:31pt;font-weight:400}.editorial .puesto{letter-spacing:.14em;font-size:9pt;text-transform:uppercase}.editorial .cab{border-bottom:1px solid #0f172a}.editorial .sec h2{font:700 12pt Georgia,serif;letter-spacing:0;text-transform:none;border-bottom:1px solid #cbd5e1}.editorial .contenido{grid-template-columns:minmax(0,1fr) 2.2in;gap:.32in}.editorial .foto{border-radius:0;filter:grayscale(1)}.editorial .iniciales{display:none}
.trayectoria .cab{border-top:7px solid {{ $lat }};border-bottom:0;padding-top:.18in}.trayectoria .sec h2{border:0;border-bottom:1px solid {{ $lat }};padding:7px 0}.trayectoria .contenido{grid-template-columns:minmax(0,1fr) 2.1in}.trayectoria .lateral{border:0;padding-left:.18in;border-left:3px solid {{ $ac }}}.trayectoria .experiencia .item{padding:8px;background:#f5f7fa;border-radius:5px}.trayectoria .nombre{font-size:28pt}
</style></head><body><div class="hoja"><div class="fit {{ $estilo }}"><div class="cab"><div class="txt"><h1 class="nombre">{{ $c->nombreCompleto() }}</h1>
@if($vt['profesion'])<div class="puesto">{{ $vt['profesion'] }}</div>
@endif<div class="contactos">
@foreach(array_merge($contactos,$enlaces) as [$ico,$texto])<div>{{ $texto }}</div>
@endforeach</div></div>
@if($foto)<img class="foto" src="{{ $foto }}" alt="">
@else<div class="iniciales">{{ $c->iniciales() }}</div>
@endif</div>
<div class="contenido"><main class="principal">

@if($vt['perfil'])<section class="sec"><h2>{{ $t('Perfil profesional') }}</h2><p class="perfil">{{ $vt['perfil'] }}</p></section>
@endif

@if($experiencias->isNotEmpty())<section class="sec experiencia"><h2>{{ $t('Experiencia laboral') }}</h2>
@foreach($experiencias as $e)<div class="item"><div class="cargo">{{ $e->cargo ?: $t('Experiencia') }}</div><div class="empresa">{{ $e->empresa }}</div><div class="fecha">{{ $e->periodoTexto($lang) }}</div><div class="detalle">{{ $e->descripcion }}</div></div>
@endforeach</section>
@endif

@if($estudios->isNotEmpty())<section class="sec"><h2>{{ $t('Formación académica') }}</h2>
@foreach($estudios as $e)<div class="item"><div class="cargo">{{ $e->titulo ?: $t($e->nivelLegible()) }}</div><div class="empresa">{{ $e->institucion }}</div><div class="fecha">{{ $e->periodo ?: $e->anio }}</div><div class="detalle">{{ $e->detalle }}</div></div>
@endforeach</section>
@endif
</main><aside class="lateral">

@if($habilidades->isNotEmpty())<section class="sec"><h2>{{ $t('Habilidades') }}</h2><ul class="lista">
@foreach($habilidades as $h)<li>{{ $h->habilidad }}</li>
@endforeach</ul></section>
@endif

@if($idiomas->isNotEmpty())<section class="sec"><h2>{{ $t('Idiomas') }}</h2><ul class="lista">
@foreach($idiomas as $i)<li><b>{{ $i->nombre($lang) }}</b><br>{{ $i->nivelTexto($lang) }}</li>
@endforeach</ul></section>
@endif

@if(count($datosPro))<section class="sec"><h2>{{ $t('Datos personales') }}</h2>
@foreach($datosPro as [$k,$v])<div class="dato"><strong>{{ $k }}</strong>{{ $v }}</div>
@endforeach</section>
@endif

@if($referencias->isNotEmpty())<section class="sec"><h2>{{ $t('Referencias') }}</h2>
@foreach($referencias as $ref)<div class="ref"><b>{{ $ref->nombreConTratamiento() }}</b><br>{{ $ref->detalle }}<br>{{ $ref->telefono }}
@if($ref->correo)<br>{{ $ref->correo }}
@endif</div>
@endforeach</section>
@endif

@if(count($familia))<section class="sec"><h2>{{ $t('Familia') }}</h2>
@foreach($familia as [$k,$v])<div class="dato"><strong>{{ $k }}</strong>{{ $v }}</div>
@endforeach</section>
@endif
</aside></div></div></div>
@include('cv._una-hoja')</body></html>