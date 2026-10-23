@php
    $lat = $paleta['lateral']; $ac = $paleta['acento']; $suave = $paleta['suave'];
    $I = fn ($k) => \App\Support\Iconos::svg($k);
    $tel = fn ($n) => trim(($c->telefono_codigo_pais ? $c->telefono_codigo_pais . ' ' : '') . $n);
    $datos = $datosPro;
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
<meta charset="utf-8">
<title>CV — {{ $c->nombreCompleto() }}</title>
@include('cv._base-pro')
<style>
.lado-fondo { position: absolute; top: 0; bottom: 0; right: 0; width: 35%; background: #f4f6f9; }
.fit { display: flex; flex-direction: column; }
.cab { background: {{ $lat }}; color: #fff; padding: 0.5in 0.55in 0.42in; display: flex; align-items: center; gap: 0.35in; position: relative; }
.cab::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 6px; background: {{ $ac }}; }
.cab .txt { flex: 1; min-width: 0; }
.cab .nombre { font-size: 28pt; font-weight: 800; line-height: 1.05; letter-spacing: .01em; }
.cab .nombre span { font-weight: 800; display: block; }
.cab .puesto { margin-top: 8px; font-size: 10pt; font-weight: 600; letter-spacing: .28em; text-transform: uppercase; color: {{ $ac }}; }
.cab .contacto { margin-top: 12px; display: flex; flex-wrap: wrap; gap: 6px 18px; font-size: 8.6pt; color: #e8edf5; }
.cab .contacto span { display: inline-flex; align-items: center; gap: 6px; }
.cab .contacto svg { width: 12px; height: 12px; color: {{ $ac }}; }
.cab .foto { width: 1.45in; height: 1.45in; border-radius: 50%; object-fit: cover; border: 4px solid {{ $ac }}; flex-shrink: 0; }
.cab .ini { width: 1.45in; height: 1.45in; border-radius: 50%; border: 4px solid {{ $ac }}; display: flex; align-items: center; justify-content: center; font-size: 34pt; font-weight: 800; flex-shrink: 0; }
.cuerpo { display: flex; flex: 1; }
.main { width: 65%; padding: 0.32in 0.4in 0.35in 0.55in; }
.lado { width: 35%; padding: 0.32in 0.4in 0.35in 0.32in; }
h2 { font-size: 10.5pt; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: {{ $lat }}; margin: 0.18in 0 9px; display: flex; align-items: center; gap: 8px; }
h2::before { content: ""; width: 14px; height: 14px; border-radius: 3px; background: {{ $ac }}; transform: rotate(45deg) scale(.7); }
.main > h2:first-child, .lado > h2:first-child { margin-top: 0; }
.perfil { font-size: 9.6pt; color: #444a55; text-align: justify; }
.item { display: grid; grid-template-columns: 1.05in 1fr; gap: 10px; margin-bottom: 11px; }
.item .f { font-size: 8pt; font-weight: 700; color: {{ $ac }}; padding-top: 2px; }
.item .t { font-weight: 700; font-size: 10pt; color: #1f2430; }
.item .s { font-size: 8.8pt; color: {{ $lat }}; font-weight: 600; }
.item .d { font-size: 9pt; color: #4a505c; margin-top: 2px; }
.dato { margin-bottom: 7px; } .dato .k { font-size: 7.2pt; text-transform: uppercase; letter-spacing: .1em; color: #7a8190; font-weight: 600; } .dato .v { font-size: 9pt; color: #1f2430; }
.chips { display: flex; flex-wrap: wrap; gap: 6px; }
.chips span { font-size: 8.4pt; background: {{ $suave }}; color: {{ $lat }}; border: 1px solid {{ $ac }}55; padding: 3px 9px; border-radius: 12px; font-weight: 500; }
.ref { margin-bottom: 9px; } .ref .t { font-weight: 700; font-size: 9.2pt; color: #1f2430; } .ref .s { font-size: 8.4pt; color: #5d6472; word-break: break-word; }
</style>
</head>
<body>
<div class="hoja">
<div class="lado-fondo"></div>
<div class="fit">
    <header class="cab">
        <div class="txt">
            <div class="nombre"><span>{{ trim($c->primer_nombre . ' ' . $c->segundo_nombre) }}</span>{{ trim($c->primer_apellido . ' ' . $c->segundo_apellido) }}</div>
            @if ($vt['profesion'])<div class="puesto">{{ $vt['profesion'] }}</div>@endif
            <div class="contacto">
                @foreach (array_merge($contactos, $enlaces) as [$ico, $txt])<span>{!! $I($ico) !!}{{ $txt }}</span>@endforeach
            </div>
        </div>
        @if ($foto)<img class="foto" src="{{ $foto }}" alt="">@else<div class="ini">{{ $c->iniciales() }}</div>@endif
    </header>

    <div class="cuerpo">
        <main class="main">
            @if ($vt['perfil'])<h2>{{ $t('Perfil profesional') }}</h2><p class="perfil">{{ $vt['perfil'] }}</p>@endif

            @if ($experiencias->isNotEmpty())
                <h2>{{ $t('Experiencia laboral') }}</h2>
                @foreach ($experiencias as $e)
                    <div class="item"><div class="f">{{ $e->periodoTexto($lang) }}</div>
                        <div><div class="t">{{ $e->cargo ?: $t('Experiencia') }}</div>@if ($e->empresa)<div class="s">{{ $e->empresa }}</div>@endif
                        @if ($e->descripcion)<div class="d">{{ $e->descripcion }}</div>@endif</div></div>
                @endforeach
            @endif

            @if ($estudios->isNotEmpty())
                <h2>{{ $t('Formación académica') }}</h2>
                @foreach ($estudios as $e)
                    <div class="item"><div class="f">{{ $e->anio }}</div>
                        <div><div class="t">{{ $e->titulo ?: $t($e->nivelLegible()) }}</div><div class="s">{{ $t($e->nivelLegible()) }}@if ($e->institucion) · {{ $e->institucion }}@endif</div>
                        @if ($e->detalle)<div class="d">{{ $e->detalle }}</div>@endif</div></div>
                @endforeach
            @endif
        </main>
        <aside class="lado">
            @if (count($datos))<h2>{{ $t('Datos personales') }}</h2>@foreach ($datos as [$k, $v])<div class="dato"><div class="k">{{ $k }}</div><div class="v">{{ $v }}</div></div>@endforeach @endif
            @if ($habilidades->isNotEmpty())<h2>{{ $t('Habilidades') }}</h2><div class="chips">@foreach ($habilidades as $h)<span>{{ $h->habilidad }}</span>@endforeach</div>@endif
            @if ($idiomas->isNotEmpty())<h2>{{ $t('Idiomas') }}</h2>@include('cv._idiomas', ['on' => $ac, 'off' => '#dfe3ea', 'texto' => '#1f2430', 'nivel' => '#6b7280'])@endif
            @if ($referencias->isNotEmpty())
                <h2>{{ $t('Referencias') }}</h2>
                @foreach ($referencias as $r)<div class="ref"><div class="t">{{ $r->nombreConTratamiento() }}</div>
                    @if ($r->telefono)<div class="s">{{ $t('Tel.') }} {{ $r->telefono }}</div>@endif @if ($r->correo)<div class="s">{{ $r->correo }}</div>@endif</div>@endforeach
            @endif
            @if (count($familia))<h2>{{ $t('Familia') }}</h2>@foreach ($familia as [$k, $v])<div class="dato"><div class="k">{{ $k }}</div><div class="v">{{ $v }}</div></div>@endforeach @endif
        </aside>
    </div>
</div>
</div>
@include('cv._una-hoja')
</body>
</html>
