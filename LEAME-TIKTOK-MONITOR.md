# Mi CV · TikTok para administración

Actualización para el proyecto Laravel existente. El ZIP tiene las carpetas del proyecto en su raíz y no contiene `.env`, credenciales, base de datos, `vendor`, `node_modules` ni datos de pruebas.

## Corrección de monitoreo automático

Esta versión corrige el tratamiento de `UserNotFoundError`: ya no lo interpreta como un perfil privado ni desactiva el monitoreo. La librería puede producirlo por cuenta no disponible o una respuesta incompleta de TikTok. Compruebe también que el @usuario sea correcto.

- Sin LIVE o ante un fallo temporal, la cuenta conserva **Monitoreo automático activado**. Se comprueba de nuevo y comienza a capturar cuando se detecta un LIVE público, con una conexión disponible. Cerrar el navegador no desactiva la captura.
- El panel separa la activación del monitoreo del estado de conexión y muestra la próxima comprobación. “Activado” no significa que ya exista una conexión de captura.
- La conexión inicial tiene un límite de 60 segundos; este límite no corta un LIVE que ya esté conectado.
- Se distinguen cuenta no disponible, red, tiempo agotado, rechazo HTTP, límite de peticiones, firma y formato incompatible. Se registra únicamente código, clase de error y estado HTTP, sin mensajes remotos, URLs con parámetros, cookies o tokens.
- El supervisor conserva el plazo de reintento indicado por el proveedor al finalizar el proceso; no lo reduce a 90 segundos.
- Las restricciones confirmadas siguen pausando la captura. Esta actualización no recupera contenido privado ni promete resolver un bloqueo externo de TikTok desde el VPS.

Para actualizar, use las instrucciones de instalación de abajo: la migración ya aplicada no se vuelve a crear. La activación previa se conserva. Si la versión anterior dejó una cuenta pausada por error, pulse **Activar captura** una vez. No se activan cuentas pausadas de forma indiscriminada.

Tras actualizar y esperar la siguiente comprobación, revise:

```bash
journalctl -u micv-tiktok -n 60 --no-pager
```

Un diagnóstico tiene este formato (ejemplo, no resultado real de su servidor):

```text
TikTok @usuario · solicitud_rechazada · HTTPStatusError · HTTP 403
```

Ese ejemplo significa que la conexión fue rechazada. Mantener el monitoreo activo no elimina el rechazo. Un registro que solo diga `Started micv-tiktok.service` confirma el arranque, pero no demuestra una conexión a TikTok ni identifica la causa del fallo.

## Funciones incluidas

- Menú **TikTok · Lives y comentarios**, exclusivo de administradores activos, con autorización también en el servidor.
- Registro de cuentas por `@usuario`, activación y pausa del monitoreo, sin borrar el historial.
- Captura de comentarios de LIVE públicos mientras el capturador está conectado. Guarda sala, autor público, nombre, texto, fecha y origen, con deduplicación por mensaje y sala.
- Historial separado por live y filtros por día o intervalo, live, publicación, texto y autor. Las fechas se muestran en `America/Costa_Rica` por defecto; la base almacena milisegundos UTC.
- Actualización del panel cada dos segundos mientras está visible; la captura continúa en el servidor aunque se cierre la página. La ventana limita sus filas para no saturar el navegador; la base y las exportaciones conservan los registros guardados.
- Estado del capturador basado en una señal real del servicio, estados de conexión, pausa, restricciones y próximo reintento. Hasta tres conexiones simultáneas por defecto.
- Enlaces al perfil público y al LIVE en TikTok. **El LIVE se abre en TikTok**, no en un reproductor de transmisión interno. Se conserva únicamente el nombre y la biografía públicos recibidos al conectar al LIVE; no se obtiene un perfil completo fuera de esa conexión.
- Registro manual de URLs de publicaciones públicas con reproductor oficial de TikTok. Comentarios de publicaciones mediante importación autorizada JSON/CSV, identificados como importados.
- Informes PDF A4 privados, ordenados por fecha, con progreso y descarga como archivo. Cada PDF contiene hasta 1000 comentarios; si hacen falta varias partes, se descargan en un ZIP. El informe fija los registros disponibles al solicitarlo y no agrega los que lleguen después. Solo puede descargarlo el administrador que lo solicitó.

## Alcance real de la integración

**No permite entrar a perfiles privados ni eludir restricciones**, ni usa cookies de sesión de una cuenta de TikTok, contraseñas, proxies para saltar bloqueos o credenciales de TikTok. Si se detecta una restricción, la captura se pausa. No recupera comentarios anteriores a la conexión ni garantiza capturar todos los mensajes ante interrupciones, límites o cambios de TikTok.

TikTokLive captura eventos de LIVE públicos: **no ofrece un listado automático de todas las publicaciones o sus comentarios**. En este módulo las publicaciones se registran por URL y sus comentarios se importan desde una fuente autorizada. TikTok decide si permite reproducir cada publicación en su reproductor oficial.

El capturador utiliza la librería no oficial TikTokLive 7.0.1, que puede depender del servicio externo de firma y sus límites. No hay una integración oficial ni una garantía de disponibilidad de TikTok. El módulo espera antes de reintentar cuando hay límites; no intenta evitarlos. La licencia de la dependencia se publica como Modified AGPL-3.0 en PyPI.

## Instalación en el VPS

1. Haga una copia del código y de la base de datos actuales. Suba `Mi-CV-TikTok-Automonitoreo.zip` a `/var/www/micv/`.
2. En la sesión SSH del VPS, ejecute:

```bash
cd /var/www/micv
unzip -o Mi-CV-TikTok-Automonitoreo.zip
sudo bash bin/instalar-tiktok.sh
```

3. Recargue el navegador con Ctrl+F5, entre como administrador, abra **TikTok · Lives y comentarios**, registre un `@usuario` público y pulse **Activar captura**. Las cuentas nuevas quedan pausadas hasta que se activen; las cuentas ya activadas retoman el monitoreo al arrancar el servicio.

El instalador requiere el proyecto base operativo con `.env`, `vendor`, PHP CLI con los módulos que ya necesita Mi CV, acceso a la base de datos, Python 3.10 o posterior, red hacia PyPI/TikTok, Ubuntu con systemd y el motor PDF existente (Node, Puppeteer y Chromium). No reinstala el motor PDF; si los CV ya se generan correctamente, utiliza ese mismo servicio `CvPdf`.

El script crea un entorno Python aislado en `storage/app/tiktok-venv`, instala la versión fijada de TikTokLive, limpia las cachés de Laravel y ejecuta únicamente la migración nueva. Instala y activa:

- `micv-tiktok.service`: supervisor PHP con procesos Python locales para capturar los LIVE públicos.
- `micv-tiktok-pdf.service`: trabajador de la cola independiente `tiktok-exportaciones`.

Ambos arrancan con el VPS y se reinician automáticamente. No modifica los servicios de video o de correo. El supervisor permanece activo sin reinicios programados cada hora. Si se detiene por una incidencia o al reiniciar el VPS, systemd lo arranca nuevamente; pueden perderse eventos durante una interrupción y no hay captura retroactiva.

Si PHP CLI o el usuario web tienen otra ubicación/nombre:

```bash
sudo MICV_PHP_BIN=/usr/bin/php MICV_WEB_USUARIO=www-data bash bin/instalar-tiktok.sh
```

Opcionales en `.env`:

```dotenv
TIKTOK_SIMULTANEOS=3
TIKTOK_INTERVALO=90
TIKTOK_ZONA=America/Costa_Rica
```

El límite de simultáneos está acotado entre 1 y 10; aumentarlo consume recursos y no elimina los límites de terceros. La comprobación de cuentas sin LIVE espera al menos 60 segundos y por defecto 90. Ante un límite de firma espera al menos 300 segundos y conserva una espera mayor indicada por el proveedor, hasta 24 horas. Un rechazo de conexión o fallo del servicio de firma también espera al menos 300 segundos. Tras cambiar estas opciones:

```bash
sudo -u www-data php artisan config:clear
sudo systemctl restart micv-tiktok micv-tiktok-pdf
```

## Uso y diagnóstico

- Active únicamente las cuentas que quiera capturar. Las demás quedan registradas y pausadas.
- Sin un LIVE público disponible, verá el estado correspondiente y una próxima comprobación. Si hay más cuentas activas que conexiones disponibles, las restantes esperan un turno libre.
- El filtro inicial muestra el día actual. Para consultar días anteriores cambie las fechas. En **Lives guardados → Ver comentarios** se aplica el rango de fechas de esa captura.
- En **Exportar estos comentarios a PDF**, los filtros actuales se conservan en el informe. El archivo aparece en **Informes PDF** y se descarga con el botón de descarga.
- Si aparece «Capturador sin señal», el panel no está recibiendo el latido del servicio. Si un PDF permanece en cola más de dos minutos, el panel indica revisar el trabajador de PDF.

```bash
systemctl status micv-tiktok micv-tiktok-pdf --no-pager -l
journalctl -u micv-tiktok -u micv-tiktok-pdf -n 80 --no-pager
sudo -u www-data storage/app/tiktok-venv/bin/python -c 'from TikTokLive import TikTokLiveClient; print("TikTokLive disponible")'
sudo -u www-data php artisan migrate:status
```

Un cambio de TikTok puede exigir una actualización del adaptador y la dependencia. No actualice a ciegas a otra versión: la versión de eventos y sus pruebas deben seguir coincidiendo.

La base contiene `tiktok_cuentas`, `tiktok_lives`, `tiktok_publicaciones`, `tiktok_comentarios` y `tiktok_exportaciones`. Los informes están en `storage/app/private/tiktok/exportaciones/`, fuera de `public/`. El directorio privado no debe publicarse ni enlazarse con `storage:link`. No se eliminan automáticamente comentarios antiguos: tenga en cuenta su crecimiento en copias de seguridad y almacenamiento.

## Importar comentarios de publicaciones

Registre la URL completa `https://www.tiktok.com/@usuario/video/ID` de la misma cuenta. El módulo no resuelve enlaces acortados ni descarga páginas arbitrarias. Después abra esa publicación y elija un JSON o CSV autorizado (máximo 5 MB y 5000 filas). Debe confirmar la autorización de la fuente en el formulario.

JSON de ejemplo; los identificadores deben ser cadenas, incluso si solo contienen dígitos:

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

CSV equivalente, UTF-8, separado por comas:

```csv
id,autor,nombre,texto,fecha
ejemplo-001,usuario_ejemplo,Participante de ejemplo,Comentario autorizado.,2026-11-22T10:30:00-06:00
```

Use fechas ISO 8601 con zona horaria. Si se omite la zona, se interpreta la configurada para el módulo. Una fila inválida rechaza toda la importación, sin dejar una importación parcial. El mismo identificador de comentario de la misma publicación no se duplica.

## Verificación realizada

- Suite PHP completa: 146 pruebas, 1589 aserciones; incluye permisos, separación de salas y días, duplicados, restricciones, importaciones, archivos privados, informes de varias partes y supervisor con procesos de prueba, conservación de esperas del proveedor y transición de sin LIVE a captura posterior sin reactivar.
- Adaptador Python: 16 pruebas de normalización, restricciones, diagnóstico sin datos sensibles, errores de conexión, límites, tiempos de espera y NDJSON Unicode. Ejecutar con `storage/app/tiktok-venv/bin/python tests/python/test_tiktok_capturar.py`.
- Navegador Chromium: transición simulada de error a espera de LIVE conservando la activación y la fecha de reintento; panel de administración en escritorio, móvil y tema oscuro; comentarios simulados actualizados sin ejecutar HTML; confirmaciones normales y descarga PDF de la cola; acceso denegado a una cuenta sin rol de administrador.
- El informe se renderizó con su plantilla real, A4 y datos ficticios. En el entorno de pruebas se cargó el HTML en memoria porque el Chromium administrado bloquea `file://`; producción utiliza el motor `CvPdf` existente.

**No se ha probado una conexión a un LIVE real ni se ha instalado este paquete en su VPS.** La conexión efectiva debe comprobarse después de instalarlo, con un LIVE público disponible. No se incluyen cuentas ficticias ni rutas de pruebas en este paquete.

## Fuentes técnicas

- TikTokLive, documentación del proyecto: https://github.com/isaackogan/TikTokLive
- Versión y requisitos fijados: https://pypi.org/project/TikTokLive/7.0.1/
- Reproductor oficial de publicaciones: https://developers.tiktok.com/doc/embed-player
- API oficial de perfil y videos, sujeta a permisos: https://developers.tiktok.com/doc/display-api-overview
