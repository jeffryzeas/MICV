# Mi CV · LIVE, perfil público y comentarios de TikTok

Actualización del módulo exclusivo de administradores del proyecto Laravel existente. El ZIP tiene las carpetas del proyecto en su raíz. No contiene `.env`, credenciales, base de datos, `vendor`, `node_modules` ni rutas operativas de pruebas.

## Cambios de esta versión

- **La captura continúa al cerrar sesión o salir del navegador.** El servicio del VPS consulta las cuentas activadas y guarda los eventos sin depender de la sesión web. El reproductor sí requiere una sesión de administrador.
- **Los comentarios públicos de participantes con perfil privado se guardan igual.** La privacidad del perfil personal no se usa como filtro de mensajes de un LIVE público, ni como indicador de privacidad del LIVE. No se consulta el contenido privado del participante.
- Se procesa también el primer lote de mensajes que TikTok entregue al conectar. Los identificadores de mensaje y sala evitan duplicados.
- Si el SDK dice `UserOfflineError`, se contrasta una vez con la API pública de sala. Si esta devuelve un identificador, el SDK vuelve a comprobar que esté en LIVE antes de conectar. No se omiten controles de acceso ni se usa una sesión de TikTok.
- El intervalo predeterminado pasa a **30 segundos**. Una cuenta sin LIVE sigue activada para nuevas comprobaciones. Si hay límites del proveedor, se respeta una espera mayor. Si su `.env` ya fija otro intervalo, se conserva ese valor.
- El historial de lives y sus contadores se actualizan automáticamente, aunque todavía no haya comentarios. Ahora se puede buscar por título y ordenar por nombre, fecha o cantidad de comentarios.
- Fechas y horas en formato **AM/PM**, con la zona configurada visible en el perfil y los informes.
- Tarjeta con **foto, nombre y biografía públicos**, y región/país cuando TikTok los proporcione. Si falta un dato, no se inventa ni se deduce por IP. La región se muestra como información proporcionada por TikTok.
- Botón para **eliminar una cuenta de monitoreo y su historial local**, con confirmación normal. Detiene su monitoreo, borra comentarios, lives e informes locales y no modifica la cuenta original de TikTok.
- Reproductor **HLS.js 1.7.4** dentro del panel, cuando la sala entregue una señal HLS pública compatible. La alternativa «Abrir en TikTok» sigue disponible. La reproducción y la captura de comentarios son independientes.

## Lo que muestran los estados

**Monitoreo automático activado** significa que la cuenta está programada para comprobarse, no que se haya establecido una conexión. **En directo** indica que se conectó a una sala. **Señal pública disponible** indica que la sala entregó una URL compatible; la reproducción todavía puede fallar por vencimiento, rechazo del CDN o incompatibilidad de navegador/formato.

El estado global del capturador se basa en un latido del servicio. El registro diferencia el inicio del servicio, los intentos y el guardado de un LIVE. Ejemplos de formato, no resultados garantizados:

```text
TikTok @usuario · sin_live · UserOfflineError
TikTok @usuario · verificando_sala · ConsultaPublica
TikTok @usuario · LIVE guardado · sala 7000000000000000001 · 2 comentarios nuevos
```

El registro que solo dice `Started micv-tiktok.service` confirma el arranque. `UserOfflineError` significa que la biblioteca no detectó un LIVE, no que un comentarista tuviera perfil privado. La nueva comprobación alternativa puede ayudar si el HTML está desactualizado; no garantiza resolver una respuesta errónea o un bloqueo de TikTok desde el VPS.

## Instalación o actualización en el VPS

Haga una copia del código y de la base de datos actuales. Suba `Mi-CV-TikTok-Live-Perfil.zip` a `/var/www/micv/` y ejecute:

```bash
cd /var/www/micv
unzip -o Mi-CV-TikTok-Live-Perfil.zip
sudo bash bin/instalar-tiktok.sh
```

Recargue el navegador con Ctrl+F5, entre como administrador y abra **TikTok · Lives y comentarios**. Registre un `@usuario` y pulse **Activar captura**. Las cuentas ya activadas conservan esa preferencia. Si la versión anterior dejó una cuenta pausada, actívela una vez; el instalador no activa indiscriminadamente cuentas pausadas.

El instalador crea el entorno Python aislado `storage/app/tiktok-venv`, instala **TikTokLive 7.0.1**, limpia cachés y ejecuta únicamente la migración original del módulo si aún falta. Esta versión aprovecha la columna JSON de perfil existente y no cambia las tablas ya creadas.

Mantiene dos servicios con arranque automático:

- `micv-tiktok.service`: supervisor PHP con procesos Python de captura. Sin reinicios programados cada hora.
- `micv-tiktok-pdf.service`: trabajador de la cola independiente `tiktok-exportaciones`.

No modifica los servicios de correo o de video promocional. Reiniciar servicios al actualizar produce una interrupción breve; pueden perderse eventos durante cualquier desconexión y no hay recuperación garantizada de mensajes anteriores.

Requisitos: proyecto Laravel instalado y operativo con PHP CLI, base de datos y cola `jobs`, Python 3.10+, Ubuntu/systemd, salida HTTPS hacia TikTok y su proveedor de firma. La reproducción y las fotos mediante el servidor requieren **PHP cURL**, DNS y certificados válidos. Los PDF usan el motor existente de Mi CV (Node, Puppeteer y Chromium). HLS.js y su licencia ya van incluidos; no hace falta instalar Node adicional para el reproductor.

Si PHP CLI o el usuario web tienen otro nombre/ruta:

```bash
sudo MICV_PHP_BIN=/usr/bin/php MICV_WEB_USUARIO=www-data bash bin/instalar-tiktok.sh
```

Opcionales en `.env`:

```dotenv
TIKTOK_SIMULTANEOS=3
TIKTOK_INTERVALO=30
TIKTOK_ZONA=America/Costa_Rica
```

Ejemplo para mostrar horas de Nicaragua: `TIKTOK_ZONA=America/Managua`. La región del perfil es un dato independiente de esta zona de visualización. El límite de conexiones está acotado entre 1 y 10; por defecto hay tres. Las demás cuentas esperan un cupo. El intervalo mínimo es 30 segundos. Ante un límite de peticiones se espera al menos 300 segundos, conservando una espera mayor del proveedor, hasta 24 horas. Un rechazo de conexión o un fallo de firma también espera al menos 300 segundos.

Después de cambiar estas opciones:

```bash
sudo -u www-data php artisan config:clear
sudo systemctl restart micv-tiktok micv-tiktok-pdf
```

## Prueba en su cuenta

1. Active el monitoreo del @usuario exacto que emite el LIVE.
2. Mantenga abierto un LIVE público mientras se realizan las comprobaciones. El panel mostrará por separado si el servicio está activo y si se conectó a la sala.
3. Escriba un comentario visible en ese LIVE, incluso desde una cuenta cuyo perfil sea privado. Si TikTok lo entrega al capturador, se guardará.
4. En el panel revise **Lives guardados**, los comentarios y el registro del servicio. La lista se actualiza sin recargar.
5. Si aparece una señal compatible, pulse **Reproducir LIVE**. El video comienza silenciado; puede activar el sonido en los controles.
6. Puede cerrar sesión: el servicio seguirá activo. Al volver, encontrará el historial guardado. El módulo archiva comentarios y metadatos del LIVE, **no graba el video**.

```bash
systemctl status micv-tiktok micv-tiktok-pdf --no-pager -l
journalctl -u micv-tiktok -u micv-tiktok-pdf -n 80 --no-pager
sudo -u www-data storage/app/tiktok-venv/bin/python -c 'from TikTokLive import TikTokLiveClient; print("TikTokLive disponible")'
sudo -u www-data php artisan migrate:status
```

Si un LIVE está abierto y el registro insiste en `sin_live`, conserve el diagnóstico de esa misma prueba. Un LIVE no detectado no se registra falsamente como capturado. Si el servicio está sin señal, revise systemd, permisos y conectividad. No cambie de versión de TikTokLive a ciegas: puede requerir adaptar los eventos y sus pruebas.

## Límites y reproducción

Se utiliza una biblioteca **no oficial**. TikTok o el servicio de firma pueden bloquear peticiones, limitar tráfico o cambiar su protocolo. No se garantiza capturar todos los mensajes ni detectar todos los LIVE. Las restricciones de acceso confirmadas se respetan. No se usan contraseñas, cookies de una cuenta de TikTok o proxies para entrar a contenido privado.

La reproducción solo admite URLs HTTPS de determinados CDN de TikTok recibidas de la sala pública. El backend valida el origen y las direcciones DNS, fija la dirección de conexión, no sigue redirecciones y sirve manifiestos/segmentos por rutas de administrador con tokens temporales. No acepta una URL libre del navegador. Las URLs firmadas originales no se entregan en el HTML ni en el JSON del panel. Los manifiestos protegidos/cifrados no se reproducen. Si TikTok no entrega una señal compatible o rechaza su lectura, el panel ofrece abrir el LIVE en TikTok.

La información pública de sala y la señal se vuelven a consultar aproximadamente cada cinco minutos durante una captura conectada; un fallo de esa actualización de video no desactiva la captura de comentarios. No se consultan álbumes o publicaciones privadas.

Los metadatos de reproducción se guardan cifrados bajo `storage/app/private/tiktok/reproduccion/`. Los informes privados están en `storage/app/private/tiktok/exportaciones/`. No publique ni enlace estos directorios con `storage:link`. Los comentarios se conservan hasta eliminar el monitoreo: tenga en cuenta el crecimiento de la base y sus copias de seguridad.

## Filtros, publicaciones e informes

El filtro inicial de comentarios muestra el día actual en la zona configurada. Para días anteriores cambie las fechas. **Ver comentarios** de un LIVE aplica el intervalo de fechas de su captura. Las búsquedas admiten texto y autor. El panel conserva una ventana de hasta 300 comentarios para evitar saturar el navegador; la base y los informes incluyen los registros guardados que correspondan a los filtros.

Puede registrar la URL canónica de una publicación pública de esa cuenta y verla en el reproductor oficial de TikTok. TikTok controla su disponibilidad. **TikTokLive no obtiene automáticamente todas las publicaciones ni sus comentarios**: estos se incorporan mediante un JSON/CSV autorizado, con origen claramente identificado como importación.

Ejemplo de JSON UTF-8 (identificadores como cadenas):

```json
[
  {
    "id": "ejemplo-001",
    "autor": "usuario_ejemplo",
    "nombre": "Participante de ejemplo",
    "texto": "Comentario obtenido de una fuente autorizada.",
    "fecha": "2026-11-22T10:30:00-06:00"
  }
]
```

CSV equivalente, separado por comas, con cabecera `id,autor,nombre,texto,fecha`. Máximo 5000 filas y 5 MB. Use fechas ISO con zona; si se omite, se interpreta la configurada. Una fila inválida rechaza toda la importación. Los duplicados por publicación e identificador se omiten.

**Exportar estos comentarios a PDF** conserva filtros y fija el conjunto de registros existentes al solicitarlo. El informe A4 se genera en segundo plano, con hasta 1000 comentarios por PDF. Varias partes se descargan en un ZIP. Solo el administrador que solicitó el informe puede descargarlo. Si queda en cola más de dos minutos, el panel indica revisar `micv-tiktok-pdf`.

## Verificación realizada

- Suite completa PHP: **153 pruebas, 1638 aserciones**. Incluye permisos, separación de salas y días, deduplicación, importación, PDF, reintentos, logout sin pausar, historial en tiempo real, orden, perfil público, eliminación y acceso al video protegido por administrador.
- Python: **20 pruebas**, incluidos comentarios públicos de participantes con perfil privado, primer lote al conectar, comprobación alternativa de sala, restricciones, errores sin datos sensibles, límites y tiempos de conexión.
- Chromium: reproducción real de un HLS local H.264/AAC de prueba, con manifiesto/segmentos servidos a través de las rutas Laravel y comprobación de dimensiones y tiempo de reproducción. Historial y comentarios actualizados sin recargar, foto y región de ejemplo, orden por nombre, tema oscuro, móvil y eliminación con confirmación.
- Se hizo una consulta pública de `@jeffryzeas` desde el entorno de trabajo: en ese momento devolvió `UserOfflineError`, también tras la comprobación alternativa. No acredita qué ocurrió durante la prueba anterior del VPS.

**No se ha confirmado una captura o reproducción de un LIVE real de TikTok ni se ha instalado esta actualización en su VPS.** Los casos positivos de captura y video se probaron con eventos simulados y una señal local. La descarga PDF se probó previamente con su plantilla real y datos ficticios; el Chromium administrado requiere cargar ese HTML en memoria, mientras producción conserva el motor `CvPdf` existente.

## Fuentes y dependencias

- TikTokLive 7.0.1: https://pypi.org/project/TikTokLive/7.0.1/ · https://github.com/isaackogan/TikTokLive · licencia publicada: Modified AGPL-3.0.
- HLS.js 1.7.4: https://github.com/video-dev/hls.js · paquete NPM verificado con su integridad SHA512 antes de incluir `public/vendor/hls.min.js`; licencia Apache-2.0 en `public/vendor/hls-LICENSE.txt`.
- Reproductor oficial de publicaciones: https://developers.tiktok.com/doc/embed-player
