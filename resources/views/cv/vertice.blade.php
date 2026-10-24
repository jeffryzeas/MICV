@php
    $lat = $paleta['lateral']; $ac = $paleta['acento']; $suave = $paleta['suave'];
    $I = fn ($k) => \App\Support\Iconos::svg($k);
    $datos = $datosPro;
    $nombres = trim($c->primer_nombre . ' ' . $c->segundo_nombre);
    $apellidos = trim($c->primer_apellido . ' ' . $c->segundo_apellido);
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
<meta charset="utf-8">
<title>CV — {{ $c->nombreCompleto() }}</title>
@include('cv._base-pro')
<style>
.col-fondo { position: absolute; top: 0; bottom: 0; right: 0; width: 34%; background: #f3f5f8; }
.col-fondo::before { content: ""; position: absolute; top: 0; bottom: 0; left: 0; width: 4px; background: {{ $ac }}; }
.cab { position: relative; background: {{ $lat }}; color: #fff; padding: 0.5in 0.5in 0.62in; clip-path: polygon(0 0, 100% 0, 100% 72%, 0 100%); display: flex; align-items: center; gap: 0.3in; }
.cab .txt { flex: 1; min-width: 0; }
.cab .nombre { font-size: 27pt; line-height: 1.04; font-weight: 800; text-transform: uppercase; letter-spacing: .01em; }
.cab .nombre .a { color: {{ $ac }}; display: block; }
.cab .puesto { margin-top: 9px; display: inline-block; font-size: 9pt; font-weight: 700; letter-spacing: .22em; text-transform: uppercase; border: 1.5px solid {{ $ac }}; color: #fff; padding: 4px 12px; border-radius: 30px; }
.cab .foto { width: 1.5in; height: 1.5in; border-radius: 22px; object-fit: cover; border: 4px solid #fff; transform: rotate(3deg); box-shadow: 0 6px 16px rgba(0,0,0,.25); flex-shrink: 0; margin-right: 0.15in; }
.cuerpo { display: flex; margin-top: -0.18in; }
.main { width: 66%; padding: 0.1in 0.38in 0.35in 0.5in; }
.lado { width: 34%; padding: 0.3in 0.36in 0.35in 0.32in; }
h2 { display: flex; align-items: center; gap: 9px; font-size: 10.5pt; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: {{ $lat }}; margin: 0.2in 0 9px; }
h2 .ic { width: 24px; height: 24px; border-radius: 7px; background: {{ $ac }}; color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transform: rotate(-6deg); }
h2 .ic svg { width: 13px; height: 13px; transform: rotate(6deg); }
.main > h2:first-child, .lado > h2:first-child { margin-top: 0.08in; }
.lado h2 { font-size: 9.5pt; }
.perfil { font-size: 9.6pt; color: #374151; text-align: justify; }
.it { position: relative; padding: 0 0 10px 16px; border-left: 2px dashed #d7dce4; margin-left: 5px; }
.it:last-child { padding-bottom: 2px; }
.it::before { content: ""; position: absolute; left: -7px; top: 2px; width: 12px; height: 12px; border-radius: 50%; background: #fff; border: 3px solid {{ $lat }}; }
.it .t { font-weight: 700; font-size: 10pt; color: #111827; }
.it .f { display: inline-block; font-size: 7.6pt; font-weight: 700; color: #fff; background: {{ $lat }}; padding: 1px 8px; border-radius: 10px; margin-left: 4px; vertical-align: 1px; }
.it .s { font-size: 9pt; color: {{ $ac }}; font-weight: 700; }
.it .d { font-size: 9pt; color: #4b5563; margin-top: 2px; }
.con { list-style: none; }
.con li { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 6px; font-size: 8.5pt; color: #374151; word-break: break-word; }
.con svg { width: 13px; height: 13px; color: {{ $lat }}; flex-shrink: 0; margin-top: 2px; }
.chips { display: flex; flex-wrap: wrap; gap: 5px; }
.chips span { font-size: 8.2pt; background: #fff; border: 1px solid {{ $ac }}; color: #1f2937; padding: 2px 9px; border-radius: 20px; }
.dato { margin-bottom: 6px; } .dato .k { font-size: 7pt; letter-spacing: .1em; text-transform: uppercase; color: #9ca3af; font-weight: 700; } .dato .v { font-size: 8.8pt; color: #111827; }
.ref { margin-bottom: 7px; } .ref .t { font-weight: 700; font-size: 9pt; color: #111827; } .ref .s { font-size: 8.3pt; color: #4b5563; word-break: break-word; }
</style>
<style>.nombre{letter-spacing:-.04em!important}h2{border-left:5px solid {{ $ac }};padding-left:10px}.foto{border-radius:4px!important}</style>
</head>
<body>
<div class="hoja">
<div class="col-fondo"></div>
<div class="fit">
    <header class="cab">
        <div class="txt">
            <div class="nombre">{{ $nombres }}<span class="a">{{ $apellidos }}</span></div>
            @if ($vt['profesion'])<div class="puesto">{{ $vt['profesion'] }}</div>@endif
        </div>
        @if ($foto)<img class="foto" src="{{ $foto }}" alt="">@endif
    </header>

    <div class="cuerpo">
        <main class="main">
            @if ($vt['perfil'])<h2><span class="ic">{!! $I('id') !!}</span>{{ $t('Sobre mí') }}</h2><p class="perfil">{{ $vt['perfil'] }}</p>@endif

            @if ($experiencias->isNotEmpty())
                <h2><span class="ic">{!! $I('cal') !!}</span>{{ $t('Experiencia') }}</h2>
                @foreach ($experiencias as $e)
                    <div class="it"><div class="t">{{ $e->cargo ?: $t('Experiencia') }}@if ($e->periodoTexto($lang))<span class="f">{{ $e->periodoTexto($lang) }}</span>@endif</div>
                        @if ($e->empresa)<div class="s">{{ $e->empresa }}</div>@endif
                        @if ($e->descripcion)<div class="d">{{ $e->descripcion }}</div>@endif</div>
                @endforeach
            @endif

            @if ($estudios->isNotEmpty())
                <h2><span class="ic">{!! $I('flag') !!}</span>{{ $t('Formación') }}</h2>
                @foreach ($estudios as $e)
                    <div class="it"><div class="t">{{ $e->titulo ?: $t($e->nivelLegible()) }}@if ($e->anio)<span class="f">{{ $e->anio }}</span>@endif</div>
                        <div class="s">{{ $t($e->nivelLegible()) }}@if ($e->institucion) · {{ $e->institucion }}@endif</div>
                        @if ($e->detalle)<div class="d">{{ $e->detalle }}</div>@endif</div>
                @endforeach
            @endif

            @if ($referencias->isNotEmpty())
                <h2><span class="ic">{!! $I('tel') !!}</span>{{ $t('Referencias') }}</h2>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 16px">
                @foreach ($referencias as $r)<div class="ref"><div class="t">{{ $r->nombreConTratamiento() }}</div><div class="s">{{ collect([$r->telefono ? $t('Tel.') . ' ' . $r->telefono : null, $r->correo])->filter()->implode(' · ') }}</div></div>@endforeach
                </div>
            @endif
        </main>

        <aside class="lado">
            @if (count($contactos) || count($enlaces))
                <h2>{{ $t('Contacto') }}</h2>
                <ul class="con">@foreach (array_merge($contactos, $enlaces) as [$ico, $x])<li>{!! $I($ico) !!}<span>{{ $x }}</span></li>@endforeach</ul>
            @endif

            @if ($habilidades->isNotEmpty())
                <h2>{{ $t('Habilidades') }}</h2>
                <div class="chips">@foreach ($habilidades as $h)<span>{{ $h->habilidad }}</span>@endforeach</div>
            @endif

            @if ($idiomas->isNotEmpty())
                <h2>{{ $t('Idiomas') }}</h2>
                @include('cv._idiomas', ['on' => $lat, 'off' => '#d7dce4', 'texto' => '#111827', 'nivel' => '#6b7280'])
            @endif

            @if (count($datos))
                <h2>{{ $t('Datos personales') }}</h2>
                @foreach ($datos as [$k, $v])<div class="dato"><div class="k">{{ $k }}</div><div class="v">{{ $v }}</div></div>@endforeach
            @endif

            @if (count($familia))
                <h2>{{ $t('Familia') }}</h2>
                @foreach ($familia as [$k, $v])<div class="dato"><div class="k">{{ $k }}</div><div class="v">{{ $v }}</div></div>@endforeach
            @endif
        </aside>
    </div>
</div>
</div>
@include('cv._una-hoja')
</body>
</html>
