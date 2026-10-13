{{-- Estilos comunes de las plantillas Pro (hoja carta fija, una sola página) --}}
@php $pantalla = $modoPantalla ?? false; @endphp
<style>
{!! $fuentesCss !!}
@page { size: 8.5in 11in; margin: 0; }
* { box-sizing: border-box; margin: 0; padding: 0; }
html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
html, body { background: #fff; }
body { font-family: 'CvSans', 'Montserrat', Arial, sans-serif; color: #2b2f38; font-size: 9.6pt; line-height: 1.5; }
.hoja { position: relative; width: 8.5in; height: 11in; overflow: hidden; background: #fff; }
.fit { position: relative; }
@if ($pantalla)
html { background: #525659; }
body { padding: 24px 0; background: #525659; }
.hoja { margin: 0 auto; box-shadow: 0 4px 18px rgba(0,0,0,.35); }
@media (max-width: 860px) { body { zoom: .45; } }
@endif
</style>
