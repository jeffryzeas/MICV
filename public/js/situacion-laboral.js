(() => {
    const norm = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().replace(/\s+/g, ' ').toLowerCase();
    document.querySelectorAll('[data-situacion-laboral]').forEach(block => {
        const form = block.closest('form');
        if (!form) return;
        const lista = JSON.parse(block.querySelector('[data-nacionalidades]').textContent);
        const residencia = block.querySelector('[name="pais_residencia"]');
        const nacionalidad = form.querySelector('[name="nacionalidad"]');
        const label = block.querySelector('[data-permiso-laboral]');
        const permiso = label.querySelector('input');
        const actualizar = () => {
            const texto = norm(nacionalidad?.value);
            const codigo = lista.find(n => [n.m, n.f, n.p].some(v => norm(v) === texto))?.c;
            const extranjero = Boolean(residencia.value && residencia.value !== 'OT' && texto && codigo !== residencia.value);
            label.hidden = !extranjero;
            label.style.display = extranjero ? 'block' : 'none';
            permiso.disabled = !extranjero;
            permiso.required = extranjero;
            permiso.setCustomValidity('');
        };
        form.addEventListener('input', actualizar);
        form.addEventListener('change', actualizar);
        form.addEventListener('submit', actualizar, true);
        // The existing nationality selector writes the text field programmatically.
        document.addEventListener('DOMContentLoaded', actualizar);
        actualizar();
    });
})();
