# Mi CV · Enviar CV y mensajes desde su número de Telegram

Módulo Laravel exclusivo de administradores activos. Utiliza la aplicación de desarrollador de Telegram (`api_id` y `api_hash`) y su cuenta personal mediante MTProto/Telethon. Cada administrador conecta su propia cuenta y solo accede a su conexión e historial. El bot de comentarios de TikTok mantiene su configuración independiente.

## Instalar en el VPS

Haga una copia del proyecto y la base de datos. Extraiga `Mi-CV-Telegram-Personal-CV.zip` dentro de `/var/www/micv/`, conservando las subcarpetas y su `.env`, dependencias y archivos almacenados. Este ZIP actualiza el proyecto existente, no lo sustituye. Incluye los archivos de las entregas de correo y auditoría anteriores; si todavía no las ha instalado, ejecute primero `sudo bash bin/actualizar-correo-comentarios.sh`.

Después instale el nuevo módulo:

```bash
cd /var/www/micv
sudo bash bin/instalar-telegram-cv.sh
```

El instalador prepara un entorno Python privado para Telethon, aplica únicamente la migración del nuevo módulo, limpia cachés y configura `micv-telegram-cv.service`. No desconecta ni reconfigura el bot. Recargue con Ctrl+F5 y abra **CV por mi Telegram**.

Requisitos: proyecto Laravel instalado, PHP CLI con sus extensiones y cola de base de datos/tabla jobs, Python 3.10+, Ubuntu con systemd y conexión del VPS a Telegram mediante MTProto. El CV reutiliza el motor Chromium/Browsershot/Puppeteer del proyecto. No requiere Reverb ni un bot. Para otra instalación PHP/usuario:

```bash
sudo MICV_PHP_BIN=/usr/bin/php MICV_WEB_USUARIO=www-data bash bin/instalar-telegram-cv.sh
```

## Conectar la aplicación Mi CV

1. Abra **CV por mi Telegram → Mi número de Telegram** como administrador.
2. Introduzca el **App api_id y App api_hash** de la aplicación Mi CV registrada en `my.telegram.org`, y su número con prefijo internacional: por ejemplo `+50688887777`.
3. Pulse **Solicitar código de Telegram** y confirme la conexión. Telegram decide si entrega el código en la aplicación u otro canal disponible. La API no permite garantizar SMS.
4. Introduzca el código. Si tiene activada la verificación en dos pasos, introduzca también su contraseña de Telegram en el formulario privado.
5. Compruebe el nombre, número e ID de la cuenta conectada.

No envíe el api_hash, códigos, contraseña o sesión por chat ni en capturas. El código y la contraseña se utilizan solo en la petición de autenticación y no se guardan en la base de datos ni en la entrada anterior de la sesión web. El api_hash, sesión de Telegram, hash del desafío y referencia interna del destinatario se guardan cifrados mediante APP_KEY. No reemplace esa clave en una instalación con información cifrada. No se crean archivos `.session` de Telethon; se usa StringSession en memoria y almacenamiento cifrado de Laravel.

La sesión sigue disponible al cerrar Mi CV. **Desconectar cuenta** intenta cerrar la sesión en Telegram, retira las credenciales locales y cancela borradores/envíos pendientes. Si no hubo conexión con Telegram, el aviso indica que debe revocarla desde **Telegram → Ajustes → Dispositivos**. Un envío ya en tránsito puede terminar.

## Preparar un mensaje o CV

1. Abra **Nuevo mensaje o CV**. Indique un único número con `+` y código de país, sin espacios, y un nombre para su agenda.
2. Elija **Mensaje con CV en PDF** o **Solo un mensaje**.
3. Si adjunta CV, seleccione una persona activa, diseño en la galería visual, paleta, idioma y fotografía. La vista previa utiliza el CV del sistema.
4. Escriba el mensaje. Con PDF se admiten hasta 1024 unidades de texto de Telegram; sin adjunto, hasta 4096. Algunos símbolos, como emojis, cuentan como dos. El sistema valida el límite sin dividir ni truncar el contenido silenciosamente.
5. Pulse **Preparar y revisar**. La confirmación avisa que Telegram puede añadir el número a su agenda de contactos para localizarlo. No se envía todavía el mensaje.
6. Revise la **identidad real devuelta por Telegram**, número, ID, texto y PDF descargable. El nombre de la agenda no se presenta como identidad confirmada del titular.
7. Pulse **Confirmar y enviar**. Se procesa en segundo plano y el estado se actualiza en la página. Puede cerrar sesión y volver a consultar el historial.

El destinatario debe tener Telegram y permitir que su cuenta lo localice y le escriba. Si Telegram no entrega esa asociación, el sistema indica que no pudo localizarlo; no inventa una cuenta ni busca identidades alternativas. No envía SMS, WhatsApp, mensajes masivos o comentarios del LIVE. Usa la cuenta personal conectada y un destinatario confirmado en cada envío.

## Copias e historial

El PDF se genera con Chromium y se conserva como una versión exacta junto con su nombre, opciones, tamaño y SHA-256. Editar luego los datos del CV no modifica un envío preparado. Antes de enviarlo se verifica el hash, la disponibilidad de la persona y la cuenta conectada. El archivo se envía como documento PDF con el texto como mensaje adjunto. Los textos son planos, sin interpretar HTML.

El historial registra remitente, destinatario, número, ID de Telegram, contenido, estados y fechas AM/PM en America/Costa_Rica. **Aceptado por Telegram** indica aceptación por el servicio; no acredita lectura. Estos CV no están sujetos a la caducidad de cinco minutos de los informes de TikTok. Puede descartar borradores; los envíos procesados conservan su versión e historial.

La confirmación repetida no crea trabajos duplicados y el trabajador solo procesa filas en cola. Cada mensaje lleva un random_id MTProto persistido. Una interrupción o resultado sin confirmación se marca **Revisar en Telegram** y no se reintenta automáticamente. Revise la conversación antes de preparar otro envío. Ante restricciones de privacidad o límites del proveedor, se conserva el motivo y se respeta su plazo de espera. Cambiar o desconectar la cuenta invalida los borradores pendientes de su sesión anterior.

## Diagnóstico

```bash
systemctl status micv-telegram-cv --no-pager -l
journalctl -u micv-telegram-cv -n 60 --no-pager
sudo -u www-data storage/app/telegram-cv-venv/bin/python -c 'import telethon; print(telethon.__version__)'
```

Si se queda en cola, revise este servicio, PHP y la base de datos. Si no localiza un número, confirme que pertenece a una cuenta de Telegram localizable desde la cuenta remitente. Si la sesión se revoca, conéctela nuevamente desde el panel. Los límites y rechazos de Telegram no se omiten.

## Verificación

- Suite PHP completa: **225 pruebas, 2166 aserciones**; incluye las 20 pruebas nuevas de permisos, aislamiento entre administradores, autenticación por código/dos pasos, cifrado, secretos excluidos de sesiones, límites, confirmación, hash y copia del CV, revocación y resultados inciertos.
- Adaptador MTProto: **10 pruebas Python** con tipos reales de Telethon 1.42.0 y transporte simulado; cubren resolución por teléfono, privacidad, cuenta remitente, PDF/documento, mensaje plano, IDs de 64 bits, random_id, código/dos pasos y respuestas sin confirmación.
- Capturador de TikTok: sus **37 pruebas Python** siguen pasando en su entorno independiente.
- Chromium: conexión con código y contraseña simulados, galería/vista previa, escritorio, móvil y modo oscuro, generación real de un PDF ficticio, descarga adjunta, confirmación y seguimiento hasta aceptación simulada.

No se han utilizado sus credenciales ni enviado mensajes reales. Esta entrega no ha sido instalada en su VPS. Las pruebas no garantizan la disponibilidad o aceptación de Telegram desde ese servidor.

Dependencia: Telethon 1.42.0, licencia MIT: https://pypi.org/project/Telethon/1.42.0/ · documentación: https://docs.telethon.dev/en/stable/
