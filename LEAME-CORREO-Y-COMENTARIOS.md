# Correo formal, comentarios por perfil y salas con invitados

Actualización para la instalación existente de Mi CV. Copie el contenido del ZIP en la raíz del proyecto, conservando las subcarpetas; no elimine el proyecto anterior ni sustituya su .env. Mantenga una copia del proyecto y la base de datos antes de actualizar.

En el VPS:

```bash
cd /var/www/micv
sudo bash bin/actualizar-correo-comentarios.sh
```

El instalador configura/reinicia el trabajador de postulaciones y los tres trabajadores de TikTok, limpia las cachés y aplica las migraciones pendientes de ambos módulos. Reutiliza el motor Chromium y las dependencias PHP/Node del proyecto instalado; instala el entorno del capturador como indica LEAME-TIKTOK-MONITOR.md. Recargue el navegador con Ctrl+F5.

## Correos a empresas

La redacción propuesta comienza con «Saludos cordiales:» o «Saludos, [contacto]:». La carta presenta párrafos, firma y espacios discretos; no incluye un título grande dentro del mensaje. El asunto permanece en el encabezado normal del correo. Puede editar la redacción antes de preparar el envío.

El pie contiene un aviso de privacidad con la dirección real de la empresa destinataria, la finalidad de selección laboral y la instrucción de eliminar el mensaje y sus adjuntos si se recibió por error. No utiliza como destinatario el correo de soporte del sistema. La misma copia del aviso se incluye en las partes HTML y texto y se muestra antes de confirmar. El CV permanece adjunto como PDF.

Los mensajes y PDF de CV ya preparados mantienen sus copias históricas. Para ver el diseño nuevo, prepare otro envío; los correos antiguos no se alteran ni se reenvían automáticamente.

## PDF de TikTok

Cada perfil aparece seguido de sus comentarios escritos, con texto, fecha local AM/PM, UTC, fuente, identificadores y hash de captura. El índice cronológico permite seguir el orden entre participantes. Se conserva la división en partes de hasta 200 registros: un participante puede aparecer en varias partes y los recuentos indican su alcance.

Los comentarios escritos del creador aparecen primero cuando pueden relacionarse con el titular de la sala. Se coteja el ID público registrado al iniciar el LIVE cuando ambos IDs existen. Si falta el ID del titular, puede usarse la coincidencia de usuario público, indicada expresamente como coincidencia sin cotejo de ID. Un ID distinto no se clasifica como creador aunque coincida el alias. No se transcribe la voz ni se inventan comentarios que no llegaron.

Los comentarios públicos recibidos se conservan aunque el comentarista tenga perfil privado. Solo se incluyen fotos y datos públicos efectivamente entregados. No se accede al contenido privado de las cuentas. Los informes listos permanecen inmutables; solicite un informe nuevo para obtener esta presentación. El paquete de evidencia mantiene manifiesto, registros y verificación sin conexión.

## Detección automática, sin seleccionar anfitrión o invitado

Registre solo el @usuario y active su captura. Se intenta la conexión habitual con TikTokLive; si no localiza una sala, se consulta la API pública del usuario y después su perfil público, incluidos los estados SIGI_STATE y __UNIVERSAL_DATA_FOR_REHYDRATION__. Solo se aceptan IDs asociados al usuario solicitado: no se buscan salas por recomendaciones ni se exploran cuentas ajenas. No necesita indicar el nombre del anfitrión.

Si existe una sala asociada, se verifica el LIVE con el SDK y se conecta como cualquier otra captura pública. La sala, la fuente de detección y el titular entregado por TikTok se conservan en el informe. El perfil monitoreado y el titular de la sala se guardan por separado; una asociación de sala no demuestra por sí sola que el usuario sea invitado o que esté hablando en ese momento.

Si la consulta principal de una sala conectada no incluye HLS, se consulta el detalle público de esa misma sala. Solo se acepta una respuesta activa y una URL HTTPS de las CDN permitidas. Esta consulta de video no bloquea la recepción de comentarios. Se conservan las comprobaciones de acceso: no se omiten restricciones, no se añaden cookies de sesión y no se accede a contenido privado.

El adaptador distingue las respuestas vacías de metadatos de los avisos explícitos `prompts` de TikTok. Una respuesta vacía queda como incidencia con reintento; un aviso explícito de acceso al LIVE no disponible se muestra como «Esperando acceso al LIVE», mantiene el monitoreo activado y programa una nueva comprobación en al menos cinco minutos, respetando una espera mayor del proveedor. Ambos casos detienen el intento actual sin saltar comprobaciones ni buscar otro método de acceso. El estado describe el acceso del capturador al LIVE, no la privacidad del perfil.

Al instalar esta versión se recuperan las cuentas que quedaron en el antiguo estado automático «restringido». Las pausas manuales (estado «inactivo») y las cuentas nuevas que todavía no se activaron se conservan. Las esperas futuras existentes no se acortan.

Cuando TikTok no publica una asociación suficiente, el estado es **No se pudo determinar la sala**, con reintentos automáticos y sin desactivar el monitoreo. No significa que el usuario no pueda estar participando como invitado en otra sala. Un perfil visible o un ID histórico no garantizan acceso a un LIVE activo. La opción manual de anfitrión/invitado fue retirada; las referencias guardadas con la versión anterior se conservan en informes históricos.

El sistema guarda los comentarios públicos recibidos, incluso cuando el comentarista tiene perfil privado, pero no garantiza recibir todos los eventos, el chat de otra sala ni todo lo ocurrido en una transmisión compartida. El monitoreo activo continúa al cerrar sesión mientras el servicio del VPS esté funcionando.

## Solo comentarios de una sala pública conocida

Si el perfil participa en otra sala y TikTok no publica su asociación, abra su ficha y despliegue **Solo comentarios de una sala pública**. Escriba el @usuario desde el que se abre esa sala en **Usuario de la sala pública**, guarde y active la captura si está pausada. No es una selección de rol; es una fuente de chat indicada por el administrador. Deje vacío el campo para volver a la detección automática desde el perfil.

El proceso solicita el servicio público de chat mediante TikTokLive, con la comprobación de LIVE activada y procesamiento del primer lote. En este modo no solicita video ni metadatos adicionales de la sala. La conexión al chat debe ser aceptada por el servicio; un rechazo, límite o fallo de red conserva su diagnóstico y no se presenta como una captura exitosa. No utiliza credenciales de un invitado ni adquiere sus permisos.

El panel muestra **Chat de la sala en directo** únicamente tras la conexión efectiva y dedica el espacio a los comentarios. El nombre del perfil monitoreado se conserva separado de la cuenta de acceso al chat. Los mensajes recibidos se archivan en su historial con sala e identificador originales y fuente de chat en cada registro; los PDF y paquetes incluyen esa procedencia. La participación del perfil en esa sala es una referencia del administrador, no una presencia confirmada automáticamente. No se afirma que sean comentarios exclusivos de esa persona ni que dos salas compartan todo su chat.

Cambiar la fuente reinicia el proceso de esa cuenta y conserva el historial. Las pausas manuales y las esperas indicadas por el proveedor se respetan. No se garantiza la conexión desde cualquier VPS ni recibir todos los mensajes.

## Verificación

Pruebas de Laravel: correo formal, copias históricas, aviso en HTML y texto MIME con transporte simulado, adjunto, comentarios debajo de cada perfil, creador por UID y coincidencia de usuario, detección de sala, separación entre perfil consultado y titular, referencias históricas y conservación de historiales. Capturador Python con TikTokLive 7.0.1: identificadores grandes, normalización, perfiles privados que comentan públicamente y restricciones.

Revisión en Chromium: correo y registro de cuenta sin selección de invitado en escritorio y móvil, sin desbordamiento. PDF A4 de demostración con datos ficticios generado y su paquete verificado con `python3 verificar.py`. No se enviaron correos reales. No se ha instalado esta actualización en su VPS ni se ha confirmado un LIVE real desde él.
