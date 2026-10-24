@php
    $lat = $paleta['lateral']; $ac = $paleta['acento']; $suave = $paleta['suave'];
    $I = fn ($k) => \App\Support\Iconos::svg($k);
    $datos = $datosPro;
    $contacto = array_merge($contactos, $enlaces);
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
<meta charset="utf-8">
<title>CV — {{ $c->nombreCompleto() }}</title>
@include('cv._base-pro')
<style>
.fit { padding: 0.5in 0.55in 0.4in; }
.tope { position: absolute; top: 0; left: 0; right: 0; height: 0.14in; background: {{ $lat }}; }
.tope::after { content: ""; position: absolute; left: 0; bottom: -4px; width: 2.2in; height: 4px; background: {{ $ac }}; }
.cab { display: flex; align-items: center; gap: 0.3in; padding-bottom: 0.16in; }
.cab .txt { flex: 1; min-width: 0; }
.cab .nombre { font-size: 26pt; font-weight: 800; line-height: 1.05; color: {{ $lat }}; text-transform: uppercase; letter-spacing: .01em; }
.cab .puesto { margin-top: 7px; font-size: 10pt; font-weight: 700; letter-spacing: .26em; text-transform: uppercase; color: {{ $ac }}; }
.cab .foto, .cab .sin { width: 1.25in; height: 1.45in; border-radius: 10px; flex-shrink: 0; }
.cab .foto { object-fit: cover; border-bottom: 5px solid {{ $ac }}; box-shadow: 0 2px 0 {{ $lat }}; }
.contacto { display: flex; flex-wrap: wrap; gap: 6px 18px; background: {{ $suave }}; border-left: 4px solid {{ $lat }}; padding: 9px 14px; border-radius: 0 6px 6px 0; font-size: 8.5pt; color: #374151; }
.contacto span { display: inline-flex; align-items: center; gap: 6px; }
.contacto svg { width: 12px; height: 12px; color: {{ $lat }}; flex-shrink: 0; }
.fila { display: grid; grid-template-columns: 1.45in 1fr; gap: 0 0.22in; margin-top: 0.2in; }
.fila > .et { font-size: 9pt; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: {{ $lat }}; padding-top: 1px; line-height: 1.3; }
.fila > .et::after { content: ""; display: block; width: 28px; height: 3px; background: {{ $ac }}; margin-top: 6px; border-radius: 2px; }
.fila > .ct { border-left: 1px solid #e5e7eb; padding-left: 0.2in; min-width: 0; }
.perfil { font-size: 9.6pt; color: #374151; text-align: justify; }
.it { margin-bottom: 9px; } .it:last-child { margin-bottom: 0; }
.it .top { display: flex; justify-content: space-between; gap: 10px; align-items: baseline; }
.it .t { font-weight: 700; font-size: 10pt; color: #111827; }
.it .f { font-size: 8pt; font-weight: 700; color: {{ $ac }}; white-space: nowrap; }
.it .s { font-size: 9pt; color: {{ $lat }}; font-weight: 600; }
.it .d { font-size: 9pt; color: #4b5563; margin-top: 2px; }
.habs { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 4px 14px; font-size: 9pt; color: #374151; }
.habs div { padding-left: 13px; position: relative; } .habs div::before { content: ""; position: absolute; left: 0; top: 6px; width: 6px; height: 6px; background: {{ $ac }}; border-radius: 1px; }
.datos { display: grid; grid-template-columns: 1fr 1fr; gap: 5px 18px; }
.datos .k { font-size: 7.4pt; letter-spacing: .1em; text-transform: uppercase; color: #9ca3af; font-weight: 600; } .datos .v { font-size: 9pt; color: #111827; }
.refs { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 18px; } .refs .t { font-weight: 700; font-size: 9.3pt; color: #111827; } .refs .s { font-size: 8.5pt; color: #4b5563; word-break: break-word; }
</style>
<style>.fila{display:block;border-top:1px solid #d6dce5;padding-top:10px;margin-top:.14in}.fila>.et{margin-bottom:7px;letter-spacing:.08em}.fila>.et::after{display:none}.fila>.ct{border:0;padding:0}.cab .nombre{font-size:27pt}</style>
</head>
<body>
<div class="hoja">
<div class="tope"></div>
<div class="fit">
    <header class="cab">
        <div class="txt">
            <div class="nombre">{{ $c->nombreCompleto() }}</div>
            @if ($vt['profesion'])<div class="puesto">{{ $vt['profesion'] }}</div>@endif
        </div>
        @if ($foto)<img class="foto" src="{{ $foto }}" alt="">@endif
    </header>

    @if ($contacto)<div class="contacto">@foreach ($contacto as [$ico, $x])<span>{!! $I($ico) !!}{{ $x }}</span>@endforeach</div>@endif

    @if ($vt['perfil'])
        <div class="fila"><div class="et">{{ $t('Perfil') }}</div><div class="ct"><p class="perfil">{{ $vt['perfil'] }}</p></div></div>
    @endif

    @if ($experiencias->isNotEmpty())
        <div class="fila"><div class="et">{{ $t('Experiencia') }}</div><div class="ct">
            @foreach ($experiencias as $e)
                <div class="it"><div class="top"><div class="t">{{ $e->cargo ?: $t('Experiencia') }}</div>@if ($e->periodoTexto($lang))<div class="f">{{ $e->periodoTexto($lang) }}</div>@endif</div>
                    @if ($e->empresa)<div class="s">{{ $e->empresa }}</div>@endif
                    @if ($e->descripcion)<div class="d">{{ $e->descripcion }}</div>@endif</div>
            @endforeach
        </div></div>
    @endif

    @if ($estudios->isNotEmpty())
        <div class="fila"><div class="et">{{ $t('Formación') }}</div><div class="ct">
            @foreach ($estudios as $e)
                <div class="it"><div class="top"><div class="t">{{ $e->titulo ?: $t($e->nivelLegible()) }}</div>@if ($e->anio)<div class="f">{{ $e->anio }}</div>@endif</div>
                    <div class="s">{{ $t($e->nivelLegible()) }}@if ($e->institucion) · {{ $e->institucion }}@endif</div>
                    @if ($e->detalle)<div class="d">{{ $e->detalle }}</div>@endif</div>
            @endforeach
        </div></div>
    @endif

    @if ($idiomas->isNotEmpty())
        <div class="fila"><div class="et">{{ $t('Idiomas') }}</div><div class="ct">
            @include('cv._idiomas', ['on' => $ac, 'off' => '#e5e7eb', 'texto' => '#111827', 'nivel' => '#6b7280', 'columnas' => 3])
        </div></div>
    @endif

    @if ($habilidades->isNotEmpty())
        <div class="fila"><div class="et">{{ $t('Habilidades') }}</div><div class="ct"><div class="habs">@foreach ($habilidades as $h)<div>{{ $h->habilidad }}</div>@endforeach</div></div></div>
    @endif

    @if (count($datos))
        <div class="fila"><div class="et">{{ $t('Datos personales') }}</div><div class="ct"><div class="datos">@foreach ($datos as [$k, $v])<div><div class="k">{{ $k }}</div><div class="v">{{ $v }}</div></div>@endforeach</div></div></div>
    @endif

    @if ($referencias->isNotEmpty())
        <div class="fila"><div class="et">{{ $t('Referencias') }}</div><div class="ct"><div class="refs">
            @foreach ($referencias as $r)<div><div class="t">{{ $r->nombreConTratamiento() }}</div><div class="s">{{ collect([$r->telefono ? $t('Tel.') . ' ' . $r->telefono : null, $r->correo])->filter()->implode(' · ') }}</div></div>@endforeach
        </div></div></div>
    @endif

    @if (count($familia))
        <div class="fila"><div class="et">{{ $t('Familia') }}</div><div class="ct"><div class="datos">@foreach ($familia as [$k, $v])<div><div class="k">{{ $k }}</div><div class="v">{{ $v }}</div></div>@endforeach</div></div></div>
    @endif
</div>
</div>
@include('cv._una-hoja')
</body>
</html>
