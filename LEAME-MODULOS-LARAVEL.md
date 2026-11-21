# Corrección visual de Usuarios, Correo y Documentos

Actualización para el proyecto Mi CV entregado en esta conversación. Conserva Laravel Blade y Tailwind, las rutas, formularios, permisos y funciones existentes.

## Cambios

- Clientes y usuarios: encabezado con acciones, totales discretos, filtros alineados, listado uniforme y exportación desplegable.
- Administración de usuarios: tabla con columnas para cuenta, rol, negocio y estado; filtros compactos; formulario continuo con secciones para datos, permisos y contraseña.
- Correos de clientes: listado con búsqueda y paginación, registro ordenado y comprobante con datos y actualización separados.
- Correo del sistema: configuración de Gmail con los mismos controles y etiquetas asociadas a sus campos.
- Documentos: área de recorte, herramientas e impresión más claras. El zoom, los ajustes manuales y los márgenes se despliegan cuando se necesitan. Conserva frente/reverso, perspectiva, filtros, historial, medidas reales y descargas.
- Estilos propios de estos módulos, con modo oscuro y adaptación a celular. Las tablas se desplazan horizontalmente en pantallas pequeñas.

## Instalación en el VPS

Este ZIP es una actualización; no contiene el sistema completo. Suba `Correccion-Modulos-Laravel.zip` a `/root/` del VPS. Ejecute como root:

```bash
cd /var/www/micv
# Respaldo de las vistas y estilos actuales antes de reemplazarlos.
tar -czf "/root/micv-vistas-$(date +%Y%m%d-%H%M%S).tar.gz" resources/views public/css
unzip -o /root/Correccion-Modulos-Laravel.zip -d /var/www/micv
sudo -u www-data /usr/bin/php /var/www/micv/artisan optimize:clear
```

El ZIP contiene directamente `resources/` y `public/`: no hay una carpeta adicional que deba mover. Después recargue la página con Ctrl+F5. Los archivos CSS usan una versión basada en su fecha para renovar la caché del navegador.

No necesita migraciones, Composer, npm ni cambios en el servicio de video. No contiene `.env`, bases de datos, contraseñas ni datos reales de clientes.

## Verificación realizada

- 24 pruebas funcionales de usuarios, correos, permisos y exportaciones: 197 comprobaciones correctas.
- 5 pruebas del procesamiento de documentos correctas.
- Vistas Blade compiladas correctamente y CSS compilado.
- Revisión en Chromium de ocho pantallas a 1440 px y 390 px, con modo claro y oscuro, sin desbordamiento general ni errores JavaScript.
- Consulta de ficha en el formulario de correo, tipos de documento, visibilidad de contraseña, selección de roles y controles de acceso.
- Dos fotos, giro/deshacer, blanco y negro, distribución de cuatro caras y bloqueo de medidas que no caben; descarga de PDF y PNG válidos.

Las comprobaciones se hicieron en un entorno local con datos ficticios. La instalación y las pruebas finales en su VPS quedan a cargo de quien aplique este paquete.
