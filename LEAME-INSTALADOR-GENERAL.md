# Mi CV · Instalación general, selector de CV y PDF temporales

Versión de código: **2026.11-integral**. El paquete contiene el proyecto para Git y un VPS nuevo, sin .env, bases de datos, sesiones, documentos de usuarios ni dependencias instaladas. Los scripts quedan versionados: no hace falta repetir los parches Python de las entregas anteriores.

## Actualizar el VPS existente

Conserve una copia de la base de datos, .env y storage. Incorpore los archivos a su repositorio, revise los cambios y publique su commit. En el servidor, actualice desde la rama que despliega y ejecute:

```bash
cd /var/www/micv
git pull --ff-only
sudo bash bin/instalar-sistema.sh
```

Si el VPS tiene modificaciones locales de versiones anteriores, conserve esas modificaciones y resuélvalas antes del pull; no use reset --hard. El instalador conserva APP_KEY y las credenciales. No cambia el sitio web existente cuando no se indica --dominio. Detiene/reinicia trabajadores al actualizar: programe una ventana de mantenimiento para los procesos en curso.

## Otro VPS nuevo

Compatible con Ubuntu 24.04/26.04 o Debian 12+, systemd y PHP 8.2+. Se requiere salida de red para descargar dependencias y para acceder a los proveedores. El motor Chrome suministrado por Puppeteer se utiliza en Linux x86_64; para otra arquitectura prepare Chromium compatible e indique MICV_CHROME_PATH.

Copie o clone el proyecto completo en `/var/www/micv/`. Para una instalación nueva sin .env:

```bash
cd /var/www/micv
sudo bash bin/instalar-sistema.sh --dominio micv.su-dominio.com
```

Se crea una base SQLite **privada** para iniciar sin inventar contraseñas de MySQL. Se genera una clave nueva solo al instalar desde cero; el asistente de consola solicita la primera cuenta administrativa. No se cargan usuarios de demostración.

Para crear también HTTPS, el DNS del dominio debe apuntar al VPS y los puertos 80/443 deben estar disponibles:

```bash
sudo bash bin/instalar-sistema.sh --dominio micv.su-dominio.com --https-correo administrador@su-dominio.com
```

Para MySQL nuevo, prepare antes .env con DB_CONNECTION=mysql y sus datos de conexión. Use --generar-clave únicamente si es una base nueva y APP_KEY está vacío. Si MySQL es local en 127.0.0.1/localhost:3306, el instalador puede instalar MariaDB y crear la base y un usuario nuevo desde la configuración, mediante el acceso root del socket local. No cambia contraseñas ni permisos de usuarios existentes. Una base remota o con configuración personalizada debe estar creada y accesible. Si migra datos existentes, copie la APP_KEY original; no genere otra.

Al migrar una instalación real debe trasladar su base, .env y storage mediante su copia de seguridad, además del código. La sesión de Telegram y las claves cifradas dependen de APP_KEY. Coordine el apagado de los trabajadores antiguos antes de arrancar los de la copia nueva para no ejecutar dos instalaciones sobre trabajos duplicados.

## Qué instala y activa

- PHP CLI/FPM y extensiones, Composer, Nginx, Python/venv, FFmpeg, eSpeak y librerías de Chromium mediante APT.
- Node 22 LTS privado bajo /opt/micv cuando no hay Node compatible; descarga oficial y comprobación SHA-256. Usa composer.lock y package-lock.json.
- Chrome de Puppeteer, o MICV_CHROME_PATH si ya dispone de un navegador compatible.
- Entornos independientes de TikTokLive y Telethon; permisos de escritura para storage/cache y lectura del código por el usuario web.
- Todas las migraciones pendientes, limpieza de cachés y comprobación real de PDF en memoria.
- Servicios persistentes de trabajos generales, correo a empresas, video promocional, captura TikTok, fotos, informes TikTok, bot de comentarios, envíos desde Telegram del sistema y vinculación QR.
- Programador de Laravel cada minuto y limpieza de informes TikTok cada quince segundos. Si ya existe un cron de schedule:run para este proyecto, se conserva para evitar duplicarlo con el temporizador.
- Configuración HTTP de Nginx solo cuando se indica --dominio; HTTPS mediante Certbot si se indica --https-correo. Los archivos manuales con nombres en conflicto no se sustituyen. Se conserva el sitio HTTPS existente al actualizar sin solicitar nueva configuración.
- Pocket TTS cuando la CPU x86_64 admite AVX2 y aún no está instalado, y el modelo de eliminación de fondo si falta. Si un proveedor impide descargar un modelo opcional, se avisa; el video puede utilizar eSpeak y el modelo puede descargarse después con su comando.

La configuración de Gmail/Telegram se realiza en el panel y los secretos existentes permanecen cifrados. No se solicitan ni envían códigos de Telegram, correos o mensajes de prueba durante la instalación.

Opciones útiles:

```bash
# Plan de servicios, sin instalar, escribir ni arrancar nada:
bash bin/instalar-sistema.sh --plan

# Dependencias del sistema ya preparadas:
sudo bash bin/instalar-sistema.sh --sin-paquetes

# Posponer la descarga de voz neuronal:
sudo bash bin/instalar-sistema.sh --sin-voz-neuronal

# PHP/usuario web diferentes:
sudo MICV_PHP_BIN=/usr/bin/php8.3 MICV_WEB_USUARIO=www-data bash bin/instalar-sistema.sh
```

## Diseño del CV y formularios

En el perfil, **A Telegram** aparece junto a **A mi correo**. Se conservan diseño, colores, idioma y fotografía al abrir el envío. El formulario también permite cambiar esas opciones con la misma galería de 31 diseños y vista previa del perfil. Los usuarios continúan utilizando la cuenta de Telegram del sistema, sin configurar una cuenta propia.

Las pantallas de envío usan componentes Blade, campos nativos, secciones separadas, iconos y estilos de gestión compartidos con Mi CV. El historial del correo ya no carga automáticamente un PDF archivado al abrirlo: presenta el registro y un botón de regeneración.

## PDF temporales y registro de envíos

**Al actualizar se retiran los PDF archivados de envíos terminados**, tanto de correo a empresas como de Telegram. Se conservan destinatario, fecha, texto, diseño/opciones, resultado, tamaño y hash del archivo preparado como metadatos del registro. Las copias ya entregadas permanecen en el destino y las fotos/documentos originales del perfil no se eliminan por esta limpieza.

El PDF se mantiene temporalmente durante preparación, revisión, cola y envío, para que el archivo confirmado coincida con el que se transmite. Se elimina al terminar en enviado, fallido o incierto. Una limpieza cada minuto reintenta eliminaciones pendientes, caduca borradores/colas de más de 24 horas y retira temporales de procesos interrumpidos de más de quince minutos, marcándolos como inciertos para revisión.

**Generar CV con datos actuales** usa el perfil actual y el diseño/opciones guardados en el registro, genera los bytes en memoria y los descarga; no guarda otro PDF ni una copia histórica de los datos del perfil. La descarga regenerada puede diferir del archivo enviado anteriormente. El hash del registro corresponde al archivo preparado original y no se sustituye por el de la descarga nueva. Se requieren permisos vigentes sobre el perfil para regenerarlo.

Esta política no cambia la conservación de comentarios/fotos originales de TikTok. Sus informes PDF/ZIP siguen caducando a los cinco minutos conforme al módulo de auditoría.

## Diagnóstico y comprobación

```bash
sudo -u www-data php artisan micv:comprobar-servidor --pdf
sudo -u www-data php artisan micv:cv-limpiar-temporales
systemctl status micv-postulaciones micv-video micv-telegram-cv micv-telegram-login --no-pager -l
systemctl list-timers 'micv-*' --no-pager
journalctl -u micv-postulaciones -u micv-video -u micv-telegram-cv -n 80 --no-pager
```

Verificación: **250 pruebas PHP (2405 aserciones)**, **4 pruebas del instalador**, **19 del adaptador Telegram/QR** y **37 del capturador TikTok**.

El código y las pruebas están preparados localmente. Se verificaron sintaxis de scripts, plantillas de servicios, modo plan sin cambios, conservación de claves/configuración, eliminación limitada de PDF, estados/colas y regeneración con permisos. Chromium comprobó selector, colores/idioma/foto, acciones del perfil, móvil/oscuro y envíos simulados con PDF real. No se enviaron mensajes reales ni se aprovisionó un VPS nuevo desde este entorno; las descargas, systemd, Nginx, DNS y certificados se comprueban al ejecutar en el servidor.
