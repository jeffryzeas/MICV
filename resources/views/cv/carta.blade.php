@php
$I = fn ($key) => \App\Support\Iconos::svg($key);
$academicos = $estudios->filter(fn ($e) => $e->nivel !== 'curso');
$cursos = $estudios->filter(fn ($e) => $e->nivel === 'curso');
$datosCarta = array_values(array_filter([
    [$lang === 'en' ? 'Document No.' : 'N.º Documento', $c->numero_documento ? $c->documentoFormateado() : null],
    [$t('Fecha de nacimiento'), $c->fecha_nacimiento ? $c->fecha_nacimiento->locale($lang)->translatedFormat($lang === 'en' ? 'F j, Y' : 'j \d\e F \d\e\l Y') . ' · ' . $vt['edad'] : $vt['edad']],
    [$t('Estado civil'), $vt['estado_civil']], [$t('Sexo'), $vt['sexo']], [$t('Nacionalidad'), $vt['nacionalidad']],
    [$lang === 'en' ? 'Work authorization' : 'Autorización laboral', \App\Support\SituacionLaboral::esExtranjero($c->pais_residencia ?? '', $c->nacionalidad) ? $c->autorizacion_laboral : null],
    [$lang === 'en' ? 'Availability' : 'Disponibilidad', $c->disponibilidad],
], fn ($row) => filled($row[1])));
$cualidades = array_values(array_filter(array_map('trim', explode(',', $c->cualidades ?? ''))));
@endphp
<!DOCTYPE html><html lang="{{ $lang }}"><head><meta charset="utf-8"><title>CV — {{ $c->nombreCompleto() }}</title><style>{!! $fuentesCss !!}

  :root{
    --yellow:#FFE200;
    --blue:#2160C4;
    --blue-dark:#1a4fa0;
    --ink:#111214;
    --text:#2a2d31;
    --muted:#5a6068;
    --paper:#ffffff;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  html{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  body{
    font-family:'Montserrat',system-ui,-apple-system,'Segoe UI',sans-serif;
    background:#4c5560;color:var(--text);line-height:1.4;
    display:flex;flex-direction:column;align-items:center;padding:24px 12px 60px;
  }

  /* toolbar (screen only) */
  .toolbar{width:8.5in;max-width:100%;display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px;color:#eef1f4;}
  .btn{font-family:inherit;font-size:13px;font-weight:700;border:none;border-radius:8px;padding:10px 16px;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:filter .12s ease,transform .12s ease;}
  .btn:focus-visible{outline:3px solid #fff3a6;outline-offset:2px;}
  .btn:hover{filter:brightness(1.05);}
  .btn:active{transform:translateY(1px);}
  .btn--go{background:var(--yellow);color:#1a1a00;}
  .btn--ghost{background:rgba(255,255,255,.12);color:#eef1f4;border:1px solid rgba(255,255,255,.25);}
  .toolbar .hint{margin-left:auto;font-size:12px;color:#c8cfd6;}

  /* letter page */
  .page{width:8.5in;min-height:11in;background:var(--paper);box-shadow:0 16px 46px rgba(0,0,0,.34);overflow:hidden;display:flex;flex-direction:column;}

  /* top contact bar */
  .topbar{display:flex;align-items:center;gap:26px;flex-wrap:wrap;padding:13px 26px;border-bottom:5px solid var(--yellow);}
  .cinfo{display:flex;align-items:center;gap:9px;font-size:13px;font-weight:600;color:var(--ink);}
  .cinfo svg{width:17px;height:17px;color:var(--blue);flex:0 0 17px;}

  .cols{display:flex;flex:1;}

  /* LEFT yellow column */
  .left{width:37%;background:var(--yellow);padding:18px 22px 20px;display:flex;flex-direction:column;gap:18px;}
  .photo-frame{width:100%;max-width:205px;margin:2px auto 2px;aspect-ratio:35/45;background:#fff;border:4px solid #fff;box-shadow:0 4px 14px rgba(0,0,0,.28);overflow:hidden;position:relative;}
  .photo-frame img{width:100%;height:100%;object-fit:cover;display:block;}

  .l-head{font-family:'Montserrat';font-weight:800;font-size:18.5px;letter-spacing:.12em;color:var(--blue);text-transform:uppercase;margin-bottom:8px;}

  .edu{margin-bottom:14px;}
  .edu:last-child{margin-bottom:0;}
  .edu .t{font-size:12.5px;font-weight:700;color:var(--ink);line-height:1.28;text-transform:uppercase;}
  .edu .o{font-size:11.3px;color:#3f4550;font-weight:500;}
  .edu .d{display:inline-block;font-size:12px;font-weight:700;color:var(--ink);margin-top:6px;border-bottom:2.5px solid var(--ink);padding-bottom:4px;min-width:130px;}

  .others{list-style:none;display:flex;flex-direction:column;gap:11px;}
  .others li{position:relative;padding-left:16px;font-size:11px;line-height:1.35;color:var(--ink);font-weight:600;}
  .others li::before{content:"";position:absolute;left:0;top:5px;width:6px;height:6px;border-radius:50%;background:var(--blue);}
  .others li span{display:block;font-weight:500;color:#41474f;font-size:10.4px;margin-top:1px;}

  /* RIGHT white column */
  .right{width:63%;padding:0 28px 18px;display:flex;flex-direction:column;}
  .namewrap{background:var(--blue);margin:0 -28px 9px;padding:12px 28px 10px;}
  .name{font-family:'Anton',Impact,sans-serif;color:#fff;font-size:44px;line-height:.92;letter-spacing:.01em;text-transform:uppercase;}
  .role{margin-top:7px;font-size:12px;font-weight:700;letter-spacing:.3em;color:#fff;text-transform:uppercase;}

  .r-head{font-family:'Montserrat';font-weight:800;font-size:15.5px;letter-spacing:.13em;color:var(--blue);text-transform:uppercase;margin-bottom:3px;padding-bottom:3px;border-bottom:2.5px solid var(--ink);}
  .section{margin-bottom:2px;}

  .dp{display:flex;flex-direction:column;gap:3px;}
  .dp .row .k{font-size:11px;font-weight:700;color:var(--ink);text-transform:uppercase;letter-spacing:.02em;}
  .dp .row .v{font-size:11px;color:var(--muted);font-weight:500;}

  .exp .t{font-size:13px;font-weight:700;color:var(--ink);}
  .exp .m{font-size:11.5px;color:var(--muted);font-weight:600;margin-top:3px;}
  .job{margin-bottom:4px;}
  .job:last-child{margin-bottom:0;}
  .jt{font-size:12.5px;font-weight:700;color:var(--ink);}
  .jm{font-size:11px;color:var(--muted);font-weight:600;margin-top:1px;}
  .jd{font-size:10.6px;color:#41474f;line-height:1.38;margin-top:2px;}
  .jy{font-size:10.5px;font-weight:700;color:var(--blue);margin-top:3px;letter-spacing:.03em;}

  .ref{margin-bottom:3px;}
  .ref .rn{font-size:12.5px;font-weight:700;color:var(--ink);text-transform:uppercase;letter-spacing:.02em;}
  .ref .rt{font-size:11.5px;color:var(--muted);font-weight:500;}

  .badges{margin-top:auto;padding-top:3px;display:grid;grid-template-columns:1fr 1fr;gap:4px 10px;}
  .badge{display:flex;align-items:center;gap:10px;font-size:12.5px;font-weight:700;color:var(--ink);}
  .badge svg{width:27px;height:27px;color:var(--blue);flex:0 0 27px;}
  .skill{font-size:10.8px;font-weight:600;color:var(--blue);background:#eaf1fb;border:1px solid #c5d8f5;border-radius:20px;padding:5px 11px;line-height:1;}
  .langs{margin-top:6px;font-size:11.2px;color:#3a3f46;font-weight:500;}
  .langs b{color:var(--ink);}

  @media print{
    @page{size:letter;margin:0;}
    body{background:none;padding:0;}
    .toolbar{display:none !important;}
    .page{box-shadow:none;width:8.5in;min-height:11in;}
  }
  @media (max-width:760px){
    .page{width:100%;}
    .cols{flex-direction:column;}
    .left,.right{width:100%;}
    .namewrap{margin-left:-26px;margin-right:-26px;}
    .toolbar{width:100%;}
    .name{font-size:38px;}
  }
  @media (prefers-reduced-motion:reduce){*{transition:none !important;}}

body{font-family:'CvSans',sans-serif;padding:0;background:white;display:block} .page{height:11in;min-height:0;box-shadow:none;position:relative} .cols{min-height:0} .name{font-family:Arial,sans-serif;font-weight:400} .r-head,.l-head{font-family:Georgia,serif} .left{flex-shrink:0} .right{flex-shrink:0} .fit{width:8.5in;min-height:11in;display:flex;flex-direction:column;transform-origin:top left} .cols{flex:1} .page{overflow:hidden} .cinfo,.jd,.jm,.ref,.others li{overflow-wrap:anywhere} .jd{white-space:pre-line} .badges{margin-top:auto} @media(max-width:760px){.cols{flex-direction:row}.left{width:37%}.right{width:63%}.namewrap{margin-left:-28px;margin-right:-28px}.name{font-size:44px}}
.page{width:8.5in}
@if($modoPantalla ?? false)
body{background:#525659;padding:24px 0}.page{margin:0 auto;box-shadow:0 4px 18px #0005}
@media(max-width:860px){body{zoom:.45}}
@endif
</style></head><body><main class="page"><div class="fit">
<div class="topbar">
@if($c->correo)<div class="cinfo">{!! $I('mail') !!}{{ $c->correo }}</div>@endif
@foreach($contactos as [$ico,$txt]) @if($ico !== 'mail')<div class="cinfo">{!! $I($ico) !!}{{ $txt }}</div>@endif @endforeach
@foreach($enlaces as [$ico,$txt])<div class="cinfo">{!! $I($ico) !!}{{ $txt }}</div>@endforeach
</div><div class="cols"><aside class="left">
@if($foto)<div class="photo-frame"><img src="{{ $foto }}" alt="{{ $c->nombreCompleto() }}"></div>@endif
@if($academicos->isNotEmpty())<div><div class="l-head">{{ $t('Educación') }}</div>
@foreach($academicos as $e)<div class="edu"><div class="t">{{ $e->titulo ?: $t($e->nivelLegible()) }}</div><div class="o">{{ $e->institucion }}{{ $e->detalle ? ' · ' . $e->detalle : '' }}</div>@if($e->periodo || $e->anio)<div class="d">{{ $e->periodo ?: $e->anio }}</div>@endif</div>@endforeach
</div>@endif
@if($cursos->isNotEmpty())<div><div class="l-head">{{ $lang === 'en' ? 'Other studies' : 'Otros estudios' }}</div><ul class="others">
@foreach($cursos as $e)<li>{{ $e->titulo ?: $t($e->nivelLegible()) }}<span>{{ implode(' · ', array_filter([$e->institucion, $e->periodo ?: $e->anio, $e->detalle])) }}</span></li>@endforeach
</ul></div>@endif
</aside><section class="right"><div class="namewrap"><div class="name">{{ trim($c->primer_nombre . ' ' . $c->segundo_nombre) }}<br>{{ trim($c->primer_apellido . ' ' . $c->segundo_apellido) }}</div>@if($vt['profesion'])<div class="role">{{ $vt['profesion'] }}</div>@endif</div>
@if($vt['perfil'])<div class="section"><div class="r-head">{{ $t('Perfil profesional') }}</div><p style="font-size:11px;line-height:1.42;color:#3a3f46;font-weight:500">{{ $vt['perfil'] }}</p></div>@endif
@if(count($datosCarta))<div class="section"><div class="r-head">{{ $t('Datos personales') }}</div><div class="dp">@foreach($datosCarta as [$k,$v])<div class="row"><div class="k">{{ $k }}</div><div class="v">{{ $v }}</div></div>@endforeach</div></div>@endif
@if($experiencias->isNotEmpty())<div class="section exp"><div class="r-head">{{ $t('Experiencia laboral') }}</div>
@foreach($experiencias as $e)<div class="job"><div class="jt">{{ $e->cargo }}</div><div class="jm">{{ implode(' · ', array_filter([$e->empresa,$e->jornada,$e->ubicacion])) }}</div>@if($e->descripcion)<div class="jd">{{ $e->descripcion }}</div>@endif<div class="jy">{{ $e->fecha_inicio?->locale($lang)->translatedFormat('M Y') }}{{ ($e->fecha_inicio || $e->fecha_fin) ? ' – ' : '' }}{{ $e->fecha_fin?->locale($lang)->translatedFormat('M Y') ?: ($e->fecha_inicio ? ($lang === 'en' ? 'Present' : 'Actualidad') : '') }}{{ $e->duracion ? ' · ' . $e->duracion : '' }}</div></div>@endforeach
</div>@endif
@if($habilidades->isNotEmpty() || $idiomas->isNotEmpty())<div class="section"><div class="r-head">{{ $t('Habilidades') }}</div><div style="display:flex;flex-wrap:wrap;gap:6px">@foreach($habilidades as $h)<span class="skill">{{ $h->habilidad }}</span>@endforeach</div>
@if($idiomas->isNotEmpty())<div class="langs"><b>{{ $t('Idiomas') }}:</b> @foreach($idiomas as $idioma){{ $idioma->nombre($lang) }} ({{ $idioma->nivelTexto($lang) }}){{ $loop->last ? '' : ' · ' }}@endforeach</div>@endif</div>@endif
@if($referencias->isNotEmpty())<div class="section"><div class="r-head">{{ $t('Referencias') }}</div>@foreach($referencias as $ref)<div class="ref"><div class="rn">{{ $ref->nombreConTratamiento() }}</div><div class="rt">{{ implode(' · ', array_filter([$ref->detalle,$ref->telefono,$ref->correo])) }}</div></div>@endforeach</div>@endif
@if(count($cualidades))<div class="badges">@foreach($cualidades as $cualidad)<div class="badge">{!! ['<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>', '<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M3 9h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>', '<svg viewBox="0 0 24 24" fill="none"><path d="M5 12a7 7 0 0 1 14 0M8 12a4 4 0 0 1 8 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="12" r="1.6" fill="currentColor"/></svg>', '<svg viewBox="0 0 24 24" fill="none"><path d="M10 4a2 2 0 1 1 4 0v1h2a1 1 0 0 1 1 1v2h1a2 2 0 1 1 0 4h-1v3a1 1 0 0 1-1 1h-3v-1a2 2 0 1 0-4 0v1H6a1 1 0 0 1-1-1v-3H4a2 2 0 1 1 0-4h1V6a1 1 0 0 1 1-1h4V4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>'][$loop->index % 4] !!}{{ $cualidad }}</div>@endforeach</div>@endif
</section></div></div></main>
<script>
(async()=>{await document.fonts.ready;await Promise.all([...document.images].map(i=>i.decode().catch(()=>{})));const fit=document.querySelector('.fit'),page=document.querySelector('.page');const ratio=Math.min(1,page.clientHeight/fit.scrollHeight);if(ratio<1){fit.style.width=(816/ratio)+'px';fit.style.minHeight=(1056/ratio)+'px';await new Promise(requestAnimationFrame);const scale=Math.min(1,1056/fit.scrollHeight,816/fit.scrollWidth);fit.style.transform='scale('+scale+')';}window.cvListo=true;})();
</script></body></html>
