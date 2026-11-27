# Correo formal para enviar currículums

Actualización del módulo «CV a empresas» de Mi CV.

El mensaje se presenta como un correo personal de trabajo: fondo blanco, tipografía sencilla, saludo, párrafos y firma. Se eliminaron el encabezado promocional, el nombre grande repetido, las tarjetas, la franja de color y el pie de contacto automático. El PDF permanece adjunto al mensaje.

La redacción automática propone un asunto como «Solicitud de empleo: Asistente administrativa — Nombre» y un mensaje formal con los datos reales del CV. Todo sigue siendo editable antes de preparar el borrador. La vista de revisión utiliza títulos más claros y da más espacio al mensaje.

## Aplicar en el VPS

Suba `Mi-CV-Correo-Formal.zip` a `/root/` y ejecute:

```bash
cd /var/www/micv
tar -czf "/root/micv-antes-correo-formal-$(date +%Y%m%d-%H%M%S).tar.gz" app/Services/PostulacionRedaccion.php resources/views/postulaciones public/css/postulaciones.css
unzip -o /root/Mi-CV-Correo-Formal.zip -d /var/www/micv
sudo -u www-data /usr/bin/php artisan optimize:clear
```

Después recargue con Ctrl+F5 y prepare un **nuevo borrador**. El cambio se aplica a los mensajes preparados a partir de esta actualización. Los borradores anteriores y el historial conservan la copia del correo que se guardó al prepararlos, junto con su versión del CV. Para ver el nuevo formato a partir de uno anterior, use «Preparar otro envío con mi CV actual».

No requiere migraciones, npm ni Composer. La cuenta de Gmail y el proceso de envío mantienen su configuración actual.

## Verificación

- 20 pruebas de postulaciones y transporte del correo: 135 comprobaciones correctas.
- Comprobación visual en Chromium a 900 y 390 píxeles: párrafos, saltos de línea y firma, sin desbordamiento horizontal.
- Caracteres especiales y contenido HTML escrito por el usuario se muestran como texto seguro.
- La versión de texto y el adjunto PDF siguen presentes en el mensaje SMTP.
- No se enviaron correos reales durante estas pruebas.
