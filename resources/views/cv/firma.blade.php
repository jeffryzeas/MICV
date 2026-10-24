@php
    $lat = $paleta['lateral']; $ac = $paleta['acento'];
    $tel = fn ($n) => trim(($c->telefono_codigo_pais ? $c->telefono_codigo_pais . ' ' : '') . $n);
    $I = fn ($k) => \App\Support\Iconos::svg($k);
    $contacto = array_merge($contactos, $enlaces);
    $datos = $datosPro;
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
<meta charset="utf-8">
<title>CV — {{ $c->nombreCompleto() }}</title>
@include('cv._base-pro')
<style>
.fit { padding: 0.5in 0.65in 0.4in; }
.barra { position: absolute; top: 0; left: 0; right: 0; height: 8px; background: {{ $ac }}; }
.barra2 { position: absolute; top: 8px; left: 0; right: 0; height: 3px; background: {{ $lat }}; }
.cab { text-align: center; }
.cab .foto { width: 1.15in; height: 1.15in; border-radius: 50%; object-fit: cover; border: 2px solid {{ $ac }}; padding: 3px; background: #fff; margin-bottom: 10px; }
.cab .nombre { font-family: 'CvSerif', Georgia, serif; font-size: 30pt; line-height: 1.05; color: {{ $lat }}; font-weight: 700; }
.cab .puesto { margin-top: 6px; font-size: 9.5pt; letter-spacing: .34em; text-transform: uppercase; color: #6b7280; font-weight: 600; }
.cab .contacto { margin-top: 10px; font-size: 8.8pt; color: #4b5563; }
.cab .contacto span { display: inline; }
.cab .contacto svg { width: 11px; height: 11px; color: {{ $ac }}; vertical-align: -1.5px; margin-right: 4px; }
.cab .contacto span + span::before { content: "•"; color: {{ $ac }}; margin: 0 9px; }
.orn { display: flex; align-items: center; justify-content: center; gap: 10px; margin: 14px 0 4px; }
.orn i { display: block; height: 1px; width: 1.6in; background: {{ $ac }}; } .orn b { width: 7px; height: 7px; background: {{ $ac }}; transform: rotate(45deg); }
.sec { margin-top: 0.2in; }
.sec h2 { font-size: 10pt; letter-spacing: .3em; text-transform: uppercase; color: {{ $lat }}; font-weight: 700; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; margin-bottom: 10px; }
.perfil { font-size: 9.8pt; color: #374151; text-align: justify; font-style: italic; }
.fila { display: grid; grid-template-columns: 1.2in 1fr; gap: 14px; margin-bottom: 10px; }
.fila .f { font-size: 8.4pt; color: {{ $ac }}; font-weight: 700; text-align: right; padding-top: 2px; }
.fila .t { font-weight: 700; font-size: 10pt; color: #111827; } .fila .s { font-size: 9pt; color: #4b5563; } .fila .d { font-size: 9pt; color: #4b5563; margin-top: 2px; }
.grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px 18px; }
.grid3 .k { font-size: 7.4pt; letter-spacing: .12em; text-transform: uppercase; color: #9ca3af; font-weight: 600; } .grid3 .v { font-size: 9pt; color: #111827; }
.habs { columns: 3; column-gap: 18px; font-size: 9pt; color: #374151; } .habs div { break-inside: avoid; margin-bottom: 4px; } .habs div::before { content: "◆ "; color: {{ $ac }}; font-size: 7pt; }
.refs { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 18px; } .refs .t { font-weight: 700; font-size: 9.4pt; color: #111827; } .refs .s { font-size: 8.6pt; color: #4b5563; }
</style>
<style>body{font-family:Georgia,serif}.nombre{font-family:Georgia,serif;font-style:italic}.foto{border-radius:0!important}h2{letter-spacing:.12em;font-size:10pt}</style>
</head>
<body>
<div class="hoja">
<div class="barra"></div><div class="barra2"></div>
<div class="fit">
    <header class="cab">
        @if ($foto)<img class="foto" src="{{ $foto }}" alt="">@endif
        <div class="nombre">{{ $c->nombreCompleto() }}</div>
        @if ($vt['profesion'])<div class="puesto">{{ $vt['profesion'] }}</div>@endif
        @if ($contacto)<div class="contacto">@foreach ($contacto as [$ico, $x])<span>{!! $I($ico) !!}{{ $x }}</span>@endforeach</div>@endif
        <div class="orn"><i></i><b></b><i></i></div>
    </header>

    @if ($vt['perfil'])<div class="sec"><h2>{{ $t('Perfil') }}</h2><p class="perfil">{{ $vt['perfil'] }}</p></div>@endif

    @if ($experiencias->isNotEmpty())
        <div class="sec"><h2>{{ $t('Experiencia laboral') }}</h2>
            @foreach ($experiencias as $e)
                <div class="fila"><div class="f">{{ $e->periodoTexto($lang) }}</div><div><div class="t">{{ $e->cargo ?: $t('Experiencia') }}</div>
                    @if ($e->empresa)<div class="s">{{ $e->empresa }}</div>@endif @if ($e->descripcion)<div class="d">{{ $e->descripcion }}</div>@endif</div></div>
            @endforeach
        </div>
    @endif

    @if ($estudios->isNotEmpty())
        <div class="sec"><h2>{{ $t('Formación académica') }}</h2>
            @foreach ($estudios as $e)
                <div class="fila"><div class="f">{{ $e->anio }}</div><div><div class="t">{{ $e->titulo ?: $t($e->nivelLegible()) }}</div>
                    <div class="s">{{ $t($e->nivelLegible()) }}@if ($e->institucion) · {{ $e->institucion }}@endif</div>@if ($e->detalle)<div class="d">{{ $e->detalle }}</div>@endif</div></div>
            @endforeach
        </div>
    @endif

    @if ($idiomas->isNotEmpty())<div class="sec"><h2>{{ $t('Idiomas') }}</h2><div class="grid3">@foreach ($idiomas as $x)<div><div class="k">{{ $x->nombre($lang) }}</div><div class="v">{{ $x->nivelTexto($lang) }}</div></div>@endforeach</div></div>@endif

    @if ($habilidades->isNotEmpty())<div class="sec"><h2>{{ $t('Habilidades') }}</h2><div class="habs">@foreach ($habilidades as $h)<div>{{ $h->habilidad }}</div>@endforeach</div></div>@endif

    @if (count($datos))<div class="sec"><h2>{{ $t('Datos personales') }}</h2><div class="grid3">@foreach ($datos as [$k, $v])<div><div class="k">{{ $k }}</div><div class="v">{{ $v }}</div></div>@endforeach</div></div>@endif

    @if ($referencias->isNotEmpty())
        <div class="sec"><h2>{{ $t('Referencias') }}</h2><div class="refs">
            @foreach ($referencias as $r)<div><div class="t">{{ $r->nombreConTratamiento() }}</div><div class="s">{{ collect([$r->telefono ? $t('Tel.') . ' ' . $r->telefono : null, $r->correo])->filter()->implode(' · ') }}</div></div>@endforeach
        </div></div>
    @endif

    @if (count($familia))<div class="sec"><h2>{{ $t('Datos familiares') }}</h2><div class="grid3">@foreach ($familia as [$k, $v])<div><div class="k">{{ $k }}</div><div class="v">{{ $v }}</div></div>@endforeach</div></div>@endif
</div>
</div>
@include('cv._una-hoja')
</body>
</html>
