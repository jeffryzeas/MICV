# Formularios, galería de CV y alertas normales

Actualización de interfaz para Mi CV con el módulo de postulaciones ya instalado.

## Cambios

- Formularios de Gmail, empresas y preparación del envío con campos, espaciado, paneles e iconos siguiendo «Registrar cliente».
- Secciones de destinatario, diseño del CV y mensaje; índice para moverse entre ellas, opción de ver por pasos y barra inferior para revisar el borrador.
- Galería visual compartida con el perfil: 31 diseños con miniaturas, búsqueda, filtros y selección. El paquete incluye las 31 imágenes de ejemplo.
- Vista previa del CV real de la persona seleccionada. Actualiza diseño, colores, fotografía e idioma. El formato ATS omite fotografía y los diseños con colores propios deshabilitan las paletas.
- Alertas de éxito y error centradas, con botón «Aceptar», sin temporizador. Se sustituyen los avisos de éxito que desaparecían solos en el diseño compartido de las páginas autenticadas.
- Confirmaciones con SweetAlert2 al guardar Gmail y empresas, preparar un borrador, confirmar el envío, desconectar Gmail, descartar borradores y reemplazar una redacción existente. Cancelar conserva lo escrito.
- La confirmación vuelve a enviar el formulario por el mecanismo normal del navegador: conserva la validación y los valores de sus botones, y evita confirmar dos veces a la vez.

## Instalar

Este ZIP actualiza la interfaz del módulo «CV a empresas» entregado anteriormente. Suba `Mi-CV-Formularios-Galeria-Alertas.zip` a `/root/` del VPS y ejecute:

```bash
cd /var/www/micv
tar -czf "/root/micv-antes-formularios-$(date +%Y%m%d-%H%M%S).tar.gz" resources/views public/css public/js public/images/cv
unzip -o /root/Mi-CV-Formularios-Galeria-Alertas.zip -d /var/www/micv
sudo -u www-data /usr/bin/php artisan optimize:clear
```

Recargue con Ctrl+F5. El ZIP contiene directamente `resources/` y `public/` y el archivo de instrucciones. Se incluyen los estilos compilados y las miniaturas. No necesita npm, Composer ni migraciones para este ajuste.

Para activar el servicio de correo si un envío continúa esperando en cola, use el instalador del módulo anterior:

```bash
sudo bash bin/instalar-postulaciones.sh
journalctl -u micv-postulaciones -n 50 --no-pager
```

## Comprobación

- Suite PHP completa: 131 pruebas, 1477 comprobaciones correctas.
- Chromium: formularios a 1440 y 390 píxeles, modo oscuro, iconos y ausencia de desbordamiento general.
- Galería con 31 miniaturas; búsqueda, filtros, selección y cierre con Escape. Enter en el buscador no envía el formulario.
- Selección de Lateral y ATS, cambio de colores, vista previa, campos realmente incluidos en el formulario y conservación del mensaje al cancelar.
- Avisos con botón «Aceptar» y sin cierre automático; confirmaciones de Gmail, empresas, borrador y envío.
- Prueba del borrador y la confirmación con PDF real y transporte SMTP simulado; no se enviaron correos reales.
- La galería del perfil utiliza el mismo componente y conserva su funcionamiento.

Las comprobaciones se hicieron con datos ficticios en el entorno local. El servicio de correo y la conexión real con Gmail se verifican en su VPS.
