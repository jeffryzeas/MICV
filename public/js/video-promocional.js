(() => {
    const video = document.getElementById('video-promocional');
    const generar = document.getElementById('generar-video');
    if (!video || !['pendiente', 'procesando'].includes(video.dataset.estado)) return;

    const mensaje = document.getElementById('video-estado');
    const mensajes = {
        pendiente: 'Video en cola. Puede volver a esta página más tarde.',
        procesando: 'Capturando pantallas y preparando la voz.',
        listo: 'Su video está listo.',
        error: 'Falló la generación. Revise el servicio de cola, Chromium, la voz y FFmpeg, o vuelva a intentarlo.',
    };

    async function consultar() {
        try {
            const respuesta = await fetch(video.dataset.url, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (respuesta.status === 401 || respuesta.status === 403 || respuesta.redirected) {
                mensaje.textContent = 'Vuelva a iniciar sesión para ver el estado.';
                return;
            }
            if (respuesta.status === 404) {
                mensaje.textContent = 'El video ya no está disponible. Recargue la página para generar uno nuevo.';
                return;
            }
            if (!respuesta.ok) throw new Error('Consulta temporalmente indisponible');
            const actual = await respuesta.json();
            if (actual.estado === 'listo') {
                document.getElementById('video-vista').src = actual.vista;
                document.getElementById('video-descarga').href = actual.descarga;
                document.getElementById('video-resultado').classList.remove('hidden');
            }
            if (actual.estado === 'listo' || actual.estado === 'error') {
                mensaje.textContent = mensajes[actual.estado];
                generar.disabled = video.dataset.abierto !== '1';
                return;
            }
            if (Date.now() / 1000 - actual.actualizado >= 1200) {
                mensaje.textContent = 'La generación lleva más de 20 minutos. Revise el servicio de cola o vuelva a intentarlo.';
                generar.disabled = video.dataset.abierto !== '1';
                return;
            }
            mensaje.textContent = mensajes[actual.estado] ?? 'Preparando la generación.';
        } catch {
            mensaje.textContent = 'No se pudo consultar el estado. Se reintentará automáticamente.';
        }
        setTimeout(consultar, 5000);
    }

    setTimeout(consultar, 2000);
})();
