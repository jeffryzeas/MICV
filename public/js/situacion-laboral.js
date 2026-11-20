(() => {
    'use strict';
    const norm = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().replace(/\s+/g, ' ').toLowerCase();
    document.querySelectorAll('[data-situacion-laboral]').forEach(block => {
        const form = block.closest('form');
        if (!form) return;
        const lista = JSON.parse(block.querySelector('[data-nacionalidades]').textContent);
        const residencia = block.querySelector('[name="pais_residencia"]');
        const nacionalidad = form.querySelector('[name="nacionalidad"]');
        const grupo = block.querySelector('[data-extranjero-laboral]');
        if (!residencia || !grupo) return;
        const campos = grupo.querySelectorAll('input[name]');
        const actualizar = () => {
            const texto = norm(nacionalidad?.value);
            const codigo = lista.find(n => [n.m, n.f, n.p].some(v => norm(v) === texto))?.c;
            const extranjero = Boolean(residencia.value && residencia.value !== 'OT' && texto && codigo !== residencia.value);
            grupo.hidden = !extranjero;
            grupo.style.display = extranjero ? '' : 'none';
            for (const campo of campos) {
                const restringido = campo.getAttribute('aria-disabled') === 'true';
                campo.disabled = !extranjero || restringido;
                campo.required = campo.name === 'autorizacion_laboral' && extranjero && !restringido;
                campo.setCustomValidity('');
            }
        };
        form.addEventListener('input', actualizar);
        form.addEventListener('change', actualizar);
        form.addEventListener('submit', actualizar, true);
        document.addEventListener('DOMContentLoaded', actualizar);
        actualizar();
    });
})();
