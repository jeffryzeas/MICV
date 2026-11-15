# Mi CV · Edición web

El proyecto se usa desde el navegador. El paquete contiene Laravel, sus vistas, los recursos web y las migraciones necesarias para instalaciones existentes. Quedaron retirados el cliente Python, el instalador Windows, la compilación de escritorio, las licencias de equipos y la API de sincronización local.

## Actualizar una instalación existente

1. Si todavía hay operaciones pendientes en algún equipo, sincronícelas desde la versión anterior y compruebe el resultado en la web antes de actualizar. Conserve un respaldo de ese equipo si alguna operación sigue pendiente.
2. Haga un respaldo de la base y del almacenamiento del servidor. Prepare el ZIP en una carpeta de despliegue nueva. Vincule o copie desde la instalación actual su configuración privada (.env) y el almacenamiento persistente (storage), conservando APP_KEY y la conexión ya configuradas. El directorio público del sitio apunta a public de la nueva versión.
3. Instale las dependencias PHP de producción con Composer y las dependencias Node con npm ci --omit=dev. Conserve la configuración y los binarios de Chromium que se usaban para generar PDF.
4. Desde la raíz de la aplicación, ejecute php artisan optimize:clear y php artisan migrate --force. Compruebe el inicio de sesión y los módulos que utiliza antes de cambiar el directorio público de producción.

Las tablas históricas de licencias, dispositivos y recibos se conservan para evitar eliminar información durante el cambio. Las sesiones emitidas por la antigua aplicación se cierran al llegar a la web. Las operaciones ya sincronizadas siguen registradas en su módulo web. Para retirar el programa de Windows, desinstale Mi CV Escritorio después de comprobar los pendientes y el respaldo.
