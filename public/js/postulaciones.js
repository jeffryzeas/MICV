(() => {
    'use strict';
    const form = document.getElementById('post-compose');
    if (form) {
        const cliente = document.getElementById('post-cliente'), empresa = document.getElementById('post-empresa'),
            cargo = document.getElementById('post-cargo'), asunto = document.getElementById('post-asunto'),
            mensaje = document.getElementById('post-mensaje'), boton = document.getElementById('post-redactar'),
            estado = document.getElementById('post-redaccion-estado');
        let secuencia = 0, ocupada = false;
        function destinatario(cambiarCargo = false) {
            const opcion = empresa.selectedOptions[0];
            document.getElementById('post-para').textContent = opcion?.dataset.correo || '';
            if (cambiarCargo) cargo.value = opcion?.dataset.cargo || '';
        }
        async function redactar() {
            if (!cliente.value || !empresa.value) { estado.textContent = 'Seleccione la persona y la empresa para generar la propuesta.'; return; }
            const numero = ++secuencia, seleccion = [cliente.value, empresa.value, cargo.value];
            const original = [asunto.value, mensaje.value];
            ocupada = true; boton.disabled = true; estado.textContent = 'Preparando la propuesta…';
            try {
                const r = await fetch(form.dataset.redactar, {method:'POST', headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}, body:JSON.stringify({cliente_id:seleccion[0],empresa_id:seleccion[1],cargo:seleccion[2]}), signal:AbortSignal.timeout(15000)});
                if (r.redirected || !r.ok) throw new Error();
                const data = await r.json();
                if (numero !== secuencia || [cliente.value,empresa.value,cargo.value].some((v,i)=>v!==seleccion[i]) || asunto.value!==original[0] || mensaje.value!==original[1]) { estado.textContent = 'Los datos cambiaron. Pulse Generar redacción para preparar una propuesta actualizada.'; return; }
                asunto.value = data.asunto; mensaje.value = data.mensaje;
                estado.textContent = 'Propuesta lista. Revise y ajuste el texto antes de continuar.';
            } catch { estado.textContent = 'No se pudo generar la propuesta. Puede escribir el asunto y el mensaje o volver a intentarlo.'; }
            finally { ocupada = false; boton.disabled = false; }
        }
        boton.addEventListener('click', redactar);
        empresa.addEventListener('change', () => { secuencia++; destinatario(true); if (!asunto.value && !mensaje.value && !ocupada) redactar(); else estado.textContent = 'Cambió la empresa. Revise el mensaje o genere una nueva redacción.'; });
        cliente.addEventListener('change', () => { secuencia++; if (!asunto.value && !mensaje.value && !ocupada) redactar(); else estado.textContent = 'Cambió la persona del CV. Revise el asunto y el mensaje.'; });
        cargo.addEventListener('input', () => { secuencia++; });
        destinatario(form.dataset.cargoAnterior !== '1' && !cargo.value);
        if (cliente.value && empresa.value && !asunto.value && !mensaje.value) redactar();
    }
    const panel = document.getElementById('post-delivery');
    if (!panel || !['en_cola','enviando'].includes(panel.dataset.estado)) return;
    let consultando = false, terminado = false;
    async function consultar() {
        if (terminado || consultando || document.hidden) return;
        consultando = true;
        try {
            const r = await fetch(panel.dataset.url, {headers:{Accept:'application/json'},cache:'no-store',signal:AbortSignal.timeout(12000)});
            if (r.redirected || [401,403,404,419].includes(r.status)) { terminado=true; document.getElementById('post-detalle').textContent='Vuelva a iniciar sesión para consultar el estado de este envío.'; return; }
            if (!r.ok) throw new Error();
            const d = await r.json(); panel.dataset.estado=d.estado;
            const badge = document.getElementById('post-estado'); badge.textContent=d.etiqueta; badge.dataset.estado=d.estado;
            document.getElementById('post-detalle').textContent=d.detalle || ({en_cola:d.espera ? 'El envío sigue en cola. La administración debe comprobar el servicio micv-postulaciones del servidor.' : 'Esperando al trabajador de correo. Puede salir y volver a esta página.',enviando:'Conectando con Gmail y enviando el archivo adjunto.',enviado:'Gmail aceptó el mensaje. La recepción y lectura por la empresa no están confirmadas.'}[d.estado] || '');
            if (d.fecha) document.getElementById('post-fecha').textContent=d.fecha+' · Costa Rica';
            terminado=!['en_cola','enviando'].includes(d.estado);
        } catch { document.getElementById('post-detalle').textContent='No se pudo actualizar el estado. Se consultará de nuevo automáticamente; no confirme otro envío.'; }
        finally { consultando=false; }
    }
    const timer=setInterval(()=>{if(terminado)clearInterval(timer);else consultar();},5000);
    document.addEventListener('visibilitychange',consultar); consultar();
})();
