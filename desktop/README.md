# Mi CV Escritorio 1.2 — Acceso por tipo de cuenta

Aplicación nativa de escritorio en Python/PySide6, conectada por API al Laravel incluido.

Esta entrega es una primera edición funcional del trabajo local. **No es todavía una copia completa de todos los módulos de Laravel sin internet.** El botón del sistema web abre los módulos originales con conexión.

| Función | Sin internet | Con Laravel |
| --- | --- | --- |
| Clientes | Consulta de copia descargada, altas y edición de ficha básica | Valida documentos y permisos; detecta revisiones concurrentes |
| POS | Catálogo descargado, carrito, pagos combinados, venta y comprobante local | Usa el servicio original Ventas, recalcula precios/impuestos, descuenta inventario y registra pagos |
| Caja | Apertura y cierre en cola | Se aplican a la caja del negocio; cierre web concurrente genera conflicto |
| Contabilidad | Ingresos/gastos, consulta y exportación PDF/Excel | Movimientos reales del libro de Laravel; ventas vinculadas cuando está habilitado |
| Editor de documentos | Mismo editor de fotos, perspectiva, filtros y copias, abierto localmente en el navegador | No sube fotografías a servicios externos |
| CV, tarjetas, fotos de carnet, chat y administración general | No tienen una réplica local en esta edición | Se abren en el sistema web original |

## SQLite local y MySQL en Laravel

SQLite se utiliza solamente dentro de la aplicación de escritorio para trabajar sin internet. Al sincronizar, envía registros por la API HTTPS y Laravel los guarda con su conexión habitual. Si su servidor ya tiene MySQL configurado, los clientes, ventas, caja, movimientos y recibos de sincronización se guardan en esa base MySQL. No necesita cambiar el servidor a SQLite ni entregar la contraseña MySQL al programa.

La API utiliza los modelos y transacciones de Laravel sin fijar otra conexión. Se agregó una prueba de configuración que comprueba que siguen el driver MySQL; no representa una prueba contra el servidor MySQL de su negocio, al cual no se ha accedido desde aquí.

El error WinError 32 al respaldar correspondía al archivo SQLite temporal del equipo. Se corrigió el cierre explícito de las conexiones antes de crear el ZIP y limpiar ese temporal. Se conserva la base local y la cola de operaciones.

## Preparar Laravel

1. Respaldar base de datos, `.env`, archivos y código del servidor.
2. Actualizar Laravel con el proyecto incluido, conservando los datos del servidor.
3. Usar PHP con extensión **sodium**, la base de datos habitual y un dominio **HTTPS**.
4. Ejecutar desde la raíz de Laravel:

```sh
php artisan migrate --force
php artisan desktop:keys
php artisan optimize:clear
php artisan view:cache
```

5. Comprobar que `APP_URL` en `.env` es la dirección HTTPS real, por ejemplo el dominio donde instaló el sistema. Si está detrás de un proxy, conservar la configuración de proxies confiables de su instalación.
6. Como administrador, entrar a **Licencias de escritorio** desde el menú. Crear una licencia para el negocio, elegir módulos, vencimiento, número de equipos y días sin conexión.
7. Guardar la clave que se muestra una sola vez. Se puede generar otra clave después desde el panel; la anterior deja de servir para nuevas activaciones.
8. Abrir el programa y activar con usuario, contraseña y clave de licencia. La versión 1.1 obtiene automáticamente la configuración por HTTPS; no necesita importar JSON. La descarga manual sigue disponible para versiones anteriores.
9. Todos los roles pueden iniciar sesión. Solo las empresas y sus empleados requieren licencia. Los clientes acceden únicamente a sus datos; los administradores eligen el área de trabajo sin licencia comercial.

La firma privada se crea en `storage/app/private/desktop-signing.key`. Respaldarla junto con la instalación; **no copiarla al programa ni a una carpeta pública**. El comando conserva la clave si ya existe. Si se pierde o cambia, los clientes configurados con la clave pública anterior necesitarán un procedimiento de renovación de confianza.

Variables opcionales:

```dotenv
DESKTOP_REQUIRE_HTTPS=true
DESKTOP_SERVER_URL=https://micv.sistemajz.com
# DESKTOP_SIGNING_KEY_PATH=/ruta/privada/desktop-signing.key
```

No desactivar HTTPS en producción. Las pruebas locales usan HTTP exclusivamente en localhost y no cambian ese requisito de la aplicación distribuida.

## Abrir desde Python para desarrollo

Desde `desktop/`, con CPython estándar 3.12, 3.13 o 3.14 de 64 bits:

```sh
python -m venv .venv
# Windows:
.venv\Scripts\python -m pip install -r requirements.txt
.venv\Scripts\python main.py
```

En Linux/macOS, activar el entorno y usar `python -m pip install -r requirements.txt` y `python main.py`. La protección del archivo de clave en Linux es solo de desarrollo; la distribución objetivo es Windows con DPAPI.

En la primera apertura:

1. Abrir Mi CV Escritorio. El servidor predeterminado es `https://micv.sistemajz.com`.
2. Introducir usuario/correo y contraseña. Después del login se pide la licencia solo a cuentas de empresa; administradores y clientes continúan sin ella.
3. Definir un PIN local de al menos seis caracteres. No reutilizar la contraseña del servidor como PIN.
4. Activar con internet y completar la primera sincronización de catálogos.
5. En aperturas posteriores basta el PIN local mientras siga vigente la autorización sin conexión.

La contraseña del sistema no se guarda. Los datos quedan en `%LOCALAPPDATA%\EquipoJZ\MiCVDesktop`, separados de la carpeta de instalación.

## Generar EXE e instalador en Windows

**Este ZIP contiene el código y herramientas de compilación. No contiene un `.exe` de Windows ya compilado o verificado en Windows.** No se generó un ejecutable Linux con nombre `.exe`.

En Windows 10/11 de 64 bits:

1. Usar CPython estándar 3.12, 3.13 o 3.14 de 64 bits. Si ya tiene Python 3.14.7 no necesita instalar 3.12. El lanzador `py` es opcional: el script busca también el intérprete directo, el Registro de Windows y las carpetas habituales.
2. Tener Inno Setup 6 o 7 instalado; el compilador detecta ambas versiones.
3. Abrir PowerShell en `desktop/` y ejecutar:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\Compilar-Windows.ps1
```

Si aparece «py no se reconoce», reemplace `Compilar-Windows.ps1` por la versión actual: ya no depende del lanzador. Solo si no tiene ninguna versión compatible instalada:

```powershell
winget install -e --id Python.Python.3.14 --scope user --architecture x64
```

Cierre y vuelva a abrir PowerShell después de instalar. Puede comprobar los requisitos sin compilar ni instalar dependencias:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\Compilar-Windows.ps1 -CheckPrerequisites
```

Si Python está en otra carpeta, especifique la ruta real a `python.exe` con `-PythonPath`. Si Inno Setup está en otra ubicación, indique `-InnoPath`. No se elimina automáticamente ningún entorno existente o dato local. Para comprobar solamente Python, combine `-CheckPrerequisites -PortableOnly`.

Para generar únicamente la carpeta portable sin instalar Inno Setup:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\Compilar-Windows.ps1 -PortableOnly
```

El script crea un entorno, instala dependencias, ejecuta las pruebas Python, compila con PyInstaller, comprueba el arranque y genera:

- `desktop/dist/MiCVDesktop/MiCVDesktop.exe`: versión portable, requiere toda su carpeta.
- `desktop/dist/installer/MiCVDesktop-Setup.exe`: instalador por usuario, con accesos directos.

El instalador conserva la carpeta de datos al desinstalar. No requiere Python en el equipo del cliente. La compilación inicial necesita internet para descargar dependencias. No incluye firma digital de editor de Windows.

También se incluye `.github/workflows/build-desktop.yml`: al colocar los archivos del proyecto en la raíz de su repositorio, se puede ejecutar manualmente **Compilar Mi CV Escritorio para Windows** en GitHub Actions. Al terminar, descargar el artefacto `MiCVDesktop-Windows`. No se ha ejecutado ese flujo desde esta conversación.

## Cómo funciona la sincronización

- Cada operación obtiene un UUID antes de guardarse en SQLite. Se conserva aunque se cierre el programa.
- Los contenidos de fichas, operaciones, tokens y metadatos están cifrados con AES-GCM. La estructura de SQLite y algunos estados/identificadores no están cifrados.
- En Windows, la clave local se protege con DPAPI del usuario. Un respaldo debe restaurarse con el mismo perfil de Windows. En Linux se usa un archivo de clave con permisos restrictivos para desarrollo.
- Sincronización automática cada minuto y botón manual. La aplicación sigue trabajando mientras se conecta.
- Primero se renueva la autorización, después se envía la cola y se descarga la copia actualizada de los catálogos permitidos.
- Cada operación se aplica en una transacción y queda un recibo único en Laravel. Reintentar después de perder la respuesta no duplica la venta, movimiento o apertura.
- Una operación relacionada puede referirse a un cliente o apertura local anteriores; se resuelve su ID real después de sincronizarlos.
- Si falla la descarga de un catálogo, se conserva la copia anterior. No se borra la cola.
- La escritura en servidor comprueba de nuevo licencia, usuario, negocio y permisos. La base local no otorga permisos al servidor.
- La contabilidad y el inventario definitivos son los de Laravel. El cliente mantiene una estimación local y reservas de sus ventas pendientes.

## Conflictos y límites de esta edición

- Dos equipos desconectados no pueden conocer el stock que acaba de vender el otro. Si ya no alcanza, se conserva la venta local como **Revisar** y no se aplica silenciosamente al servidor.
- Si los precios/impuestos cambian y ya no coincide el total, se revierte la importación completa de esa venta. El comprobante local se conserva para revisión.
- Si la caja se cerró en la web, una venta pendiente contra esa caja requiere revisión. No se abre otra caja ni se cambia una venta pagada sin autorización.
- Si un cliente cambió en la web, no se sobrescribe su ficha. La edición local conserva la revisión original. Si el documento ya existe, no se vincula ni modifica automáticamente una persona de otro negocio.
- El botón Reintentar sirve cuando se corrigió la causa en el servidor, por ejemplo existencias o permisos. **No hay todavía un asistente completo para fusionar fichas concurrentes, cancelar operaciones pendientes o reconciliar una venta que ya se registró manualmente.** Esos casos requieren revisión administrativa; no borrar la base local ni repetir la venta como nueva para forzar la importación.
- La venta y caja importadas conservan la fecha local declarada, validada dentro de la vigencia histórica de la licencia y sin fechas futuras. El libro vinculado usa esa fecha en la zona del negocio. El recibo de sincronización conserva además el momento real de importación en el servidor. La fecha del equipo es una declaración del usuario; no es una certificación de tiempo externa.
- El comprobante local no es una autorización tributaria ni una integración de factura electrónica.
- Los descuentos especiales, devoluciones, compras e inventario editable siguen en la web. El cliente vende productos previamente descargados a sus precios de catálogo.
- Las altas/ediciones offline de clientes reciben la validación definitiva del documento al sincronizar. La ficha básica no reemplaza estudios, experiencias ni archivos del CV.

## Licencia sin internet

La autorización está firmada con Ed25519 y ligada al usuario, equipo y negocio. El administrador configura de 1 a 30 días sin conexión, sin superar el vencimiento de la licencia. Una revocación no puede llegar a un equipo desconectado: se aplica al reconectarse o al vencer lo que ya estaba firmado.

Al vencer, se conserva la información local y se bloquean operaciones nuevas; el usuario puede consultar y respaldar. Se requiere reactivar/renovar antes de importar pendientes si el acceso del servidor está suspendido. Hay detección básica de retroceso de reloj. Esto no convierte un ordenador administrado por un tercero en un dispositivo inviolable: la protección definitiva de los datos del servidor está en la API y sus permisos.

## Respaldo

Usar **Licencia y respaldo → Crear copia de seguridad cifrada**. El archivo incluye una instantánea consistente de SQLite y la clave protegida. Guardarlo en un lugar privado. Para restaurar: cerrar la aplicación, respaldar la carpeta actual y restaurar ambos archivos con el mismo perfil de Windows. No reemplazar una instalación con pendientes sin conservarla.

## Pruebas

```sh
python -m unittest discover -s tests -v
# Desde Laravel:
php artisan test
npm run test:documentos
```

Se verificó en el entorno disponible: pruebas Python, arranque e interfaz Qt en Linux, exportación real PDF/Excel, editor HTML sin red y API Laravel real. La prueba integrada activó una licencia firmada por PHP, la verificó en Python, guardó operaciones durante un corte y sincronizó clientes, caja, venta y contabilidad; un reintento confirmó la misma venta. Además se instalaron las dependencias, se ejecutaron las once pruebas Python y se comprobó el arranque Qt con CPython 3.14.8 en Linux. El detector PowerShell admite 3.12, 3.13 y 3.14 x64 estándar. La ejecución e instalación nativas de Windows quedan pendientes de la compilación en Windows.

Fuentes de herramientas: https://pyinstaller.org/en/stable/ · https://jrsoftware.org/isinfo.php · https://doc.qt.io/qtforpython-6/deployment/

## Actualizar desde la edición anterior

Lea `../ACTUALIZAR-ESCRITORIO-1.2.txt`. Esta versión requiere `php artisan migrate --force` y `php artisan optimize:clear` después de subir el parche; después compile e instale el escritorio nuevo. No genere otra firma.

La aplicación conserva los datos de `%LOCALAPPDATA%\EquipoJZ\MiCVDesktop`. El instalador conserva su AppId. Si ya está activada, continúa con su PIN y perfil guardado. La firma del servidor debe seguir siendo la misma.

La conexión inicial usa HTTPS con verificación de certificado y obtiene únicamente la URL y clave pública desde `/api/desktop/v1/connection`. No sigue redirecciones ni acepta que el servidor cambie la dirección esperada. Guarda la firma pública después de validar la licencia; si una instalación existente recibe otra firma, detiene la activación y conserva sus datos. La contraseña y clave de licencia no se guardan.

El dominio de escritorio se configura en `config/desktop.php` (`DESKTOP_SERVER_URL`, predeterminado micv.sistemajz.com). Debe coincidir con la dirección incluida en la aplicación. `APP_URL` sigue configurándose con el dominio real para los demás módulos de Laravel.

## Interfaz local y atajos

Menús Archivo, Módulos, Herramientas y Ayuda; barra de acceso rápido; estado de pendientes; tablas y formularios Qt. POS en dos paneles ajustables: productos y venta actual.

- Ctrl+1 a Ctrl+6: módulos.
- F5: sincronizar.
- F8: cobrar, cuando está en POS y tiene permiso.
- Ctrl+Mayús+B: copia de seguridad.

Pruebas de esta entrega: PHP/Laravel y Python, pruebas de ventana Qt y simulación de conexión. La compilación y prueba final del instalador se realizan en Windows; este paquete no contiene un EXE ya compilado.

## Cuentas y licencias en 1.2

El rol se obtiene del servidor después de verificar usuario y contraseña. No se envía un rol para solicitar privilegios. Administradores y clientes reciben una autorización firmada de 30 días renovable con internet, sin licencia comercial. Empresas y empleados siguen sujetos a módulos, vencimiento, límite de equipos y permisos de la licencia.

Archivo > Cambiar de cuenta permite perfiles locales aislados. La copia de seguridad guarda la cuenta abierta. Los permisos se vuelven a comprobar en cada operación del servidor. Los dispositivos revocados o cuyo rol cambió no renuevan su acceso.

La ficha de cliente es de consulta en el escritorio actual. Las operaciones empresariales, datos de otros clientes y catálogos POS están bloqueados para ese rol. La administración general y el resto de módulos aún pendientes no se presentan como una réplica offline completa.
