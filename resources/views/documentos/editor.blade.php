<x-layout titulo="Editor y copias de documentos" activo="documentos">
<style>
/* Editor de documentos – alineado al tema del sistema */
.doc-app { --dc: rgb(var(--jz-azul-800)); --accent: #0f7e79; color: #223349; }
.doc-head { display:flex; justify-content:space-between; gap:16px; align-items:center; margin-bottom:18px; }
.doc-head h2 { font-size:1.5rem; font-weight:800; color: rgb(var(--jz-azul-800)); }
.doc-head p { font-size:0.875rem; color:#68798a; margin-top:3px; }
.doc-actions { display:flex; gap:8px; flex-wrap:wrap; }
.doc-btn { border:1px solid #cbd5df; background:#fff; color:#173e62; border-radius:10px; padding:10px 14px; font:600 13px inherit; cursor:pointer; transition: all .15s; }
.doc-btn:hover { border-color: rgb(var(--jz-azul-800)); }
.doc-btn.primary { background: rgb(var(--jz-azul-800)); color:white; border-color: rgb(var(--jz-azul-800)); }
.doc-btn.teal { background:#0f7e79; color:white; border-color:#0f7e79; }
.doc-btn:disabled { opacity:.45; cursor:default; }
.doc-grid { display:grid; grid-template-columns: minmax(0,1.65fr) minmax(280px,1fr); gap:18px; }
@media (max-width:900px){ .doc-grid { grid-template-columns:1fr; } }
.doc-panel { background:white; border:1px solid #e5e7eb; border-radius:1rem; padding:18px; box-shadow:0 1px 3px rgb(0 0 0 / 0.05); }
.doc-panel h3 { font-size:15px; font-weight:800; margin-bottom:12px; color: rgb(var(--jz-azul-800)); }
.doc-controls { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:12px 0; }
.doc-tabs { display:flex; gap:8px; margin-bottom:12px; }
.doc-tab { border:1px solid #e5e7eb; background:#fff; border-radius:8px; padding:6px 12px; font-weight:600; font-size:13px; cursor:pointer; }
.doc-tab[aria-pressed=true] { background:#e7f4f2; border-color:#0f7e79; color:#08605c; }
.doc-stage { background:#f3f4f6; border:1px solid #e5e7eb; border-radius:12px; min-height:280px; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.doc-stage canvas { max-width:100%; max-height:68vh; width:auto; height:auto; display:block; touch-action:none; outline-offset:-3px; cursor:crosshair; }
.doc-empty { text-align:center; padding:44px 22px; color:#728296; font-size:14px; }
.doc-fields { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.doc-field { display:block; font-size:12px; font-weight:700; color:#526578; }
.doc-field input, .doc-field select { width:100%; border:1px solid #d1d5db; border-radius:8px; padding:8px; margin-top:5px; font-size:14px; background:#fff; color:#20364a; }
.doc-field input:focus, .doc-field select:focus { outline:2px solid rgb(var(--jz-azul-800)); outline-offset:0; border-color:transparent; }
.doc-field input[type=range] { padding:0; border:none; }
.doc-wide { grid-column:1/-1; }
.doc-help { font-size:12px; color:#6a7c8d; line-height:1.5; }
.doc-sheet-wrap { background:#f3f4f6; border-radius:12px; padding:18px; margin-top:12px; }
.doc-sheet { position:relative; background:#fff; width:100%; aspect-ratio:215.9/279.4; box-shadow:0 3px 12px rgb(23 62 98 / 0.12); }
.doc-sheet img { position:absolute; object-fit:fill; outline:1px solid #eef0f3; }
.doc-message { margin-top:12px; font-size:13px; min-height:20px; color:#0f6f67; }
.doc-message.error { color:#b42318; }
.doc-filter-row { border-top:1px solid #e5eaf0; margin-top:14px; padding-top:14px; }
.doc-result { max-width:100%; max-height:160px; object-fit:contain; border-radius:8px; }
.doc-tip { font-size:12px; color:#5a6a7a; background:#f0f5fb; border-radius:8px; padding:10px 12px; margin:10px 0; }
.dark .doc-panel { background:#182233; border-color:#2a3648; }
.dark .doc-btn { background:#101826; color:#e5ebf3; border-color:#2a3648; }
.dark .doc-stage { background:#1c2838; border-color:#2a3648; }
.dark .doc-field input, .dark .doc-field select { background:#101826; color:#e5ebf3; border-color:#2a3648; }
</style>
<section class="doc-app" id="doc-app">
<div class="doc-head"><div><div class="doc-kicker">Documentos e impresión</div><h2>Enderezar, mejorar y copiar</h2><p>Vista en vivo: mueva las esquinas, ajuste filtros y defina el tamaño en centímetros.</p></div><div class="doc-actions"><button type="button" id="doc-upload" class="doc-btn primary"><x-icono name="image-plus"/> Subir fotos del documento</button><input id="doc-files" type="file" accept="image/jpeg,image/png,image/webp" multiple class="doc-hidden-file" aria-label="Elegir una o dos fotos del documento"><button id="doc-clear" class="doc-btn"><x-icono name="trash-2"/> Limpiar</button></div></div>
<div class="doc-upload-zone" id="doc-drop"><x-icono name="upload"/><div><strong>Suba el frente y el reverso</strong><p>Arrastre aquí hasta dos fotos o use el botón Subir fotos del documento.</p><span id="doc-file-status" role="status">JPG, PNG o WebP · hasta 50 MB por foto</span></div><button type="button" id="doc-upload-zone" class="doc-btn primary"><x-icono name="folder-open"/> Elegir fotos</button></div><div class="doc-grid"><div class="doc-panel"><div class="doc-tabs" id="doc-tabs"></div><div class="doc-controls"><button type="button" class="doc-btn" id="doc-undo"><x-icono name="undo-2"/> Deshacer</button><button type="button" class="doc-btn" id="doc-redo"><x-icono name="redo-2"/> Rehacer</button><button type="button" class="doc-btn" id="doc-swap"><x-icono name="arrow-left-right"/> Intercambiar caras</button><button type="button" class="doc-btn" id="doc-remove"><x-icono name="image-minus"/> Quitar esta cara</button></div><h3>1. Marque las cuatro esquinas</h3><p class="doc-help">1 arriba izquierda · 2 arriba derecha · 3 abajo derecha · 4 abajo izquierda. Arrastre cada punto hasta la esquina del documento. Si la foto está de lado, gírela primero.</p>
<div class="doc-controls"><button class="doc-btn" id="doc-rotate-left"><x-icono name="rotate-ccw"/> Girar 90°</button><button class="doc-btn" id="doc-rotate-right"><x-icono name="rotate-cw"/> Girar 90°</button><button class="doc-btn" id="doc-reset">Restablecer recorte</button><button class="doc-btn teal" id="doc-crop">Actualizar vista</button></div>
<div class="doc-stage"><canvas id="doc-canvas" hidden tabindex="0" aria-label="Documento: arrastre las cuatro esquinas. Use flechas para mover el punto seleccionado."></canvas><div class="doc-empty" id="doc-empty">Cargue la foto del frente y, si lo necesita, la del reverso.<br>JPG, PNG o WebP · máximo 50 MB por foto.</div></div>
<div class="doc-filter-row"><h3>2. Mejore la imagen</h3><div class="doc-fields">
<label class="doc-field doc-wide">Filtro<select id="doc-filter"><option value="color">Color original</option><option value="gris">Escala de grises</option><option value="bn">Blanco y negro</option><option value="documento">Documento: aclarar sombras</option></select></label>
<label class="doc-field"><span class="doc-range-label">Brillo <output id="doc-bright-val">100%</output></span><input id="doc-bright" type="range" min="60" max="160" value="100"></label>
<label class="doc-field"><span class="doc-range-label">Contraste <output id="doc-contrast-val">100%</output></span><input id="doc-contrast" type="range" min="50" max="200" value="100"></label>
<label class="doc-field doc-wide"><span class="doc-range-label">Umbral de blanco y negro <output id="doc-threshold-val">150</output></span><input id="doc-threshold" type="range" min="30" max="230" value="150"></label>
</div><div class="doc-controls"><button class="doc-btn" id="doc-filter-reset">Restablecer filtros</button><button class="doc-btn" id="doc-jpg">Descargar foto JPG</button><button class="doc-btn" id="doc-png">Descargar foto PNG</button></div><img id="doc-result" class="doc-result" hidden alt="Vista previa de la foto corregida"></div>
</div>
<aside class="doc-panel"><h3>3. Medidas y hoja de impresión</h3><div class="doc-fields">
<label class="doc-field doc-wide">Formato del documento<select id="doc-preset"><option value="tarjeta">Tarjeta: 8,56 × 5,4 cm</option><option value="pasaporte">Pasaporte: 12,5 × 8,8 cm</option><option value="a5">Media hoja: 14,8 × 21 cm</option><option value="libre">Medidas personalizadas</option></select></label>
<label class="doc-field">Ancho (cm)<input id="doc-width" type="number" min="1" max="50" step="0.01" value="8.56"></label><label class="doc-field">Alto (cm)<input id="doc-height" type="number" min="1" max="50" step="0.01" value="5.4"></label>
<label class="doc-field doc-wide">Papel<select id="doc-paper"><option value="carta">Carta · 21,59 × 27,94 cm</option><option value="a4">A4 · 21 × 29,7 cm</option></select></label>
<label class="doc-field">Márgenes (cm)<input id="doc-margin" type="number" min="0" max="5" step="0.1" value="2"></label><label class="doc-field">Separación (cm)<input id="doc-gap" type="number" min="0" max="6" step="0.1" value="1.2"></label>
<label class="doc-help doc-wide"><input id="doc-fit" type="checkbox"> Ajustar a la hoja si las medidas no caben</label>
</div><div class="doc-tip">Para una copia a tamaño real, confirme las medidas de su documento e imprima el PDF al <b>100% / tamaño real</b>, sin “Ajustar a página”. El tamaño de tarjeta es una referencia editable.</div>
<div class="doc-sheet-wrap"><div id="doc-sheet" class="doc-sheet" aria-label="Vista previa de la hoja"></div></div><p class="doc-help" id="doc-size-info" style="margin-top:10px">Las dos caras se colocan una debajo de la otra.</p>
<div class="doc-controls"><button class="doc-btn primary" id="doc-pdf"><x-icono name="file-down"/> Descargar PDF</button><button class="doc-btn" id="doc-print"><x-icono name="printer"/> Abrir PDF para imprimir</button></div><p class="doc-help">Las fotos de esta sesión no se guardan en el historial.</p></aside></div>
<p class="doc-message" id="doc-message" role="status" aria-live="polite"></p>
</section>
@push('scripts')
<script src="{{ asset('vendor/pdf-lib.min.js') }}" defer></script>
<script src="{{ asset('js/documentos-core.js') }}" defer></script>
<script src="{{ asset('js/documentos-editor.js') }}" defer></script>
@endpush
</x-layout>
