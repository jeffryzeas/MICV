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

## Salas compartidas con invitados

En la ficha de una cuenta, pulse **Agregar invitado público**. Registre el @usuario exacto del invitado y conserve la referencia de sala compartida preseleccionada. Abra su ficha y active su captura. Puede dejar activadas varias fuentes; el monitoreo sigue funcionando al cerrar sesión mientras el servicio del VPS esté activo.

Cada cuenta conserva su propia sala, historial, reproductor e informes. Los enlaces entre anfitrión e invitados son referencias declaradas por el administrador, no una relación confirmada por TikTok. El PDF y el paquete identifican la cuenta observada y esa referencia. Eliminar el monitoreo del anfitrión desvincula las referencias sin borrar el historial del invitado.

Solo se conecta a un LIVE público accesible de la cuenta registrada. No cambia de fuente automáticamente para eludir una restricción ni habilita el LIVE privado del anfitrión. Los comentarios de distintos invitados pueden pertenecer a chats diferentes: observar a uno no garantiza recibir todos los mensajes ni todo lo ocurrido en la sala compartida.

## Verificación

Pruebas de Laravel: correo formal, copias históricas, aviso en HTML y texto MIME con transporte simulado, adjunto, comentarios debajo de cada perfil, creador por UID y coincidencia de usuario, referencia del invitado y separación de historiales. Capturador Python con TikTokLive 7.0.1: identificadores grandes, normalización, perfiles privados que comentan públicamente y restricciones.

Revisión en Chromium: correo y formulario de invitado en escritorio y móvil, sin desbordamiento. PDF A4 de demostración con datos ficticios generado y su paquete verificado con `python3 verificar.py`. No se enviaron correos reales. No se ha instalado esta actualización en su VPS ni se ha confirmado un LIVE real desde él.
