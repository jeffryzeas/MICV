<!doctype html><html lang="{{ $lang }}"><head><meta charset="utf-8"><title>CV — {{ $c->nombreCompleto() }}</title><style>
@page{size:Letter portrait;margin:18mm 16mm}*{box-sizing:border-box}body{margin:0;font:10.5pt/1.45 Arial,Helvetica,sans-serif;color:#111;background:white}.hoja{width:8.5in;min-height:11in;padding:18mm 16mm;background:white}h1{font-size:22pt;line-height:1.15;margin:0 0 5px;font-weight:700}h2{font-size:11.5pt;line-height:1.3;margin:15px 0 7px;font-weight:700;text-transform:uppercase;break-after:avoid}h3{font-size:10.5pt;margin:8px 0 2px;break-after:avoid}p{margin:3px 0 4px;white-space:pre-line;overflow-wrap:anywhere}.contacto{font-size:10pt;margin-bottom:9px}.puesto{font-weight:700;margin:5px 0}.item{margin-bottom:9px}.sub{margin:0 0 4px}.fecha{margin:2px 0 5px}ul{margin:5px 0;padding-left:18px}li{margin:3px 0;overflow-wrap:anywhere}.idiomas p{margin:4px 0}@media print{.hoja{width:auto;min-height:0;padding:0}h1,h2,h3{break-after:avoid}p,li{orphans:2;widows:2}}

@if($modoPantalla ?? false)
html,body{background:#525659}.hoja{margin:20px auto;box-shadow:0 4px 18px #0004}@media(max-width:860px){body{zoom:.45}}

@endif
</style></head><body><main class="hoja"><h1>{{ $c->nombreCompleto() }}</h1>

@if($vt['profesion'])<p class="puesto">{{ $vt['profesion'] }}</p>
@endif<div class="contacto">
@foreach(array_merge($contactos,$enlaces) as [$ico,$texto])<p>{{ $texto }}</p>
@endforeach</div>

@if($vt['perfil'])<section><h2>{{ $t('Perfil profesional') }}</h2><p>{{ $vt['perfil'] }}</p></section>
@endif

@if($experiencias->isNotEmpty())<section><h2>{{ $t('Experiencia laboral') }}</h2>
@foreach($experiencias as $e)<div class="item"><h3>{{ $e->cargo ?: $t('Experiencia') }}</h3><p class="sub">{{ implode(' | ',array_filter([$e->empresa,$e->ubicacion])) }}</p>
@if($e->periodoTexto($lang))<p class="fecha">{{ $e->periodoTexto($lang) }}</p>
@endif
@if($e->descripcion)<p>{{ $e->descripcion }}</p>
@endif</div>
@endforeach</section>
@endif

@if($estudios->isNotEmpty())<section><h2>{{ $t('Formación académica') }}</h2>
@foreach($estudios as $e)<div class="item"><h3>{{ $e->titulo ?: $t($e->nivelLegible()) }}</h3><p>{{ $e->institucion }}</p><p>{{ $e->periodo ?: $e->anio }}</p>
@if($e->detalle)<p>{{ $e->detalle }}</p>
@endif</div>
@endforeach</section>
@endif

@if($habilidades->isNotEmpty())<section><h2>{{ $t('Habilidades') }}</h2><p>{{ $habilidades->pluck('habilidad')->implode(', ') }}</p></section>
@endif

@if($idiomas->isNotEmpty())<section class="idiomas"><h2>{{ $t('Idiomas') }}</h2>
@foreach($idiomas as $i)<p>{{ $i->nombre($lang) }}: {{ $i->nivelTexto($lang) }}</p>
@endforeach</section>
@endif
</main><script>document.fonts.ready.then(()=>window.cvListo=true);</script></body></html>