# Mi CV Escritorio — Python + Laravel

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
8. Descargar **configuración de conexión**. Ese JSON contiene la URL y clave pública; no contiene contraseñas de base de datos ni la firma privada.
9. El encargado necesita una cuenta activa de su negocio con los permisos correspondientes. Las cuentas de clientes del portal no pueden activar una caja de escritorio.

La firma privada se crea en `storage/app/private/desktop-signing.key`. Respaldarla junto con la instalación; **no copiarla al programa ni a una carpeta pública**. El comando conserva la clave si ya existe. Si se pierde o cambia, los clientes configurados con la clave pública anterior necesitarán un procedimiento de renovación de confianza.

Variables opcionales:

```dotenv
DESKTOP_REQUIRE_HTTPS=true
# DESKTOP_SIGNING_KEY_PATH=/ruta/privada/desktop-signing.key
```

No desactivar HTTPS en producción. Las pruebas locales usan HTTP exclusivamente en localhost y no cambian ese requisito de la aplicación distribuida.

## Abrir desde Python para desarrollo

Desde `desktop/`, con Python 3.12:

```sh
python -m venv .venv
# Windows:
.venv\Scripts\python -m pip install -r requirements.txt
.venv\Scripts\python main.py
```

En Linux/macOS, activar el entorno y usar `python -m pip install -r requirements.txt` y `python main.py`. La protección del archivo de clave en Linux es solo de desarrollo; la distribución objetivo es Windows con DPAPI.

En la primera apertura:

1. Importar el JSON descargado de Laravel.
2. Introducir usuario/correo, contraseña del sistema y clave de licencia.
3. Definir un PIN local de al menos seis caracteres. No reutilizar la contraseña del servidor como PIN.
4. Activar con internet y completar la primera sincronización de catálogos.
5. En aperturas posteriores basta el PIN local mientras siga vigente la autorización sin conexión.

La contraseña del sistema no se guarda. Los datos quedan en `%LOCALAPPDATA%\EquipoJZ\MiCVDesktop`, separados de la carpeta de instalación.

## Generar EXE e instalador en Windows

**Este ZIP contiene el código y herramientas de compilación. No contiene un `.exe` de Windows ya compilado o verificado en Windows.** No se generó un ejecutable Linux con nombre `.exe`.

En Windows 10/11 de 64 bits:

1. Instalar Python 3.12 de 64 bits con el lanzador `py`.
2. Instalar Inno Setup 6 desde su sitio oficial.
3. Abrir PowerShell en `desktop/` y ejecutar:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\Compilar-Windows.ps1
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

Se verificó en el entorno disponible: pruebas Python, arranque e interfaz Qt en Linux, exportación real PDF/Excel, editor HTML sin red y API Laravel real. La prueba integrada activó una licencia firmada por PHP, la verificó en Python, guardó operaciones durante un corte y sincronizó clientes, caja, venta y contabilidad; un reintento confirmó la misma venta. La ejecución e instalación nativas de Windows quedan pendientes de la compilación en Windows.

Fuentes de herramientas: https://pyinstaller.org/en/stable/ · https://jrsoftware.org/isinfo.php · https://doc.qt.io/qtforpython-6/deployment/
