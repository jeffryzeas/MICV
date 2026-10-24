@php
    $lat = $paleta['lateral']; $ac = $paleta['acento']; $suave = $paleta['suave'];
    $nombres = trim($c->primer_nombre . ' ' . $c->segundo_nombre);
    $apellidos = trim($c->primer_apellido . ' ' . $c->segundo_apellido);
    $I = fn ($k) => \App\Support\Iconos::svg($k);
    $tel = fn ($n) => trim(($c->telefono_codigo_pais ? $c->telefono_codigo_pais . ' ' : '') . $n);
    $datosLat = $datosPro;
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
<meta charset="utf-8">
<title>CV — {{ $c->nombreCompleto() }}</title>
@include('cv._base-pro')
<style>
.lat-fondo { position: absolute; top: 0; bottom: 0; left: 0; width: 32.5%; background: {{ $lat }}; }
.cv { display: flex; align-items: stretch; min-height: inherit; }
.lat { width: 32.5%; flex-shrink: 0; color: #e9edf4; padding: 0.5in 0.3in 0.35in; }
.cont { flex: 1; padding: 0.55in 0.5in 0.35in 0.42in; min-width: 0; }
.foto-wrap { width: 1.6in; height: 1.6in; margin: 0 auto 0.26in; border-radius: 50%; padding: 4px; background: {{ $ac }}; }
.foto-wrap img, .foto-wrap .ini { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 3px solid {{ $lat }}; display: block; }
.foto-wrap .ini { display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,.08); color: #fff; font-weight: 800; font-size: 34pt; }
.lat h3 { font-size: 9pt; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; color: #fff; margin: 0.24in 0 0.1in; padding-bottom: 5px; border-bottom: 1.5px solid rgba(255,255,255,.18); position: relative; }
.lat h3::after { content: ""; position: absolute; left: 0; bottom: -1.5px; width: 34px; height: 1.5px; background: {{ $ac }}; }
.contacto { list-style: none; }
.contacto li { display: flex; gap: 9px; align-items: flex-start; margin-bottom: 7px; font-size: 8.6pt; word-break: break-word; }
.contacto .i { width: 21px; height: 21px; border-radius: 50%; background: {{ $ac }}; color: {{ $lat }}; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.contacto .i svg { width: 12px; height: 12px; }
.dato { margin-bottom: 7px; }
.dato .k { font-size: 7pt; text-transform: uppercase; letter-spacing: .12em; color: {{ $ac }}; font-weight: 600; }
.dato .v { font-size: 8.8pt; color: #fff; }
.habs { list-style: none; }
.habs li { position: relative; padding-left: 15px; margin-bottom: 6px; font-size: 8.8pt; }
.habs li::before { content: ""; position: absolute; left: 0; top: 5.5px; width: 7px; height: 7px; border-radius: 2px; background: {{ $ac }}; transform: rotate(45deg); }
.nombre { font-size: 27pt; line-height: 1.05; color: {{ $lat }}; text-transform: uppercase; letter-spacing: .02em; }
.nombre .n, .nombre .a { font-weight: 800; display: block; }
.puesto { margin-top: 9px; display: inline-block; font-size: 9.5pt; font-weight: 600; letter-spacing: .24em; text-transform: uppercase; color: #fff; background: {{ $ac }}; padding: 4px 12px; border-radius: 3px; }
.linea { height: 3px; width: 70px; background: {{ $ac }}; margin: 16px 0 4px; border-radius: 2px; }
.sec { margin-top: 0.22in; }
.sec h2 { display: flex; align-items: center; gap: 10px; font-size: 11pt; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: {{ $lat }}; margin-bottom: 10px; }
.sec h2::after { content: ""; flex: 1; height: 1.5px; background: #e2e5ea; }
.sec h2 .bola { width: 10px; height: 10px; border-radius: 50%; background: {{ $ac }}; box-shadow: 0 0 0 3px {{ $suave }}; flex-shrink: 0; }
.perfil { font-size: 9.6pt; color: #444a55; text-align: justify; background: {{ $suave }}; border-left: 3px solid {{ $ac }}; padding: 10px 14px; border-radius: 0 6px 6px 0; }
.tl { position: relative; padding-left: 18px; }
.tl::before { content: ""; position: absolute; left: 4px; top: 6px; bottom: 6px; width: 1.5px; background: #dfe3ea; }
.item { position: relative; margin-bottom: 11px; }
.item::before { content: ""; position: absolute; left: -18px; top: 4px; width: 9px; height: 9px; border-radius: 50%; background: #fff; border: 2px solid {{ $ac }}; }
.item .t { font-weight: 700; font-size: 10pt; color: #1f2430; }
.item .s { font-size: 8.8pt; color: #5d6472; margin-top: 1px; } .item .s b { color: {{ $lat }}; font-weight: 600; }
.item .fecha { display: inline-block; font-size: 7.6pt; font-weight: 600; color: {{ $lat }}; background: {{ $suave }}; padding: 1px 8px; border-radius: 10px; margin-left: 6px; }
.item .d { font-size: 9pt; color: #4a505c; margin-top: 3px; }
.refs { display: grid; grid-template-columns: 1fr 1fr; gap: 9px 14px; }
.ref { border: 1px solid #e6e9ee; border-top: 3px solid {{ $ac }}; border-radius: 6px; padding: 8px 11px; }
.ref .t { font-weight: 700; font-size: 9.4pt; color: #1f2430; } .ref .s { font-size: 8.5pt; color: #5d6472; word-break: break-word; }
.fam { font-size: 9pt; color: #4a505c; } .fam b { color: #1f2430; font-weight: 600; }
</style>
<style>.foto{border-radius:50%!important;border:5px solid {{ $ac }}!important}.nombre{font-size:25pt!important}h2{letter-spacing:.12em}</style>
<style>.foto-wrap{width:1.45in;height:1.45in;padding:7px;border-radius:30%}.foto-wrap img,.foto-wrap .ini{border-radius:30%}.lat h2{letter-spacing:.16em}</style></head>
<body>
<div class="hoja">
<div class="lat-fondo"></div>
<div class="fit">
<div class="cv">
    <aside class="lat">
        <div class="foto-wrap">@if ($foto)<img src="{{ $foto }}" alt="">@else<div class="ini">{{ $c->iniciales() }}</div>@endif</div>

        <h3>{{ $t('Contacto') }}</h3>
        <ul class="contacto">
            @foreach ($contactos as [$ico, $txt])<li><span class="i">{!! $I($ico) !!}</span><span>{{ $txt }}</span></li>@endforeach
            @foreach ($enlaces as [$ico, $txt])<li><span class="i">{!! $I($ico) !!}</span><span>{{ $txt }}</span></li>@endforeach
        </ul>

        @if (count($datosLat))
            <h3>{{ $t('Datos personales') }}</h3>
            @foreach ($datosLat as [$k, $v])<div class="dato"><div class="k">{{ $k }}</div><div class="v">{{ $v }}</div></div>@endforeach
        @endif

        @if ($habilidades->isNotEmpty())
            <h3>{{ $t('Habilidades') }}</h3>
            <ul class="habs">@foreach ($habilidades as $h)<li>{{ $h->habilidad }}</li>@endforeach</ul>
        @endif

        @if ($idiomas->isNotEmpty())
            <h3>{{ $t('Idiomas') }}</h3>
            @include('cv._idiomas', ['on' => $ac, 'off' => 'rgba(255,255,255,.22)', 'texto' => '#fff', 'nivel' => '#c9d2e0'])
        @endif
    </aside>

    <main class="cont">
        <div class="nombre"><span class="n">{{ $nombres }}</span><span class="a">{{ $apellidos }}</span></div>
        @if ($vt['profesion'])<div class="puesto">{{ $vt['profesion'] }}</div>@else<div class="linea"></div>@endif

        @if ($vt['perfil'])
            <div class="sec"><h2><span class="bola"></span>{{ $t('Perfil profesional') }}</h2><div class="perfil">{{ $vt['perfil'] }}</div></div>
        @endif

        @if ($experiencias->isNotEmpty())
            <div class="sec"><h2><span class="bola"></span>{{ $t('Experiencia laboral') }}</h2><div class="tl">
                @foreach ($experiencias as $e)
                    <div class="item">
                        <div class="t">{{ $e->cargo ?: $t('Experiencia') }}@if ($e->periodoTexto($lang))<span class="fecha">{{ $e->periodoTexto($lang) }}</span>@endif</div>
                        @if ($e->empresa)<div class="s"><b>{{ $e->empresa }}</b></div>@endif
                        @if ($e->descripcion)<div class="d">{{ $e->descripcion }}</div>@endif
                    </div>
                @endforeach
            </div></div>
        @endif

        @if ($estudios->isNotEmpty())
            <div class="sec"><h2><span class="bola"></span>{{ $t('Formación académica') }}</h2><div class="tl">
                @foreach ($estudios as $e)
                    <div class="item">
                        <div class="t">{{ $e->titulo ?: $t($e->nivelLegible()) }}@if ($e->anio)<span class="fecha">{{ $e->anio }}</span>@endif</div>
                        <div class="s"><b>{{ $t($e->nivelLegible()) }}</b>@if ($e->institucion) · {{ $e->institucion }}@endif</div>
                        @if ($e->detalle)<div class="d">{{ $e->detalle }}</div>@endif
                    </div>
                @endforeach
            </div></div>
        @endif

        @if ($referencias->isNotEmpty())
            <div class="sec"><h2><span class="bola"></span>{{ $t('Referencias') }}</h2><div class="refs">
                @foreach ($referencias as $r)
                    <div class="ref"><div class="t">{{ $r->nombreConTratamiento() }}</div>
                        @if ($r->telefono)<div class="s">{{ $t('Tel.') }} {{ $r->telefono }}</div>@endif
                        @if ($r->correo)<div class="s">{{ $r->correo }}</div>@endif</div>
                @endforeach
            </div></div>
        @endif

        @if (count($familia))
            <div class="sec"><h2><span class="bola"></span>{{ $t('Datos familiares') }}</h2>
                <div class="fam">@foreach ($familia as [$k, $v])<div><b>{{ $k }}:</b> {{ $v }}</div>@endforeach</div></div>
        @endif
    </main>
</div>
</div>
</div>
@include('cv._una-hoja')
</body>
</html>
