> **Actualización vigente:** consulte LEAME-INSTALADOR-GENERAL.md. Los PDF de envíos terminados se retiran; las descargas se regeneran con los datos actuales del perfil y las opciones guardadas. El instalador general reúne los servicios.

# CV a empresas: Gmail por cuenta, empresas e historial

Esta actualización agrega los módulos al proyecto Mi CV de esta conversación. Mantiene Laravel Blade, Tailwind y Symfony Mailer, sin contratar servicios de IA ni añadir paquetes de Composer o npm.

## Qué incluye

- **Gmail por cuenta:** dirección, nombre del remitente y contraseña de aplicación de 16 letras, cifrada con la APP_KEY. Guardar, comprobar conexión sin enviar correos, activar y desconectar. La clave guardada nunca se muestra en formularios ni historiales.
- **Empresas destinatarias:** nombre, correo, contacto, cargo opcional y notas internas. Si no indica cargo, se redacta una candidatura a las oportunidades disponibles. Permite archivar empresas; el administrador puede compartir empresas en el catálogo.
- **Nuevo envío:** selección de remitente, persona del CV, empresa, cargo, plantilla, colores, fotografía e idioma del PDF. Genera una propuesta editable de asunto y mensaje a partir del CV, sin inventar formación ni experiencia.
- **Revisión y confirmación:** prepara un borrador con correo HTML, texto y PDF privado. Puede revisar y descargar la versión del CV antes de confirmar. Un borrador no envía mensajes.
- **Historial:** destinatario, remitente, candidato, empresa, cargo, fecha, mensaje y copia exacta del PDF. Cambiar después el perfil, la empresa o el diseño no modifica las copias ya preparadas o enviadas.
- **Administración:** acceso al historial completo y posibilidad de configurar y enviar desde las cuentas de Gmail conectadas, seleccionando el CV de cualquier cliente activo. Cada usuario ve los envíos de su cuenta y las copias de su propio CV enviadas por administración. Ver una copia de un envío desde una cuenta ajena no permite usar esa cuenta para enviar.
- **Operadores de negocio:** con permiso «Generar CV», pueden usar su propio Gmail para los clientes a los que tienen acceso. Sin ese permiso no tienen acceso al módulo.
- **Privacidad:** PDF almacenado en disco privado y protegido por sesión; claves excluidas de la sesión de errores, exportación y registros. Se eliminan los registros y sus archivos al borrar la ficha o la cuenta relacionada.

El Gmail de postulaciones es independiente de la cuenta global que envía códigos y avisos del sistema.

## Instalar en su VPS Ubuntu

Suba `Mi-CV-Postulaciones-Gmail.zip` a `/root/`. Como root, ejecute:

```bash
cd /var/www/micv
# Respaldo del código que será actualizado. Respalde también su base de datos antes de migrar.
tar -czf "/root/micv-antes-postulaciones-$(date +%Y%m%d-%H%M%S).tar.gz" app routes bootstrap config resources/views public/css public/js database/migrations bin
unzip -o /root/Mi-CV-Postulaciones-Gmail.zip -d /var/www/micv
sudo bash bin/instalar-postulaciones.sh
```

El ZIP contiene directamente los directorios del proyecto. El instalador:

1. Limpia la configuración y las vistas compiladas.
2. Ejecuta únicamente la migración nueva, que agrega `gmail_cuentas`, `postulacion_empresas` y `postulaciones`.
3. Prepara el almacenamiento privado de los CV.
4. Instala y activa `micv-postulaciones.service`, con la cola `postulaciones`, para el usuario `www-data`.

No cambia la contraseña de acceso al sistema, la configuración de correo global ni el servicio del video. No contiene `.env`, claves reales, datos reales ni librerías completas. Conserve su APP_KEY: es necesaria para descifrar las cuentas configuradas.

Si el PHP de consola o el usuario web son distintos:

```bash
sudo MICV_PHP_BIN=/ruta/a/php MICV_WEB_USUARIO=usuario-web bash bin/instalar-postulaciones.sh
```

Para otros servidores sin systemd, ejecute la migración y mantenga activo este trabajador mediante su gestor de procesos:

```bash
php artisan optimize:clear
php artisan migrate --force --path=database/migrations/2026_11_22_000001_create_postulaciones_tables.php
php artisan queue:work postulaciones --queue=postulaciones --sleep=2 --tries=1 --timeout=90
```

## Primer envío

1. Recargue con Ctrl+F5 y abra **CV a empresas → Gmail por cuenta**.
2. Ingrese una dirección de Gmail o Google Workspace. Active la verificación en dos pasos en Google y cree una contraseña en https://myaccount.google.com/apppasswords. Son 16 letras, no la contraseña normal de Gmail.
3. Guarde y pulse **Comprobar conexión**. Si Google no ofrece contraseñas de aplicaciones, revise las restricciones de esa cuenta o consulte a su administrador de Google Workspace.
4. En **Empresas destinatarias**, registre el correo de recepción de CV. Cargo y persona de contacto son opcionales.
5. En **Nuevo envío**, seleccione persona, empresa y diseño. Genere y revise la redacción; también puede escribirla manualmente.
6. Pulse **Preparar y revisar envío**. Compruebe el correo y el PDF; marque la confirmación y pulse **Confirmar y enviar CV**.
7. Consulte el resultado en **Envíos e historial**. El CV que descarga allí es la copia guardada de esa postulación, no una regeneración del perfil actual.

La administración puede seleccionar otro usuario en la configuración de Gmail y cualquier cuenta conectada en el remitente. El candidato puede consultar en su historial una copia de los correos que administración haya preparado o enviado con su CV.

## Estados y fallos de conexión

- **Pendiente de confirmar:** borrador, sin enviar.
- **En cola:** espera al servicio de correo. Pasados dos minutos, el detalle indica que administración debe revisar el trabajador.
- **Enviando:** el trabajador reclamó el envío y está conectando con Gmail.
- **Aceptado por Gmail:** Gmail confirmó la aceptación del mensaje. No confirma recepción, lectura, respuesta ni que no termine en spam. No se utilizan píxeles de seguimiento.
- **No enviado:** faltan permisos, cambió la cuenta o no está disponible el PDF original; el transporte no se inició.
- **Revisar envío:** hubo una interrupción o no se pudo confirmar el resultado del transporte. Revise **Enviados** en Gmail antes de preparar otro envío.

Los clics repetidos y la recepción duplicada de un mismo trabajo no duplican el mensaje. Las incidencias SMTP no se reintentan automáticamente porque una desconexión puede ocurrir después de que Gmail haya aceptado el correo. Un trabajador interrumpido se detecta también al consultar el estado. Desconectar Gmail o modificar su contraseña impide usar las credenciales anteriores para trabajos pendientes.

El PDF se limita a 18 MB para dejar margen para la codificación del adjunto. Se aplican los límites existentes de envíos del sistema; Google mantiene además sus propios límites de uso.

## Diagnóstico en el VPS

```bash
systemctl status micv-postulaciones --no-pager -l
journalctl -u micv-postulaciones -n 50 --no-pager
```

Si no autentica con Google, revise la dirección, la contraseña de aplicación y la salida de red del VPS a `smtp.gmail.com:587` con TLS. Si no prepara el PDF, revise primero que la generación habitual del CV funcione: este módulo usa el mismo motor de PDF instalado.

Para revertir, detenga `micv-postulaciones`, restaure el respaldo del código y revise la migración antes de deshacerla. El rollback de esta migración elimina cuentas conectadas, empresas e historial de postulaciones. Los correos ya aceptados por Gmail no se pueden retirar desde este módulo.

## Verificaciones realizadas

- Suite funcional completa: 131 pruebas y más de 1400 comprobaciones, incluidas 20 pruebas específicas de postulaciones y MIME/adjunto.
- Separación entre usuarios, administrador y operadores; claves cifradas y sin exposición; empresas privadas y compartidas; confirmación obligatoria; idempotencia; cambios de credenciales, archivo alterado y errores de transporte.
- Vistas y formularios en Chromium a 1440 px y 390 px, con modo oscuro; flujo real de formularios y sesión, redacción editable, archivo PDF válido, cola de base de datos, actualización del estado e historial.
- Mensaje SMTP simulado con remitente, destinatario, HTML, texto y los bytes exactos del PDF. No se enviaron correos reales ni se utilizaron claves de Gmail reales.
- La política del Chromium administrado del entorno de pruebas bloquea URL `file://`; para la prueba visual del PDF se renderizó la misma plantilla en memoria. En producción se conserva el motor Browsershot ya utilizado por el proyecto.
- Sintaxis PHP y JavaScript, compilación de vistas y CSS, y sintaxis del instalador comprobadas. El servicio systemd debe activarse en su VPS con el instalador.
