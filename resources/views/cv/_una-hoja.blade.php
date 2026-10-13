{{-- Ajusta el CV Pro para que SIEMPRE quepa en una sola hoja carta.
     Si el contenido es largo, reduce el tamaño (zoom) poco a poco hasta
     que entre. Chromium espera "window.cvListo" antes de hacer el PDF. --}}
<script>
(function () {
    function ajustar() {
        var hoja = document.querySelector('.hoja'), fit = document.querySelector('.fit');
        if (!hoja || !fit) { window.cvListo = true; return; }
        var H = hoja.clientHeight - 1;
        function alto() { return fit.getBoundingClientRect().height; }
        fit.style.zoom = 1; fit.style.minHeight = '';
        if (alto() > H) {
            var lo = 0.45, hi = 1;
            for (var i = 0; i < 14; i++) { var m = (lo + hi) / 2; fit.style.zoom = m; if (alto() <= H) lo = m; else hi = m; }
            fit.style.zoom = lo;
        }
        var z = parseFloat(fit.style.zoom) || 1;
        fit.style.minHeight = (H / z) + 'px';
        window.cvZoom = z;
        window.cvListo = true;
    }
    if (document.fonts && document.fonts.ready) { document.fonts.ready.then(ajustar); } else { window.addEventListener('load', ajustar); }
})();
</script>
