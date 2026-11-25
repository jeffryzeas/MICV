# Mi CV · Telegram del sistema con inicio por QR

Una sola cuenta de Telegram del sistema, vinculada por la administración, envía los CV que solicitan los usuarios. El usuario no registra su Telegram, api_id, api_hash ni contraseña: solo indica un número de destino, revisa su CV y confirma. El bot de comentarios de TikTok mantiene su configuración independiente.

## Actualizar el VPS

Haga una copia del proyecto y de la base de datos. Extraiga `Mi-CV-Telegram-Sistema-QR.zip` dentro de `/var/www/micv/`, conservando las subcarpetas. Mantenga su `.env`, APP_KEY y archivos almacenados.

```bash
cd /var/www/micv
sudo bash bin/instalar-telegram-cv.sh
```

El instalador reutiliza el entorno Telethon 1.42.0, limpia las cachés y aplica las migraciones de Telegram pendientes, incluida la nueva migración 000011. Configura dos servicios de systemd que arrancan con el VPS: envío (`micv-telegram-cv`) y vinculación (`micv-telegram-login`). Recargue el navegador con Ctrl+F5.

Si ya había cuentas de la versión personal, se conserva como cuenta del sistema la primera conectada; si ninguna estaba conectada, se elige la primera configurada. El api_hash y la sesión existentes se conservan cifrados. La cuenta del sistema queda independiente del usuario administrador. El historial y sus archivos permanecen; los borradores y envíos en cola anteriores se invalidan para preparar y revisar de nuevo con la cuenta del sistema. No se reenvía ningún mensaje automáticamente.

Requisitos: Laravel instalado, PHP CLI con extensiones de la aplicación y proc_open, tabla jobs, Python 3.10+, Ubuntu/systemd y conexión del VPS a Telegram. Los PDF reutilizan Chromium/Browsershot/Puppeteer. El QR se dibuja localmente con la biblioteca ya utilizada por Mi CV, incluida con su licencia; no se envía el token a un servicio externo de QR.

## Administración: vincular una sola vez

1. Abra **Telegram del sistema → Telegram del sistema** en el menú administrativo.
2. Guarde el api_id y api_hash de la aplicación Mi CV en `my.telegram.org`. Si ya estaban configurados, el api_id aparece y puede dejar api_hash vacío para conservarlo. El QR no requiere escribir el número remitente.
3. Pulse **Generar QR para vincular** y confirme. El panel mostrará preparación, conexión y QR temporal con cuenta regresiva.
4. En el teléfono que contiene la cuenta remitente: **Telegram → Ajustes → Dispositivos → Vincular dispositivo**. Escanee el QR y apruebe la vinculación.
5. Si la cuenta utiliza verificación en dos pasos, introduzca su contraseña en el formulario privado de Mi CV. No se guarda la contraseña.
6. Verifique nombre, número e ID de la cuenta que quedó **Conectada al sistema**.

El QR se renueva automáticamente al caducar mientras la solicitud permanece abierta, durante un máximo de cinco minutos. Puede cancelar y generar otro. El proceso de Telethon mantiene la conexión y el listener antes de publicar el QR; los accesos se procesan en una cola independiente de los envíos. Si el proceso se interrumpe, el panel detecta la pérdida de actividad y muestra un fallo, no una vinculación ficticia. Si se requiere contraseña, el paso adicional dispone de hasta diez minutos.

La sesión persiste al cerrar el panel o salir del sistema. Solo los administradores activos pueden vincular, ver el QR, configurar o desconectar esta cuenta. El QR y la sesión se guardan cifrados, sus rutas requieren autenticación, las respuestas no permiten caché y el token desaparece de la interfaz y se retira al completar/cancelar la solicitud. Los registros de error contienen únicamente categorías seguras. No publique el QR, api_hash, códigos, contraseña ni sesión.

El proceso espera la confirmación de almacenamiento de Laravel antes de aceptar el resultado. Si la operación se cancela, caduca o el proceso pierde esa confirmación después de haberse autorizado en tránsito, intenta cerrar esa sesión para no dejarla sin control local. La revocación final también está disponible desde Telegram → Ajustes → Dispositivos.

Se mantiene el acceso por código como alternativa. El QR usa el mismo acceso de red a Telegram: no puede solucionar un bloqueo de conectividad del VPS ni omitir verificaciones de Telegram.

## Usuario: indicar el número y enviar su CV

- Desde **Mi CV en PDF**, pulse **A Telegram**. Se conserva el diseño, paleta, idioma y elección de fotografía del currículum.
- También puede entrar en **Mi CV por Telegram** desde su menú.
- Indique el número con prefijo internacional, por ejemplo `+50688887777`.
- Pulse **Preparar y revisar mi CV**. El aviso explica que la cuenta del sistema puede añadir el número a su agenda de Telegram para localizarlo.
- Revise el nombre y el ID devueltos por Telegram, el texto y el PDF, y confirme el envío.

Se envía desde la cuenta del sistema al número indicado, si tiene Telegram y permite localizarlo desde esa cuenta. No se envían SMS ni WhatsApp. Si Telegram no entrega una asociación suficiente o rechaza el envío, se conserva el diagnóstico; no se busca otra identidad ni se omiten restricciones.

El usuario solo puede utilizar CV visibles para su cuenta. El servidor fuerza el modo CV y genera el texto de presentación para usuarios normales; no acepta cambiarlo por mensajes arbitrarios ni por un CV ajeno. El administrador puede preparar también mensajes de texto individuales y consultar el historial general. Cada usuario solo ve sus propios registros y descargas, sin acceso a las credenciales ni al QR.

El PDF se conserva como una copia exacta, con tamaño y SHA-256. El trabajador comprueba permisos, cuenta remitente y hash antes de enviarlo como documento. La confirmación repetida no duplica trabajos. Los envíos inciertos requieren revisar la conversación en Telegram y no se repiten automáticamente. Los límites del proveedor y las esperas se respetan. Estos CV no caducan con los informes temporales de TikTok.

## Diagnóstico

```bash
systemctl status micv-telegram-cv micv-telegram-login --no-pager -l
journalctl -u micv-telegram-login -u micv-telegram-cv -n 80 --no-pager
sudo -u www-data storage/app/telegram-cv-venv/bin/python -c 'import telethon; print(telethon.__version__)'
```

QR en cola más de treinta segundos: compruebe `micv-telegram-login`. QR con incidencia de red: compruebe la salida del VPS hacia Telegram y el código de diagnóstico. Generar QR no solicita códigos SMS. Si la sesión se revoca desde Telegram, vuelva a vincularla desde este panel.

## Verificación

Suite PHP completa: **237 pruebas, 2264 aserciones**. Adaptador/QR: **19 pruebas en Python 3.14**. Capturador TikTok: **37 pruebas Python**. Incluyen permisos, historial privado, cuenta global, migración con credenciales y envíos anteriores, cuenta conservada al eliminar al administrador, caducidad en America/Costa_Rica, token cifrado, dos pasos, cancelación y resultados tardíos. Se comprobó el intercambio real por pipes entre el trabajador y un proceso de prueba, con confirmación antes de aceptar la sesión. El flujo QR nativo de Telethon se probó con cambio de centro de datos y transporte simulado.

Chromium: QR generado localmente, renovación, cancelación, aprobación simulada, vista móvil, modo oscuro, formulario del usuario con solo número, PDF real de datos ficticios y envío simulado desde la cuenta del sistema. No se autenticaron cuentas ni se enviaron mensajes reales en Telegram. La actualización no se ha instalado en su VPS; falta verificar allí conectividad y vinculación reales.
