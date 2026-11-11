# Motor local 2.0 — historial de implementación

La versión de prueba 2.0.0-beta.6 incluye el código del escritorio y Laravel. Use `LEAME-VERSION-2.0-BETA.md` para compilar y probar. Las secciones siguientes describen las etapas del desarrollo. Queda pendiente validar el instalador y los escenarios de Windows.

## Decisión acordada

Un instalador Windows incluirá las dependencias. La presentación debe ser de escritorio y conservar todas las funciones y validaciones de Laravel. Los administradores y clientes no requieren licencia comercial; empresas y empleados sí. El correo y las comunicaciones entre equipos necesitan conexión.

## Implementado y probado

- Supervisor aislado de Laravel con autorización del perfil, base SQLite por cuenta y rechazo de configuración de servidor.
- Acceso del motor limitado a loopback con secreto por proceso, comprobación de origen, vigencia y protección de archivos.
- Ventana Qt con navegación derivada del menú autorizado de Laravel, búsqueda de módulos, impresión y guardado de descargas. La vista de contenido aún utiliza el renderizador HTML; no se han convertido todos los formularios a widgets Qt.
- Dependencias del renderizador separadas en requirements-local.txt.
- Siete pruebas nuevas del supervisor (34 pruebas de escritorio con las existentes), once comprobaciones HTTP del router y prueba de navegación nativa.

## Pendiente antes de publicar

1. Aprovisionar bases locales por usuario/empresa desde datos autorizados, sin copiar claves del servidor ni contraseñas de otras cuentas. El supervisor deliberadamente rechaza espacios que no estén preparados.
2. Implementar sincronización ampliada con archivos, relaciones, conflictos y operaciones idempotentes; la API original acepta seis tipos y el avance agrega dos operaciones de expediente completo; el resto sigue pendiente.
3. Integrar acceso local con la sesión y los permisos de Laravel.
4. Verificar cada módulo, incluidos CV y sus 31 diseños, cartas, documentos, fotos, tarjetas, correos, chat, clientes, POS completo, contabilidad y administración. La presencia de sus controladores en este proyecto no demuestra que estén integrados al escritorio.
5. Revisar concurrencia del motor PHP en Windows, recursos sin red y generación de PDF.
6. Empaquetar PHP, Node, Chromium, dependencias y recursos en el instalador; probar instalación y actualización en Windows real.
7. Comprobar migración y respaldo de los perfiles 1.3 sin pérdida de operaciones pendientes.

Se ha ampliado el protocolo del servidor durante este desarrollo. No se ha generado un nuevo EXE completo.

## Avance: sincronización del expediente completo

- Operaciones nuevas client.full.create y client.full.update, usando ClienteGuardar y sus validaciones originales.
- Incluye estudios, experiencia, idiomas, habilidades, referencias y soporte de foto pequeña dentro del límite del lote.
- Comprobación de versión de toda la ficha y sus secciones; los cambios concurrentes producen conflicto, no reemplazo automático.
- Reintentos idempotentes, aislamiento entre empresas y respeto de los grupos de edición del portal del cliente.
- El administrador puede editar una ficha sin seleccionar empresa.
- Descarga firmada por páginas mediante /api/desktop/v1/dossiers; vinculada a servidor, cuenta, empresa y equipo.
- Caché local cifrada; una descarga interrumpida o inválida conserva la copia anterior.
- Compatibilidad con servidores anteriores: la descarga nueva solo se usa cuando el servidor anuncia la capacidad.
- Bloqueo transaccional del cliente durante actualizaciones web para coordinar cambios en sus relaciones.

Validación de este avance: 129 pruebas Laravel (1417 aserciones) y 39 pruebas Python.
Estas pruebas se ejecutaron en Linux; no verifican un instalador Windows.

La interfaz publicada 1.3 todavía no incorpora un editor completo del CV ni impresión local de todos los diseños. Este avance implementa el protocolo y la caché; faltan el aprovisionamiento de Laravel local, su conexión con las pantallas y la sincronización del resto de operaciones. No es una entrega final ni un parche que deba instalarse por separado en producción.

## Avance: sesión completa conectada en ventana de escritorio

- Sesión Laravel con token del equipo en cabecera y POST, sin credenciales en URL ni JavaScript.
- Clientes y administradores sin licencia comercial. Empresas sujetas a permisos originales y módulos licenciados en cada solicitud.
- Todos los módulos de Negocios::MODULOS pueden asignarse a una licencia; las existentes no se amplían automáticamente.
- Menú nativo derivado de #jzMenu; filtrado recursivo de submódulos. Conserva el panel de impresión del editor y paneles internos de chat y CV.
- Pestañas nativas para ventanas adicionales, descargas, impresión y búsqueda de módulos. Cookies únicamente dentro del perfil privado de la sesión.
- Interceptor restringido al origen autorizado, sin enviar tokens a hosts externos; requiere HTTPS.
- Renovación del acceso cada minuto; el servidor comprueba revocación y permisos en las rutas web de escritorio.
- Entrada conectada al arrancar cuando el servidor anuncia desktop_session. Trabajo local mantiene funciones anteriores; NO implica paridad sin conexión.
- Compilador incluye requirements-local.txt para Qt. Aún no incluye el motor PHP completo ni una base local aprovisionada.
- module-coverage.json inventaría las 226 rutas por controlador. Las comprobaciones finales están pendientes: el inventario evita omisiones, no certifica paridad.

Las páginas conectadas usan los controladores originales; no se ha probado cada recorrido visual ni cada impresora. El funcionamiento sin conexión completo, la sincronización de todos los módulos y el instalador final siguen pendientes. No distribuir este avance como la versión completa solicitada.

Validación de la sesión conectada: 141 pruebas Laravel (1661 aserciones), 44 pruebas Python, navegación Qt con servidor de prueba y arranque del renderizador. No equivalen a validar todas las funciones en Windows.

## Preparación protegida de datos y archivos (10 de noviembre)

- GET /api/desktop/v1/workspace prepara 31 tablas de datos autorizados. Firma ligada al equipo, cuenta, empresa, rol y servidor; límite de 20.000 filas por tabla, 100.000 totales y 32 MiB de JSON.
- Filtrado por permisos y licencia, CV propio del cliente y empresa. Cédulas bloqueadas solo para administrador. Las conversaciones se limitan a participantes autorizados; se excluyen mensajes ocultos y el contenido eliminado.
- No exporta contraseñas de acceso, sesiones, tokens, passkeys, claves de firma, APP_KEY ni credenciales SMTP/reCAPTCHA. Las claves de correos registradas y el texto de chat autorizado se preparan para cifrarlos con la clave del equipo.
- GET /api/desktop/v1/workspace/file vuelve a autorizar cada registro y su archivo. No acepta rutas arbitrarias. Rechaza enlaces simbólicos, rutas fuera del almacenamiento, archivos cambiados y archivos superiores a 50 MiB.
- Manifiesto firmado de fotos, logos y adjuntos con SHA-256; archivos faltantes declarados explícitamente. Descarga limitada a 256 MiB por preparación.
- Sync.prepare_workspace renueva el acceso y descarga la preparación. Filas y archivos quedan cifrados. Solo se sustituye la preparación previa después de completar la descarga; nunca modifica database.sqlite activo ni la cola de pendientes.
- DesktopAuth corregido para admitir respuestas de archivos además de JSON.

Pendiente: convertir esta preparación en una base Laravel utilizable, resolver referencias que no deban exportarse como cuentas completas, restaurar archivos al motor privado, vincular la sesión local y registrar/sincronizar todas las modificaciones. La administración global, avisos y auditoría requieren aún ampliar y validar la cobertura. Estas piezas no demuestran funcionamiento completo sin conexión.

Pruebas: 152 Laravel (1724 aserciones) y 57 pruebas Python. No se ejecutó un instalador Windows. No distribuir como versión final.

## Importación de la copia a SQLite

La preparación renovada puede materializarse con Sync.provision_workspace(bundle). El proceso usa PHP incluido en el paquete, ejecuta las migraciones en un archivo nuevo, verifica las referencias SQLite y restaura los archivos con verificación de hash. Publica una generación separada una vez que termina. El comando requiere SQLite nuevo y vacío. Los campos protegidos se cifran con una APP_KEY local, guardada en la bóveda del equipo. El archivo SQLite contiene los demás datos operativos y depende de los permisos del perfil del sistema operativo.

Las referencias opcionales a filas no exportadas quedan nulas. Las referencias obligatorias a usuarios no exportados se conservan con identidades locales inactivas y sin credenciales del servidor. Esto conserva la integridad de la base, pero esos contactos aún necesitan su proyección autorizada para que el chat funcione con las reglas originales. La copia cifrada conserva las relaciones originales para el trabajo pendiente.

Validación con PHP/Laravel real en Linux: migración, restauración de adjunto, cifrado de mensaje, control de referencias y rechazo de una copia incompatible sin modificar la base previa. Pasaron 152 pruebas Laravel y 58 pruebas Python. Faltan sesión local, vinculación con la ventana, sincronización integral y validación del instalador en Windows.

Sesión local vinculada por usuario, rol, clave del proceso, origen loopback y vigencia. Lectura autorizada por permisos exportados. Edición local y sincronización integral pendientes. Comprobadas dos pruebas de identidad HTTP y siete del motor local. Falta integrar el arranque en la ventana y verificar recorridos completos.

Arranque desde Herramientas: preparar datos locales y abrir espacio local. La preparación corre en segundo plano y publica la referencia de la nueva generación al terminar. Apertura ligada al UUID del espacio y la cuenta. Se probaron importación y acceso HTTP con PHP real, incluida denegación sin secreto. Los permisos completos viajan firmados en la autorización y el motor rechaza una copia con permisos retirados. Las compilaciones aún requieren incluir runtime-bundle con PHP y Laravel. Edición integral, sincronización y validación Windows siguen pendientes.

Empaquetado del motor: se integró -RuntimePath en el compilador y un ensamblador que copia motores y dependencias, descarta configuración y datos del servidor, rechaza enlaces simbólicos y publica el paquete completo al terminar. Tres pruebas específicas pasaron. Aún se deben suministrar motores Windows y dependencias compatibles. No se ha validado el instalador en Windows.

Preparación automática del motor: la compilación sin RuntimePath descarga PHP 8.4 NTS x64 y Node 24 LTS mediante catálogos oficiales y verificación SHA-256. Verifica Composer con SHA-384, prepara dependencias en copia temporal con los archivos de bloqueo, instala Chromium y el modelo U2Net e incluye el redistribuible firmado de Microsoft. La primera preparación necesita Internet y puede elevar permisos para instalar Visual C++ si falta. Se verificaron tres pruebas del ensamblador y siete del supervisor en Linux. PowerShell, descargas y generación del instalador aún requieren ejecución real en Windows.

Revisión integral: pasaron 154 pruebas Laravel y 61 Python. Se corrigió después el filtrado central de permisos para controles y menús locales, con tres pruebas específicas aprobadas. La edición local de todos los módulos sigue pendiente de protocolos de sincronización. El instalador requiere validación Windows.

Validación de PDF con Chromium real en Linux: los 31 diseños generaron archivos PDF válidos con datos de ejemplo, incluidos estudios y experiencia. Se extrajo el texto para confirmar el nombre en todos y se verificó tamaño carta vertical (612 x 792 pt). Revisión visual del ATS realizada. Esta comprobación cubre generación con muestra breve, no documentos extensos ni impresoras Windows. No cambió el soporte pendiente de edición y sincronización local.

Edición local de fichas y CV existentes: se habilitaron clientes.update y mi-cv.actualizar con validación original y registro cifrado en la misma transacción. La revisión parte de las filas originales descargadas, incluso si SQLite normaliza referencias. La cola se incorpora por páginas sin duplicar UUID y se envía por el protocolo FullClientSync existente. Se conserva un cambio por ficha hasta sincronizar y descargar una copia nueva. Fotos, creación local integral y demás módulos siguen pendientes. PHP real verificó formulario autenticado, modificación de estudios, guardado SQLite y lectura idempotente de la cola. Pasaron 155 pruebas PHP y 61 Python, y se repitió la prueba HTTP tras paginar la cola. Windows sigue sin validar.

CV y operaciones de caja verificadas de extremo a extremo. Los recibos conservan la revisión del CV que el servidor aplicó. Esto permite volver a editar tras confirmar el cambio sin descargar otra copia. Se admiten fotos de hasta 600 KB y se elimina la anterior al confirmar la transacción. Se conservaron lotes acotados por bytes y número de operaciones.

La cola local conserva registro y anulación contable, apertura, movimientos y cierre de caja, venta y anulación de venta. El servidor verifica el negocio, la licencia y los permisos, y devuelve conflicto cuando cambian los datos necesarios. Las referencias entre operaciones locales se resuelven por recibos. Las operaciones aceptadas no se duplican. Actualizar la copia requiere resolver los cambios pendientes.

Se requiere aplicar la migración desktop_receipt_revision en el servidor actualizado. Pruebas: 155 Laravel y 62 Python. Las pruebas con dos instancias Laravel reales verificaron dos cambios sucesivos de CV, foto, reintento idempotente y siete operaciones de contabilidad/POS. Quedan edición de inventario y compras, otros módulos y comprobación del instalador en Windows. El ZIP continúa como avance de desarrollo.

Inventario y compras: se habilitaron los ajustes de existencias y el registro y anulación de compras con cola cifrada. Web y API comparten el servicio de compras. La anulación resuelve compras creadas en el mismo equipo por su recibo. Fijar el stock exige que la existencia previa coincida con el servidor. Prueba HTTP real de diez operaciones y de conflicto de stock sin sobrescribir el servidor. Pasaron 155 pruebas Laravel y 62 Python. Altas, edición y bajas de catálogo, otros módulos y Windows siguen pendientes.

El botón Editor de documentos abre una ventana interna con los recursos locales. La vista previa de impresión se abre en una pestaña de la aplicación cuando el PDF termina de generarse. Se probó la apertura desde el menú nativo, carga de PNG, rotación, exportación PDF y vista previa en Qt. Pasaron 62 pruebas Python y las pruebas de apertura de ventana conectada y de editor local en Linux. Falta verificar el instalador y la impresión física en Windows.

Catálogo POS: alta, edición y baja de productos, categorías y proveedores sin conexión. Se incluyen fotos de producto de hasta 600 KB y el registro de servicios sugeridos. Los recibos resuelven referencias locales en ventas, compras y ajustes. La prueba de 19 operaciones comprobó colisiones de ID entre el equipo y servidor, foto de producto, vínculos correctos, bajas con historial y conflicto de existencias. Pasaron 155 pruebas Laravel y 62 Python antes del último ajuste de proyección. Después se repitieron 23 pruebas Laravel de POS y espacios y las dos pruebas de integración. El instalador Windows y otros módulos siguen pendientes.

## Registro de clientes y ventas sin conexión

El formulario de clientes conserva las validaciones de Laravel y registra la ficha, el CV y su foto en el espacio local del negocio. El registro rechaza documentos duplicados. Una venta puede asociarse a esa ficha antes de sincronizar. La API vincula la venta al cliente mediante el recibo de creación, aunque SQLite y el servidor asignen identificadores distintos. La revisión devuelta por el servidor permite editar la ficha local nuevamente tras sincronizar.

Se comprobó el flujo con bases separadas, una colisión de identificadores, un intento de duplicado y una edición posterior. Pasaron 155 pruebas Laravel y 62 de escritorio en Linux.

## Cartas, tarjetas, fotos y consulta de mensajes en el escritorio

- Habilitadas las vistas y descargas locales de cartas, tarjetas y hojas de fotos. Exportar una tarjeta localmente conserva el diseño guardado. Para cambiarlo se usa Guardar tarjeta.
- El guardado local de la tarjeta y la foto recortada del CV se sincronizan con la ficha de destino. Se verifica la versión antes de aplicar cambios. La foto o logo que se adjunte a esta sincronización admite hasta 600 KB.
- Se puede consultar el historial de mensajes de la copia firmada, con lectura local. El envío sigue disponible en sesión conectada.
- En Linux se verificó PDF para los 31 diseños de CV, Word ATS, carta PDF, tarjeta PNG/PDF/hoja, hoja fotográfica y exportaciones PDF/Excel de clientes, POS y contabilidad. Se probó la sincronización de fotos y dos ediciones sucesivas de tarjeta contra una base separada.
- Sigue pendiente comprobar la instalación y actualización en Windows, el modelo de fondo con el paquete final y la cobertura de las operaciones restantes de administración y autoservicio.

## Autoservicio y configuración del punto de venta

- En la copia del negocio se pueden revisar, atender y descartar los turnos recibidos antes de perder conexión. Atender guarda la ficha y el estado del turno en la misma transacción. La sincronización enlaza el turno con el ID asignado por el servidor.
- Si el turno ya fue atendido o descartado en la web, la operación local se conserva como conflicto. El QR público y la recepción de formularios se administran desde la sesión conectada.
- Los cambios de configuración del POS se guardan sin conexión y se sincronizan con control de versión. Se conservan las reglas de moneda y consecutivo cuando hay movimientos.
- Pruebas con bases separadas en Linux. El instalador y la actualización siguen pendientes de prueba en Windows.

## Marca del negocio y permisos de edición del cliente

- Los cambios de logo, colores y datos de contacto del negocio se pueden guardar sin conexión. La cola incluye el logo JPG, PNG o WEBP hasta 600 KB y protege contra cambios concurrentes.
- Un encargado autorizado puede actualizar los grupos de edición de clientes con una cuenta existente. Para recibir los permisos actuales de esas cuentas, se debe renovar la copia local del negocio. La invitación, la contraseña y la verificación de correo se gestionan con conexión.
- Se comprobó la carga y retirada del logo, el rechazo de conflictos y la sincronización de permisos con bases separadas. El servidor comprueba de nuevo la autorización del encargado y la cuenta vinculada.

## Equipo de trabajo y comprobación del quitafondos

- En la copia del negocio, un encargado autorizado puede cambiar el nombre, estado y permisos delegables de sus cajeros existentes. Se conservan los permisos que están fuera de su alcance. Laravel verifica el encargado, la versión y los permisos al recibir la operación. El cambio de estado en el servidor surte efecto al sincronizar.
- Crear cuentas y cambiar usuario de acceso, correo, contraseña o encargado requiere la sesión conectada.
- La compilación estándar de Windows ejecuta una inferencia con el modelo U²-Net descargado y valida que la salida sea un PNG con canal alfa y las dimensiones esperadas. Se probaron los comprobadores de imagen en Linux. Queda pendiente ejecutar la compilación y el instalador en Windows.

## Continuidad cuando el servidor no responde

- Tras preparar los datos y desbloquear la cuenta con el PIN, el programa inicia en el espacio local. El trabajo guardado en SQLite y las operaciones pendientes se mantienen durante un apagón del servidor. La sincronización vuelve a intentar la conexión en segundo plano.
- Si el usuario está en la sesión conectada y la comprobación detecta un error de transporte o de servidor, abre la copia preparada. Una denegación de acceso se trata según la autorización recibida.
- El motor local conserva el último plazo de acceso firmado. Puede aplicar una renovación verificada sin reiniciar PHP y rechaza alteraciones al archivo de vigencia. La primera preparación y las funciones que necesitan llegar a otro equipo requieren conexión.
- En Linux se apagó el servidor simulado, se comprobó que el espacio seguía accesible, se reinició el motor local, se guardó un cambio de CV y se confirmó al recuperar el servidor. Queda pendiente comprobar la ventana y el instalador en Windows.

## Respaldo durante una caída del servidor

- El respaldo de un espacio preparado incluye ambas bases SQLite, las operaciones pendientes y los archivos privados de Laravel. La base y los archivos se cifran y autentican antes de publicar el ZIP.
- El espacio local debe estar cerrado durante la copia para mantener la base y sus archivos en el mismo estado. La recuperación verifica el ZIP, el PIN y la cuenta firmada antes de publicar el perfil. En Windows la clave del respaldo sigue vinculada al usuario de Windows.
- La recuperación no depende del servidor. El acceso local posterior conserva el plazo de vigencia de la última autorización.
- La prueba de integración en Linux recuperó un CV con foto y archivos privados mientras el servidor seguía apagado. La edición, que aún estaba solo en la base del espacio local, se sincronizó después de la recuperación. Se rechazaron un PIN incorrecto y una copia alterada. La restauración con DPAPI queda pendiente de prueba en Windows.

## Beta 2 · Ícono y diagnóstico

El ejecutable, el instalador y el acceso directo se preparan con el ícono JZ en formato ICO. La ventana de diagnóstico muestra incidencias recientes, conteos de errores de Laravel y PHP locales, y referencias de conflictos. El resumen copiable excluye los mensajes de los registros y los datos de las operaciones. Se probó el diálogo Qt y la lectura del ícono en Linux. La compilación y el resultado visual en Windows siguen pendientes de validación.

## Beta 4 · Cartas editables y chat

El editor Qt permite editar todo el contenido de una carta, guardarla como borrador cifrado en la cuenta y exportarla a PDF o imprimirla. Las plantillas usan las validaciones y los permisos de Laravel. Se probó con el motor local la carga de una carta, la edición y el PDF. Se comprobó en Chromium la distribución del chat, el filtro de no leídos y la vista móvil. La compilación del instalador sigue pendiente de validar en Windows.

## Beta 5 · Correcciones

Se reprodujo en Chromium el fallo de búsqueda que reemplazaba el historial local con una consulta de contactos. Se verificó la búsqueda corregida y el perfil de solo lectura sin conexión. Se probaron en Qt la recuperación del borrador ante una caída del servidor, la conservación del texto ante un error de escritura y la protección de PDF existentes al completar la extensión. El instalador de Windows sigue pendiente de ejecutar.
