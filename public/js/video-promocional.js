(() => {
    'use strict';
    const video = document.getElementById('video-promocional');
    const generar = document.getElementById('generar-video');
    if (!video) return;
    const mensaje = document.getElementById('video-estado');
    const porcentaje = document.getElementById('video-porcentaje');
    const barra = document.getElementById('video-progreso');
    const detalle = document.getElementById('video-detalle');
    const tiempo = document.getElementById('video-tiempo');
    const etapas = ['cola', 'preparacion', 'capturas', 'voz', 'render', 'exportacion', 'completado'];
    let activo = ['pendiente', 'procesando'].includes(video.dataset.estado);
    let inicio = Number(video.dataset.iniciado) || 0;
    let consulta;
    let reintentos = 0;
    let consultando = false;

    function reloj() {
        if (!inicio || !activo) return;
        const segundos = Math.max(0, Math.floor(Date.now() / 1000 - inicio));
        tiempo.textContent = `${Math.floor(segundos / 60)}:${String(segundos % 60).padStart(2, '0')} transcurridos`;
    }
    function mostrar(actual) {
        video.dataset.estado = actual.estado;
        inicio = Number(actual.iniciado) || inicio;
        const avance = Math.min(100, Math.max(0, Number(actual.porcentaje) || 0));
        porcentaje.textContent = `${avance}%`;
        barra.value = avance;
        barra.setAttribute('aria-valuetext', `${avance}% · ${actual.mensaje || actual.estado}`);
        mensaje.textContent = actual.mensaje || 'Preparando la generación.';
        const paso = etapas.indexOf(actual.etapa);
        for (const elemento of video.querySelectorAll('[data-etapa]')) {
            const indice = etapas.indexOf(elemento.dataset.etapa);
            elemento.dataset.fase = actual.estado === 'error' ? 'error' : indice < paso ? 'completa' : indice === paso ? 'actual' : 'pendiente';
            if (indice === paso) elemento.setAttribute('aria-current', 'step');
            else elemento.removeAttribute('aria-current');
        }
        detalle.textContent = actual.escena > 0 ? `Escena ${actual.escena} de ${actual.escenas}` : 'El avance corresponde a las etapas del renderizado.';
        if (actual.motor_voz) detalle.textContent += ({pocket:' · Voz neuronal natural',piper:' · Voz neuronal Piper',espeak:' · Voz de respaldo'}[actual.motor_voz] || '');
        if (actual.estado === 'listo') {
            document.getElementById('video-vista').src = actual.vista;
            document.getElementById('video-descarga').href = actual.descarga;
            document.getElementById('video-resultado').classList.remove('hidden');
        }
        if (['listo', 'error'].includes(actual.estado)) {
            activo = false;
            generar.disabled = video.dataset.abierto !== '1';
            tiempo.textContent = actual.estado === 'listo' ? 'Generación completada' : 'Generación interrumpida';
        }
        reloj();
    }
    async function consultar() {
        if (!activo || document.hidden || consultando) return;
        consultando = true;
        try {
            const respuesta = await fetch(video.dataset.url, {
                headers: { Accept: 'application/json' }, cache: 'no-store',
                signal: AbortSignal.timeout(12000),
            });
            if ([401, 403, 404, 419].includes(respuesta.status) || respuesta.redirected) {
                activo = false;
                mensaje.textContent = respuesta.status === 404 ? 'El video ya no está disponible. Recargue la página.' : 'Vuelva a iniciar sesión para consultar el estado.';
                return;
            }
            if (!respuesta.ok) throw new Error('Consulta no disponible');
            const actual = await respuesta.json();
            mostrar(actual);
            reintentos = 0;
            if (activo && Date.now() / 1000 - actual.actualizado >= 1200) {
                mensaje.textContent = 'No hay actividad reciente. Revise el trabajador de la cola o genere un nuevo video.';
                generar.disabled = video.dataset.abierto !== '1';
            }
        } catch {
            reintentos++;
            mensaje.textContent = 'Conexión interrumpida. Se reintentará automáticamente; la generación continúa en el servidor.';
        } finally {
            consultando = false;
        }
        if (activo) consulta = setTimeout(consultar, Math.min(15000, 2500 * (reintentos + 1)));
    }
    document.addEventListener('visibilitychange', () => {
        clearTimeout(consulta);
        if (!document.hidden && activo) consultar();
    });
    const formulario = generar?.closest('form');
    formulario?.addEventListener('submit', () => {
        generar.disabled = true;
        generar.textContent = 'Iniciando generación…';
    });
    setInterval(reloj, 1000);
    reloj();
    if (activo) consultar();
})();
