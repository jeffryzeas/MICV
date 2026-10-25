<x-layout titulo="Editor y copias de documentos" activo="documentos">
<section class="doc-app" id="doc-app">
    <header class="panel mb-5">
        <div class="doc-head">
            <div><div class="doc-kicker">Documentos e impresión</div><h2>Enderezar, mejorar y copiar</h2><p>Prepare el frente y reverso, mejore la lectura y acomode sus copias en una hoja.</p></div>
            <div class="doc-actions">
                <x-ui.button id="doc-upload" icon="image-plus">Subir fotos</x-ui.button>
                <x-ui.button id="doc-clear" variant="secondary" icon="trash-2">Limpiar</x-ui.button>
                <input id="doc-files" type="file" accept="image/jpeg,image/png,image/webp" multiple class="doc-hidden-file" aria-label="Elegir una o dos fotos del documento">
            </div>
        </div>
        <nav class="flex flex-wrap gap-3 text-sm text-azul-800" aria-label="Pasos del editor">
            <a href="#doc-recorte" class="inline-flex items-center gap-2"><x-icono name="crop" class="size-4"/> 1. Recortar y enderezar</a>
            <a href="#doc-mejorar" class="inline-flex items-center gap-2"><x-icono name="sliders-horizontal" class="size-4"/> 2. Mejorar imagen</a>
            <a href="#doc-impresion" class="inline-flex items-center gap-2"><x-icono name="printer" class="size-4"/> 3. Preparar copias</a>
        </nav>
    </header>
    <div class="doc-upload-zone" id="doc-drop">
        <x-icono name="upload"/>
        <div><strong>Frente y reverso del documento</strong><p>Arrastre hasta dos fotos, selecciónelas o pegue una imagen con Ctrl+V.</p><span id="doc-file-status" role="status">JPG, PNG o WebP · hasta 50 MB por foto</span></div>
        <x-ui.button id="doc-upload-zone" icon="folder-open">Elegir fotos</x-ui.button>
    </div>
    <div class="doc-grid">
        <div class="doc-panel" id="doc-recorte">
            <div class="doc-tabs" id="doc-tabs" role="group" aria-label="Cara del documento"></div>
            <div class="doc-controls">
                <x-ui.button variant="secondary" id="doc-undo" icon="undo-2">Deshacer</x-ui.button>
                <x-ui.button variant="secondary" id="doc-redo" icon="redo-2">Rehacer</x-ui.button>
                <x-ui.button variant="secondary" id="doc-swap" icon="arrow-left-right">Intercambiar caras</x-ui.button>
                <x-ui.button variant="secondary" id="doc-remove" icon="image-minus">Quitar cara</x-ui.button>
            </div>
            <h3><x-icono name="crop" class="size-4"/> 1. Recorte y perspectiva</h3>
            <p class="doc-help">Coloque los puntos en las cuatro esquinas del documento. La vista corregida se actualiza al moverlos.</p>
            <div class="doc-controls">
                <x-ui.button variant="secondary" id="doc-rotate-left" icon="rotate-ccw">Girar izquierda</x-ui.button>
                <x-ui.button variant="secondary" id="doc-rotate-right" icon="rotate-cw">Girar derecha</x-ui.button>
                <x-ui.button variant="secondary" id="doc-reset" icon="maximize">Usar foto completa</x-ui.button>
                <x-ui.button id="doc-crop" icon="scan-line">Aplicar recorte</x-ui.button>
            </div>
            <div class="doc-fields mb-3">
                <label class="doc-field">Punto seleccionado<select id="doc-corner"><option value="0">1 · Superior izquierda</option><option value="1">2 · Superior derecha</option><option value="2">3 · Inferior derecha</option><option value="3">4 · Inferior izquierda</option></select></label>
                <label class="doc-field"><span class="doc-range-label">Zoom <output id="doc-zoom-val">100%</output></span><input id="doc-zoom" type="range" min="50" max="200" step="10" value="100"></label>
            </div>
            <div class="doc-stage"><canvas id="doc-canvas" hidden tabindex="0" aria-label="Documento: arrastre las esquinas; teclas 1 a 4 para elegir un punto y flechas para moverlo. Mayúsculas mueve diez píxeles."></canvas><div class="doc-empty" id="doc-empty"><x-icono name="scan-line" class="size-10 mx-auto mb-3"/><p>Suba o pegue la foto del documento para comenzar.</p></div></div>
            <p class="doc-help mt-2">Teclado: 1–4 elige la esquina · flechas ajustan · Mayús + flechas mueve más rápido. El zoom no cambia el tamaño de impresión.</p>
            <section class="doc-filter-row" id="doc-mejorar">
                <h3><x-icono name="sliders-horizontal" class="size-4"/> 2. Mejorar lectura</h3>
                <div class="doc-controls" role="group" aria-label="Ajustes rápidos">
                    <x-ui.button variant="secondary" id="doc-natural" icon="image">Natural</x-ui.button>
                    <x-ui.button variant="secondary" id="doc-enhance" icon="sun">Aclarar documento</x-ui.button>
                    <x-ui.button variant="secondary" id="doc-monochrome" icon="contrast">Blanco y negro</x-ui.button>
                </div>
                <div class="doc-fields">
                    <label class="doc-field doc-wide">Filtro<select id="doc-filter"><option value="color">Color original</option><option value="gris">Escala de grises</option><option value="bn">Blanco y negro</option><option value="documento">Documento: aclarar sombras</option></select></label>
                    <label class="doc-field"><span class="doc-range-label">Brillo <output id="doc-bright-val">100%</output></span><input id="doc-bright" type="range" min="60" max="160" value="100"></label>
                    <label class="doc-field"><span class="doc-range-label">Contraste <output id="doc-contrast-val">100%</output></span><input id="doc-contrast" type="range" min="50" max="200" value="100"></label>
                    <label class="doc-field"><span class="doc-range-label">Nitidez <output id="doc-sharp-val">0%</output></span><input id="doc-sharp" type="range" min="0" max="100" value="0"></label>
                    <label class="doc-field"><span class="doc-range-label">Umbral B/N <output id="doc-threshold-val">150</output></span><input id="doc-threshold" type="range" min="30" max="230" value="150"></label>
                </div>
                <div class="doc-controls">
                    <x-ui.button variant="secondary" id="doc-filter-reset" icon="rotate-ccw">Restablecer filtros</x-ui.button>
                    <x-ui.button variant="secondary" id="doc-copy" icon="clipboard-copy">Copiar imagen</x-ui.button>
                    <x-ui.button variant="secondary" id="doc-jpg" icon="image-down">Descargar JPG</x-ui.button>
                    <x-ui.button variant="secondary" id="doc-png" icon="image-down">Descargar PNG</x-ui.button>
                </div>
                <figure><figcaption class="label-campo mb-2">Resultado de la cara seleccionada</figcaption><img id="doc-result" class="doc-result" hidden alt="Vista previa de la foto corregida"></figure>
            </section>
        </div>
        <aside class="doc-panel self-start xl:sticky xl:top-5" id="doc-impresion">
            <h3><x-icono name="printer" class="size-4"/> 3. Copias y hoja de impresión</h3>
            <div class="doc-fields">
                <label class="doc-field doc-wide">Tamaño del documento<select id="doc-preset"><option value="tarjeta">Tarjeta: 8,56 × 5,4 cm</option><option value="pasaporte">Pasaporte: 12,5 × 8,8 cm</option><option value="a5">Media hoja: 14,8 × 21 cm</option><option value="libre">Medidas personalizadas</option></select></label>
                <label class="doc-field">Ancho (cm)<input id="doc-width" type="number" min="1" max="50" step="0.01" value="8.56"></label>
                <label class="doc-field">Alto (cm)<input id="doc-height" type="number" min="1" max="50" step="0.01" value="5.4"></label>
                <label class="doc-field">Papel<select id="doc-paper"><option value="carta">Carta</option><option value="a4">A4</option></select></label>
                <label class="doc-field">Orientación<select id="doc-orientation"><option value="vertical">Vertical</option><option value="horizontal">Horizontal</option></select></label>
                <label class="doc-field">Copias por hoja<input id="doc-copies" type="number" min="1" max="10" step="1" value="1"></label>
                <label class="doc-field">Distribución<select id="doc-layout"><option value="vertical">Una columna</option><option value="horizontal">Dos columnas</option></select></label>
                <label class="doc-field">Márgenes (cm)<input id="doc-margin" type="number" min="0" max="5" step="0.1" value="2"></label>
                <label class="doc-field">Separación (cm)<input id="doc-gap" type="number" min="0" max="6" step="0.1" value="1.2"></label>
                <label class="doc-help doc-wide flex items-start gap-2"><input id="doc-fit" type="checkbox" class="casilla mt-1"> Reducir proporcionalmente si las copias no caben en la hoja</label>
            </div>
            <div class="doc-sheet-wrap"><div id="doc-sheet" class="doc-sheet" aria-label="Vista previa de la hoja"></div></div>
            <p class="doc-help mt-3" id="doc-size-info" role="status"></p>
            <div class="doc-controls">
                <x-ui.button id="doc-pdf" icon="file-down">Descargar PDF</x-ui.button>
                <x-ui.button variant="secondary" id="doc-print" icon="printer">Abrir para imprimir</x-ui.button>
            </div>
            <p class="doc-help">Para conservar las medidas, imprima el PDF al 100% / tamaño real. Cada copia incluye las caras cargadas.</p>
        </aside>
    </div>
    <p class="doc-message" id="doc-message" role="status" aria-live="polite"></p>
</section>
@push('scripts')
<script src="{{ asset('vendor/pdf-lib.min.js') }}" defer></script>
<script src="{{ asset('js/documentos-core.js') }}?v={{ filemtime(public_path('js/documentos-core.js')) }}" defer></script>
<script src="{{ asset('js/documentos-editor.js') }}?v={{ filemtime(public_path('js/documentos-editor.js')) }}" defer></script>
@endpush
</x-layout>
