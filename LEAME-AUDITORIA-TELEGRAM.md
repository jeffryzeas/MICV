# Mi CV · Auditoría, Telegram e informes temporales

Actualización del proyecto Laravel existente. El ZIP contiene las carpetas en su raíz; conserve su `.env`, base de datos, dependencias y archivos originales. No incluye credenciales, capturas reales ni rutas de demostración.

## Instalar en el VPS

Haga una copia del código y la base de datos. Suba y extraiga `Mi-CV-Auditoria-Telegram-Informes-Temporales.zip` dentro de `/var/www/micv/`, conservando las subcarpetas y reemplazando los archivos de esta actualización. Después:

```bash
cd /var/www/micv
sudo bash bin/actualizar-correo-comentarios.sh
```

El instalador conjunto aplica las migraciones pendientes, configura el correo y los servicios de TikTok. Recargue con Ctrl+F5. Los requisitos de PHP, Python, Node y Chromium permanecen en LEAME-TIKTOK-MONITOR.md. Esta entrega prepara el código; no instala remotamente en su VPS.

## Informes disponibles durante cinco minutos

El PDF, ZIP, snapshot NDJSON, manifiesto y copias de fotos preparados para cada exportación caducan **cinco minutos después de finalizar la generación**. No se consume ese plazo mientras el informe está en cola o procesándose. La pantalla muestra la cuenta regresiva y retira los enlaces al caducar. El servidor comprueba la caducidad en cada descarga y devuelve una página 410, aunque se conserve un enlace anterior.

El temporizador de systemd limpia los directorios caducados cada 15 segundos. La eliminación física ocurre en el siguiente barrido; el bloqueo de descarga se aplica desde el vencimiento. Si una eliminación falla, se conserva el estado caducado y se vuelve a intentar. Los informes antiguos que ya superen los cinco minutos también caducan al actualizar. Se conservan sus metadatos, recuentos y hashes.

La limpieza solo afecta al directorio de **copias generadas de cada informe**. No elimina comentarios, historial de salas, perfiles, fotos originales ni PDF de CV enviados a empresas. Puede consultar esos datos en Auditoría y preparar otro informe. Las copias que haya descargado a otro dispositivo permanecen allí.

## Auditoría sin generar archivos

Desde cada cuenta, abra **Auditar**. El menú separa mensajes, participantes, salas y fechas, captura, fuentes de chat, informes y Telegram. Hay filtros de fecha, sala, texto y orden; fechas y horas incluyen la zona configurada y formato AM/PM. Cada participante enlaza sus comentarios completos debajo de su ficha, incluidos los del creador cuando se identifican.

La consulta fija un corte de registros para mantener una paginación estable. **Actualizar consulta** incorpora los nuevos. **Inspeccionar registro** presenta el texto recibido, nombre, usuario, foto pública disponible, origen, sala, fechas e integridad SHA-256 del registro conservado. Abrir estas vistas no genera PDF ni ZIP. Son privadas y exclusivas de administradores activos.

## Bot exclusivo de @jeffryzeasni

El único destinatario permitido es el **chat privado de @jeffryzeasni, ID 8476238020**. La autorización usa el ID numérico; el alias es una referencia visual. El servidor rechaza otros IDs y grupos, incluso si se altera el formulario o la configuración almacenada. No existe una ruta pública de órdenes o respuestas a otros usuarios. Esta integración envía notificaciones de comentarios; no es un chatbot conversacional.

1. Cree un bot con **@BotFather** y obtenga su token. Los valores `api_id` y `api_hash` de Telegram Core no sirven como token de Bot API.
2. Desde la cuenta **@jeffryzeasni**, abra el bot y pulse **Iniciar**.
3. Como administrador, abra **TikTok → Bot de Telegram**, introduzca el token en el formulario privado y guarde. El ID ya está fijado. **No publique el token en el chat ni en capturas.**
4. Pulse **Comprobar bot y chat**. Se consultan getMe/getChat sin enviar mensajes; el chat debe coincidir exactamente con el ID autorizado y ser privado.
5. Seleccione todos los perfiles o una lista y active los envíos. Solo se encolan comentarios públicos nuevos de perfiles con monitoreo activo.

Se envía un mensaje de texto por comentario: nombre público, @usuario, perfil monitoreado, cuenta de origen del chat cuando se entrega, ID de sala, hora local con zona y texto. No se adjuntan fotos ni se generan vistas previas de enlaces. Los comentarios que exceden el límite de Telegram se recortan con un aviso; el original completo permanece en Auditoría.

El token se almacena cifrado mediante la clave de Laravel, nunca se devuelve al formulario y se excluye de la entrada anterior de sesión. Dejar el campo vacío conserva el token existente. No sustituya APP_KEY en una instalación que ya almacena datos cifrados.

Las entregas usan una cola de base de datos y un proceso independiente: la captura no espera a Telegram y continúa al cerrar sesión. Se deduplica el mismo evento de sala observado desde varios perfiles y se respetan las esperas de Telegram. El chat privado procesa como máximo un intento cada dos segundos: un LIVE con muchos comentarios puede acumular retraso. Si no se conoce el resultado de un envío, se marca **Sin confirmación** y no se repite automáticamente para evitar duplicados. Un rechazo de permisos pausa únicamente Telegram. El panel conserva el estado de cada entrega. Cambiar token/alcance o pausar cancela pendientes de la configuración anterior.

## Servicios y diagnóstico

Cuatro trabajadores independientes: captura (`micv-tiktok`), fotos (`micv-tiktok-evidencias`), exportaciones (`micv-tiktok-pdf`) y Telegram (`micv-tiktok-telegram`). La limpieza utiliza `micv-tiktok-limpieza.timer` y un servicio de ejecución puntual.

```bash
systemctl status micv-tiktok micv-tiktok-evidencias micv-tiktok-pdf micv-tiktok-telegram micv-tiktok-limpieza.timer --no-pager -l
journalctl -u micv-tiktok-telegram -u micv-tiktok-limpieza -n 80 --no-pager
systemctl list-timers micv-tiktok-limpieza.timer --no-pager
sudo -u www-data php artisan micv:tiktok-limpiar-informes
```

La limpieza manual es idempotente y aplica el mismo límite de cinco minutos. No ejecute un segundo trabajador de Telegram a la vez; el comando también utiliza un bloqueo local para evitarlo.

## Comprobación de esta entrega

205 pruebas PHP y 37 Python. Incluyen caducidad a los 300 segundos, separación de archivos generados y originales, permisos, auditoría, integridad, destinatario fijo, cifrado, deduplicación y límites/fallos de Telegram. Revisión en Chromium en escritorio, móvil y modo oscuro; generación real de PDF con datos ficticios, descarga antes del vencimiento y respuesta 410 después, con eliminación de copias y conservación de capturas. La conexión de Telegram se simuló: **no se enviaron mensajes reales**. El token real debe configurarse en su instalación.

La captura sigue limitada a los eventos públicos que entregue TikTok; no obtiene contenido privado ni garantiza exhaustividad. Los hashes permiten detectar cambios del registro conservado, no certificar por sí solos identidad o autenticidad de la plataforma.
