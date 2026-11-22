(() => {
    'use strict';
    window.jzAlerta = async function (opciones = {}) {
        if (!window.Swal) {
            const texto = [opciones.title, opciones.text].filter(Boolean).join('\n\n');
            const si = opciones.showCancelButton ? window.confirm(texto) : (window.alert(texto), true);
            return {isConfirmed:si, isDismissed:!si};
        }
        if (Swal.isLoading()) {
            Swal.hideLoading(); Swal.close();
            await new Promise(resolve => setTimeout(resolve, 80));
        }
        return Swal.fire({
            toast:false, position:'center', showConfirmButton:true,
            confirmButtonText:'Aceptar', cancelButtonText:'Cancelar',
            confirmButtonColor:'#0B3C74', allowOutsideClick:false,
            ...opciones,
            // Los avisos normales esperan la respuesta de la persona.
            toast:false, timer:undefined, timerProgressBar:false,
        });
    };
    const pendientes = new WeakSet(), confirmados = new WeakSet();
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-jz-confirmar')) return;
        if (confirmados.has(form)) { confirmados.delete(form); return; }
        event.preventDefault(); event.stopImmediatePropagation();
        if (pendientes.has(form)) return;
        if (!form.checkValidity()) { form.reportValidity(); return; }
        pendientes.add(form);
        const submitter = event.submitter;
        try {
            const result = await window.jzAlerta({
                icon:form.dataset.jzIcono || 'question',
                title:form.dataset.jzConfirmar || '¿Guardar los datos?',
                text:form.dataset.jzDetalle || '', showCancelButton:true, focusCancel:true,
                confirmButtonText:form.dataset.jzBoton || 'Sí, guardar',
                confirmButtonColor:form.hasAttribute('data-jz-peligro') ? '#dc2626' : '#0B3C74',
            });
            if (result.isConfirmed && form.isConnected) {
                confirmados.add(form);
                try { form.requestSubmit(submitter?.isConnected && !submitter.disabled ? submitter : undefined); }
                finally { confirmados.delete(form); }
            }
        } finally { pendientes.delete(form); }
    }, true);
})();
