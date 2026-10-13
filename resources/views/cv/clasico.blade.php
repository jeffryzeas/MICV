@php
    $pantalla = $modoPantalla ?? false;
    $pt = fn ($v) => $v . 'pt';
    // Márgenes del texto (pt). La hoja va sin margen (@page margin:0) y el
    // espacio de arriba/abajo de CADA página lo dan thead/tfoot, que Chromium
    // repite en todas las páginas. Así el marco "fixed" cubre la hoja entera.
    $mTop = 46; $mBot = 44; $mLado = 50;
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
<meta charset="utf-8">
<title>CV — {{ $c->nombreCompleto() }}</title>
<style>
{!! $fuentesCss !!}
@page { size: 8.5in 11in; margin: 0; }
* { box-sizing: border-box; margin: 0; padding: 0; }
html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body {
    font-family: 'CvNarrow', 'Arial Narrow', Arial, sans-serif;
    font-size: {{ $pt($cv['fuente_texto_tam']) }};
    color: {{ $cv['color_texto'] }};
    line-height: 1.35;
}
/* Marco y marca de agua: "fixed" se repite en CADA página del PDF */
.fijo { position: fixed; pointer-events: none; }
.marco {
    top: {{ $cv['marco_margen'] }}pt; bottom: {{ $cv['marco_margen'] }}pt;
    left: {{ $cv['marco_margen'] }}pt; right: {{ $cv['marco_margen'] }}pt;
    border: {{ $pt($cv['marco_externo_grosor']) }} solid {{ $cv['marco_externo_color'] }};
}
.marco::after {
    content: ""; position: absolute;
    inset: {{ $pt($cv['marco_separacion']) }};
    border: {{ $pt($cv['marco_interno_grosor']) }} solid {{ $cv['marco_interno_color'] }};
}
.agua {
    top: 0; bottom: 0; left: 0; right: 0;
    display: flex; align-items: center; justify-content: center;
    z-index: -1;
}
.agua img { width: {{ $cv['marca_agua_ancho_pct'] }}%; opacity: {{ $cv['marca_agua_opacidad'] / 100 }}; }

.cabecera { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8pt; }
.cabecera .escudo { width: {{ $pt($cv['escudo_tam']) }}; height: {{ $pt($cv['escudo_tam']) }}; object-fit: contain; }
.cabecera .titulo {
    flex: 1; text-align: center;
    font-family: 'CvScript', 'Edwardian Script ITC', 'Brush Script MT', cursive;
    font-size: {{ $pt($cv['fuente_titulo_tam']) }}; color: {{ $cv['color_titulo'] }}; line-height: 1.1;
}
.seccion {
    font-size: {{ $pt($cv['fuente_seccion_tam']) }};
    font-weight: {{ $cv['seccion_negrita'] ? 'bold' : 'normal' }};
    text-decoration: {{ $cv['seccion_subrayado'] ? 'underline' : 'none' }};
    text-underline-offset: 3pt;
    color: {{ $cv['color_seccion'] }};
    text-transform: uppercase;
    margin: 12pt 0 6pt;
    break-after: avoid;
}
table { width: 100%; border-collapse: collapse; }
table.pagina > thead > tr > td, table.pagina > tfoot > tr > td { padding: 0; }
table.pagina > tbody > tr > td.cuerpo { padding: 0 {{ $mLado }}pt; }
.esp-top { height: {{ $mTop }}pt; } .esp-bot { height: {{ $mBot }}pt; }
td { vertical-align: top; padding: 1.6pt 2pt; }
td.et { width: {{ $cv['etiqueta_ancho_pct'] }}%; font-weight: {{ $cv['etiqueta_negrita'] ? 'bold' : 'normal' }}; }
.contenido tr { break-inside: avoid; }
.personales { display: flex; gap: 12pt; align-items: flex-start; }
.personales table { flex: 1; }
.foto {
    width: {{ $pt($cv['foto_ancho']) }}; height: {{ $pt($cv['foto_alto']) }};
    object-fit: cover; flex-shrink: 0;
    border: {{ $pt($cv['foto_borde']) }} solid {{ $colores['primario'] }};
}
ul { margin: 2pt 0 2pt 16pt; font-size: {{ $pt($cv['fuente_vineta_tam']) }}; }
li { padding-bottom: 3.5pt; break-inside: avoid; }
.nivel { font-weight: bold; color: {{ $colores['primario'] }}; }

@if ($pantalla)
/* Vista previa en pantalla: simula la hoja carta */
html { background: #525659; }
body { padding: 24px 0; }
.hoja {
    position: relative; width: 8.5in; min-height: 11in; margin: 0 auto;
    background: #fff; box-shadow: 0 4px 18px rgba(0,0,0,.35);
}
.fijo { position: absolute; }
.agua { z-index: 0; } .contenido { position: relative; z-index: 1; }
@media (max-width: 860px) { body { zoom: .45; } }
@endif
</style>
</head>
<body>
<div class="hoja">
    @if ($cv['marca_agua_activa'] && $escudo)<div class="fijo agua"><img src="{{ $escudo }}" alt=""></div>@endif
    @if ($cv['marco_activo'])<div class="fijo marco"></div>@endif

    <table class="pagina">
    <thead><tr><td><div class="esp-top"></div></td></tr></thead>
    <tfoot><tr><td><div class="esp-bot"></div></td></tr></tfoot>
    <tbody><tr><td class="cuerpo">
    <div class="contenido">
        <div class="cabecera">
            @if ($cv['mostrar_escudo'] && $escudo)<img class="escudo" src="{{ $escudo }}" alt="">@else<span class="escudo"></span>@endif
            <div class="titulo">{{ $lang === 'en' ? 'Curriculum Vitae' : 'Currículum Vítae' }}</div>
            @if ($cv['mostrar_escudo'] && $escudo)<img class="escudo" src="{{ $escudo }}" alt="">@else<span class="escudo"></span>@endif
        </div>

        @if (count($personales))
            <div class="seccion">{{ $t('Datos personales') }}</div>
            <div class="personales">
                <table>
                    @foreach ($personales as [$et, $val])
                        <tr><td class="et">{{ $et }}:</td><td>{{ $val }}</td></tr>
                    @endforeach
                </table>
                @if ($foto)<img class="foto" src="{{ $foto }}" alt="">@endif
            </div>
        @endif

        @if (count($familia))
            <div class="seccion">{{ $t('Datos familiares') }}</div>
            <table>
                @foreach ($familia as [$et, $val])
                    <tr><td class="et">{{ $et }}:</td><td>{{ $val }}</td></tr>
                @endforeach
            </table>
        @endif

        @if ($estudios->isNotEmpty())
            <div class="seccion">{{ $t('Información académica') }}</div>
            <ul>
                @foreach ($estudios as $e)
                    <li><span class="nivel">{{ $t($e->nivelLegible()) }}:</span> {{ $e->lineaCv($lang) }}</li>
                @endforeach
            </ul>
        @endif

        @if ($experiencias->isNotEmpty())
            <div class="seccion">{{ $t('Experiencia laboral') }}</div>
            <ul>@foreach ($experiencias as $e)<li>{{ $e->lineaCv($lang) }}</li>@endforeach</ul>
        @endif

        @if ($habilidades->isNotEmpty())
            <div class="seccion">{{ $t('Habilidades') }}</div>
            <ul>@foreach ($habilidades as $h)<li>{{ $h->habilidad }}</li>@endforeach</ul>
        @endif

        @if ($idiomas->isNotEmpty())
            <div class="seccion">{{ $t('Idiomas') }}</div>
            <ul>@foreach ($idiomas as $x)<li><span class="nivel">{{ $x->nombre($lang) }}:</span> {{ $x->nivelTexto($lang) }}</li>@endforeach</ul>
        @endif

        @if ($referencias->isNotEmpty())
            <div class="seccion">{{ $t('Referencias personales') }}</div>
            <ul>@foreach ($referencias as $r)<li>{{ $r->lineaCv() }}</li>@endforeach</ul>
        @endif
    </div>
    </td></tr></tbody>
    </table>
</div>
</body>
</html>
