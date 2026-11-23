# Mi CV · Centro de captura e informes técnicos

Actualización para el proyecto Laravel instalado. El ZIP mantiene las carpetas del proyecto en su raíz. No incluye `.env`, credenciales, base de datos, `vendor`, `node_modules` ni rutas de prueba.

## Qué cambia

- Interfaz de administración rediseñada con los componentes Blade y los estilos de Mi CV: perfil compacto, indicadores, transmisión y comentarios lado a lado, historial, informes y herramientas de administración. Adaptada a móvil y modo oscuro.
- Fotos públicas conservadas, nombre mostrado, usuario, ID público cuando TikTok lo entrega, ID del mensaje, biografía e idioma disponibles, fechas de evento y recepción, fuente y hash del registro normalizado.
- Exportación **por LIVE completo** desde el historial o el botón «PDF del LIVE». No depende del filtro de fechas del panel y contempla mensajes del primer lote con fechas anteriores al inicio de recepción.
- Exportación de una selección con referencia/expediente y observaciones del solicitante.
- PDF A4 profesional generado con **Chromium/Browsershot/Puppeteer**, portada, resumen, participantes, fotos disponibles, cronología, bitácora de conexiones, hashes y numeración de páginas.
- Paquete de evidencia independiente del PDF: registros NDJSON, fotos originales disponibles, representaciones visuales, PDF, manifiesto SHA-256 y verificador Python sin red.
- Reproductor con estados claros, reconexión limitada, pausa, controles nativos de sonido y pantalla completa, HLS AES-128 con clave pública y hasta dos redirecciones de CDN validadas. No gestiona DRM ni licencias externas.
- La actualización del historial no reemplaza los formularios mientras está abierto un aviso de confirmación, para conservar la acción y el LIVE elegidos.

## Instalar o actualizar en el VPS

Haga una copia del código y de la base de datos. Suba `Mi-CV-TikTok-Forense.zip` a `/var/www/micv/` y ejecute:

```bash
cd /var/www/micv
unzip -o Mi-CV-TikTok-Forense.zip
sudo bash bin/instalar-tiktok.sh
```

Después recargue con Ctrl+F5 y entre como administrador. El instalador detiene los trabajadores del módulo durante la actualización, limpia las cachés, aplica las siete migraciones de TikTok que falten y vuelve a arrancar los servicios. Conserva las cuentas y las pausas manuales; recupera el monitoreo que se desactivó por el antiguo estado automático «restringido». Las nuevas quedan pausadas hasta que las active.

Servicios que arrancan con el VPS:

- `micv-tiktok`: conexión y captura, independiente del navegador y de la sesión web.
- `micv-tiktok-evidencias`: descarga y conservación de fotos públicas en segundo plano.
- `micv-tiktok-pdf`: preparación del snapshot, archivos y PDF.

No modifica los servicios de correo o de video promocional. Reiniciar al actualizar produce una interrupción breve; no hay recuperación garantizada de eventos perdidos.

Requisitos: Laravel base instalado con `.env` y `vendor`, PHP CLI con cURL, base de datos y tabla `jobs`, Python 3.10+, Ubuntu/systemd, salida HTTPS hacia TikTok y su servicio de firma. El PDF y las miniaturas grandes utilizan el motor existente de Mi CV: Node, Puppeteer y Chromium. Si hay GD, se aprovecha para miniaturas; si falta, se usa Chromium. Si una representación no puede generarse, el original disponible permanece en el paquete.

Para otro PHP o usuario web:

```bash
sudo MICV_PHP_BIN=/usr/bin/php MICV_WEB_USUARIO=www-data bash bin/instalar-tiktok.sh
```

Opcionales en `.env`:

```dotenv
TIKTOK_SIMULTANEOS=3
TIKTOK_INTERVALO=30
TIKTOK_ZONA=America/Costa_Rica
```

Ejemplo de zona para Nicaragua: `America/Managua`. La región indicada por TikTok es un dato distinto de la zona de visualización. Tras cambiar estas opciones:

```bash
sudo -u www-data php artisan config:clear
sudo systemctl restart micv-tiktok micv-tiktok-evidencias micv-tiktok-pdf
```

## Capturar y ver el LIVE

Registre el @usuario exacto que desea observar y active su monitoreo, sin elegir anfitrión o invitado. Puede cerrar sesión: la captura continúa en el VPS. Una cuenta activada sin LIVE sigue comprobándose, por defecto cada 30 segundos, y comienza al detectar una sala pública con un cupo disponible. Hay tres conexiones simultáneas por defecto; las demás cuentas esperan. Ante límites del proveedor o rechazos se respeta una espera mayor, de al menos 300 segundos y hasta 24 horas cuando corresponde.

Los estados distinguen servicio activo, monitoreo programado y conexión a una sala. «Activado» no significa que ya haya un LIVE conectado. Si el SDK no localiza una sala, se contrasta con la API del usuario y su perfil público; si obtiene una asociación, se vuelve a comprobar su estado antes de conectar. Si TikTok no entrega una asociación suficiente, se muestra «No se pudo determinar la sala», sin afirmar que no participe como invitado. No se omiten controles de acceso.

Un participante con perfil privado puede escribir un comentario público: ese comentario se guarda si TikTok lo entrega, sin consultar sus publicaciones privadas. Se procesa también el primer lote entregado al conectar y se deduplican los mensajes por cuenta, sala e ID disponible. Si no hay ID de plataforma, el adaptador genera uno y lo identifica como tal.

Pulse **Reproducir LIVE** cuando haya señal HLS compatible. **Detener vista** no pausa la captura. Si la señal no se entrega, caduca o es rechazada, puede abrir la transmisión en TikTok. El módulo archiva comentarios y metadatos; **no graba el video**.

## Fotos y datos de participantes

Solo se recogen campos públicos entregados en el evento, no todos los datos posibles de una cuenta. No se infieren identidad real, domicilio, nacionalidad, edad o titularidad a partir de nombres o fotos. Un campo ausente queda marcado como no entregado.

Las fotos se descargan mediante una cola separada para no bloquear los comentarios. Cada URL observada se deduplica dentro del LIVE. Se conservan el original, su tamaño, tipo detectado, SHA-256 y fecha de descarga. La URL original se almacena cifrada y en los informes se identifica por su hash, sin publicar sus parámetros firmados. Las imágenes admitidas son JPEG, PNG y WebP, hasta 1 MB y 16 millones de píxeles.

La observación de una URL y la descarga del archivo son momentos distintos. Una foto puede fallar, estar pendiente o no haber sido entregada. No se reconstruye ni se inventa una foto faltante. Las imágenes del PDF pueden ser representaciones reducidas; el paquete conserva los originales disponibles sin modificarlos.

Los comentarios anteriores a esta actualización no adquieren retroactivamente IDs, fotos o un hash de captura original. Se incluyen como registros anteriores, con sus datos disponibles y sin atribuirles un sello que nunca tuvieron.

## Informe por LIVE

1. Abra la cuenta monitoreada.
2. En **Historial de lives**, busque la sala y pulse **Exportar PDF**. Se incluirán todos los comentarios guardados de ese LIVE hasta el corte de la solicitud.
3. Para una selección concreta, use los filtros y **Informes y evidencia → Informe de la selección actual**. Puede añadir referencia y observaciones.
4. La cola fija un snapshot y genera los documentos en segundo plano. En **Informes y evidencia** aparecerán los botones **Descargar PDF** y **Paquete de evidencia**.
5. Un informe con más de 200 comentarios se divide en partes para limitar el consumo de Chromium; los PDF se descargan juntos en un ZIP. El paquete de evidencia reúne todas las partes y los archivos.

Si está consultando un LIVE del historial, el botón superior exporta ese LIVE seleccionado, aunque exista otra sala en directo. Sin un LIVE seleccionado, se refiere al actual.

El PDF incluye contexto, hora local AM/PM y UTC, número de comentarios e identificadores observados, participantes, datos recibidos, cronología y limitaciones. Una agrupación por ID o usuario no equivale a personas identificadas. Los mensajes importados conservan una etiqueta de origen distinta de los recibidos del LIVE.

## Integridad y verificación

En las capturas nuevas se calcula un SHA-256 de la representación JSON normalizada y ordenada del registro: cuenta, LIVE local, clave, autor, ID público, nombre, texto, origen, fechas y metadatos públicos seleccionados. Se registran las normalizaciones o recortes y, cuando está disponible, el hash del texto original entregado por el adaptador. No se conserva el paquete WebSocket binario completo ni una firma de TikTok.

La exportación limita los comentarios al identificador local fijado al solicitarla y a los filtros con su zona horaria. Las fotos disponibles y sus estados se fijan durante la preparación. El snapshot NDJSON queda separado de los datos que lleguen después. Los datos originales de captura y el contexto de sala/publicación se distinguen en sus campos.

El paquete contiene:

```text
informe-parte-001.pdf       # y partes adicionales si hacen falta
datos.ndjson               # registros y estados de adjuntos del snapshot
fotos/                     # originales disponibles
vistas/                    # representaciones utilizadas en el documento
manifiesto.json            # identificador, selección, preparación y hashes
verificar.py               # verificador sin dependencias externas
```

Extraiga todo el paquete, abra una terminal en esa carpeta y ejecute:

```bash
python3 verificar.py
```

En Windows con Python instalado puede usar `python verificar.py`. El verificador comprueba existencia, tamaño y SHA-256 de los archivos, cantidad de registros y hashes de captura disponibles. Si modifica un archivo, debe fallar. Los registros sin hash original se cuentan expresamente y no se presentan como verificados al capturarse.

Los hashes detectan diferencias respecto del manifiesto conservado; **no certifican por sí solos autenticidad de TikTok, identidad, exhaustividad o una fecha mediante una autoridad externa**. La bitácora documenta conexiones, últimos datos y cierres observados. Un cierre registrado al reiniciar no determina el instante exacto de una interrupción anterior. El informe técnico no sustituye una pericia acreditada o una cadena de custodia externa.

## Archivo y permisos

Tablas principales: cuentas, lives, comentarios, publicaciones, exportaciones, adjuntos y sesiones. Los archivos quedan bajo `storage/app/private/tiktok/`:

- `avatares/`: originales y representaciones privadas de participantes.
- `exportaciones/`: snapshot, PDF, manifiesto y paquetes.
- `reproduccion/`: metadatos cifrados temporales de la señal pública.

No publique ni enlace esos directorios con `storage:link`. Las rutas de imagen y video requieren administración. Cada informe y su paquete solo los descarga el administrador que los solicitó. Eliminar un monitoreo borra sus datos y archivos locales; no modifica la cuenta original de TikTok. Durante una pausa pueden terminar de guardarse mensajes que ya estaban en tránsito.

La reproducción valida HTTPS, dominios de CDN, direcciones DNS públicas, destino de cada redirección, tokens temporales y límites de tamaño. No acepta una URL libre del navegador ni reenvía una sesión de TikTok. HLS AES-128 con una clave pública entregada por el servidor es compatible; no se solicitan licencias DRM. La lectura de sala y señal puede renovarse aproximadamente cada cinco minutos sin desactivar los comentarios ante un fallo de video.

## Diagnóstico

```bash
systemctl status micv-tiktok micv-tiktok-evidencias micv-tiktok-pdf --no-pager -l
journalctl -u micv-tiktok -u micv-tiktok-evidencias -u micv-tiktok-pdf -n 80 --no-pager
sudo -u www-data php artisan migrate:status
sudo -u www-data storage/app/tiktok-venv/bin/python -c 'from TikTokLive import TikTokLiveClient; print("TikTokLive disponible")'
```

- «Capturador sin señal»: revise el servicio de captura.
- Fotos pendientes: revise `micv-tiktok-evidencias`. Un fallo de foto no elimina el comentario.
- Informe en cola más de dos minutos: revise `micv-tiktok-pdf` y el motor de Chromium.
- `UserOfflineError`: el SDK no detectó un LIVE; no significa que un comentarista tuviera perfil privado.
- Señal no disponible: TikTok puede no proporcionar HLS compatible o rechazar su lectura desde el VPS. No se registra falsamente una transmisión como capturada.

TikTokLive es no oficial y puede dejar de funcionar por cambios, bloqueos o límites de terceros. No se garantiza capturar todos los mensajes. Actualizar una dependencia puede requerir adaptar el código; no cambie versiones a ciegas.

## Verificación de esta entrega

- PHP: **183 pruebas**. Incluye permisos, sesiones, fotos y archivos privados, hashes, IDs grandes, exportación por LIVE, datos anteriores, publicación importada, varias partes, eliminación y verificación que detecta alteraciones. Las pruebas de informes renderizan también su plantilla HTML.
- Python: **37 pruebas**, con ID original/generado, hash del texto, recortes, tipos reales del protocolo TikTokLive 7.0.1, perfiles privados que comentan públicamente, conexión, restricciones, reintentos y metadatos.
- Chromium: reproducción efectiva de un HLS local H.264/AAC y de otro con AES-128 y redirección validada; detener la vista conserva el monitoreo. Interfaz, fotos de prueba, actualización sin ejecutar HTML, confirmación abierta durante eventos, exportación en cola y descarga de PDF y paquete. Escritorio, móvil y modo oscuro revisados.
- PDF de muestra A4 generado por Chromium con datos ficticios, fotos de prueba, seis páginas y numeración. El paquete se extrajo y verificó con el script incluido.

El Chromium administrado del entorno bloquea `file://`: la comprobación local carga **el mismo HTML y pie** en memoria. Producción usa el motor de Mi CV configurado con Browsershot. Los casos positivos de captura y reproducción se probaron con eventos simulados y señales locales; **no se ha confirmado un LIVE real desde su VPS ni se ha instalado esta actualización allí**.

## Dependencias

- TikTokLive 7.0.1: https://pypi.org/project/TikTokLive/7.0.1/ · https://github.com/isaackogan/TikTokLive · licencia publicada: Modified AGPL-3.0.
- HLS.js 1.7.4: https://github.com/video-dev/hls.js · incluido con su licencia Apache-2.0 en `public/vendor/hls-LICENSE.txt`.
- El motor Chromium de PDF utiliza las dependencias existentes del proyecto.

## Comentarios por perfil y detección automática

La actualización presenta los comentarios debajo de cada perfil, incluidos los escritos del creador cuando se identifican. Se registra únicamente el @usuario; el capturador busca automáticamente las asociaciones de sala que TikTok publica sin elegir anfitrión o invitado. Si esa asociación no está disponible, el estado indica «No se pudo determinar la sala» y reintenta; no garantiza localizar invitados ni obtener un chat de otra sala. Consulte **LEAME-CORREO-Y-COMENTARIOS.md** para el instalador conjunto y los detalles de las fuentes públicas, el HLS secundario y las copias históricas.

## Acceso al LIVE y privacidad del perfil

Un aviso de acceso de TikTok ya no deja la cuenta desactivada permanentemente ni se presenta como perfil privado. El estado es «Esperando acceso al LIVE», con monitoreo activado y comprobaciones separadas por al menos cinco minutos, o más si el proveedor lo indica. Mientras no haya acceso verificable no se inicia una captura. Las pausas manuales siguen bajo control del administrador.

## Solo comentarios desde una sala indicada

La ficha permite indicar el @usuario de una sala pública conocida y seguir su chat desde el perfil monitoreado. Este modo no solicita video ni metadatos adicionales de sala, conserva la comprobación de LIVE y requiere que el servicio público de chat acepte la conexión. Cada mensaje y el informe conservan la cuenta de origen; la presencia del perfil en esa sala no se da por confirmada. Consulte LEAME-CORREO-Y-COMENTARIOS.md para configurar la fuente, volver a la detección automática y conocer sus límites.

## Varias salas debajo del mismo perfil

En **Solo comentarios de salas públicas**, escriba hasta 20 usuarios, uno por línea o separados por comas, espacios o punto y coma. El sistema normaliza los @ y elimina duplicados. Cada fuente tiene su estado, sala y próximo reintento. Una fuente sin LIVE o con un fallo no cierra las demás. Se conserva la fuente individual que ya estaba guardada antes de esta actualización.

El límite global sigue siendo TIKTOK_SIMULTANEOS (3 por defecto, máximo 10). Las fuentes adicionales esperan un cupo; una conexión activa no se corta para rotarlas. Los mensajes se deduplican por perfil, sala e ID del evento, incluso si dos fuentes conducen a la misma sala. Retirar una fuente no borra comentarios ni informes y solo cierra sus conexiones; una sala compartida permanece activa si otra fuente sigue conectada. La pausa del perfil detiene todas sus fuentes.
