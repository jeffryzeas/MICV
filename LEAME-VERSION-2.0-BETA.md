# Mi CV Escritorio 2.0.0-beta.6

Este ZIP contiene el código del escritorio y el proyecto Laravel que implementa su API. La versión está destinada a pruebas. Se verificaron en Linux el motor local, la sincronización, el funcionamiento durante una caída del servidor y la recuperación de un respaldo. La compilación y la instalación siguen pendientes de prueba en Windows.

## Punto de venta en la beta 6

- **Ventas en espera.** Desde el carrito, use **En espera** y escriba una referencia. En **Pendientes**, retome la venta y confirme el cobro. El inventario y la caja cambian al cobrar.
- **Cotizaciones.** Use **Cotizar** desde el carrito. En **Pendientes** puede bajar el PDF con la marca del negocio y convertir la cotización en venta mediante el cobro habitual. Los importes usan la moneda configurada, NIO y C$ para el perfil de Nicaragua.
- **Reposición.** En **Productos e inventario → Por agotarse**, use **Reponer** para cargar en una compra la cantidad faltante para alcanzar el mínimo. Revise el costo y registre la compra después de recibir el producto.
- **Horas con más ventas.** El reporte y el PDF muestran las tres franjas de mayor actividad. Excel incluye una hoja con todas las franjas que tuvieron ventas pagadas.

Los pendientes se guardan por cajero en el entorno en que se crearon. El escritorio permite trabajar con ellos sin conexión en un espacio local previamente preparado y con acceso vigente. Las ventas cobradas usan la cola de sincronización habitual. Antes de reemplazar la copia local, resuelva los pendientes para conservar sus datos. Los PDF descargados se pueden conservar fuera de la copia local.

## Correcciones de la beta 5

- **Cartas.** Si falla la conexión, puede abrir sus borradores, editar una carta libre, imprimir o exportar a PDF. El programa conserva el texto editable si el guardado falla. Cuando se agrega la extensión PDF al nombre, se confirma antes de sobrescribir un archivo existente.
- **Chat local.** La búsqueda filtra las conversaciones guardadas. Los cambios de perfil y mensajes se ofrecen desde la sesión conectada.

## Cambios de la beta 4

- En el escritorio, abra **Cartas** desde la barra o el menú de módulos. El editor nativo permite escribir desde cero o cargar una plantilla del cliente. Puede editar el encabezado, el cuerpo y la firma, guardar un borrador y volver a abrirlo. Use **Guardar PDF** o **Imprimir** para entregar la carta.
- El chat incorpora una barra lateral de accesos, lista de conversaciones con filtros y un área de conversación con la paleta de Mi CV. En móvil mantiene la lista y la conversación como pantallas separadas.
- Los borradores de chat de la beta 3 conservan su formato de guardado cifrado y se siguen recuperando con el respaldo. El envío se hace desde la sesión conectada.

## Cambios de la beta 2

- El ejecutable, el acceso directo y el instalador usan el ícono de Mi CV.
- En el panel de escritorio, abra **Ayuda → Diagnóstico y errores**. En el espacio local y en la sesión conectada de escritorio, use **Diagnóstico** en la barra de acciones. El resumen se puede copiar y muestra incidencias, conteos de errores del motor local y referencias de conflictos.
- En Laravel, el administrador general puede consultar **Errores** para ver los registros del servidor.

## Compilar en Windows

1. Extraiga el ZIP en una carpeta nueva. Use Windows x64 con Python 3.12, 3.13 o 3.14 de 64 bits e Inno Setup 6 o 7.
2. Abra PowerShell en la carpeta `mi-cv` y ejecute:

   ```powershell
   cd .\desktop
   powershell -NoProfile -ExecutionPolicy Bypass -File .\Compilar-Windows.ps1
   ```

3. Si todas las pruebas pasan, el instalador queda en `desktop\dist\installer\MiCVDesktop-Setup.exe`. La compilación descarga las dependencias y puede solicitar elevación para instalar Microsoft Visual C++ x64. Para compilar solo la carpeta portable, agregue `-PortableOnly` y conserve la carpeta `desktop\dist\MiCVDesktop` completa.

## Preparar Laravel

Despliegue el código Laravel incluido en un entorno de prueba con su configuración actual, su base y la clave de firma del servidor de escritorio. Conserve `.env` y los archivos de almacenamiento de su instalación. Tras desplegar el código y sus dependencias, ejecute desde la raíz del servidor:

```sh
php artisan migrate --force
php artisan optimize:clear
```

El escritorio guarda su copia en SQLite y sincroniza por HTTPS. Laravel conserva el motor de base de datos que ya tenga configurado.

## Probar sin conexión

1. Abra Mi CV Escritorio, entre con su cuenta y configure el PIN local. La licencia comercial aplica a cuentas de empresa.
2. Con el servidor encendido, vaya a **Trabajo local → Herramientas → Preparar datos locales**. Abra el espacio local y compruebe que aparecen sus datos.
3. Desconecte el equipo o detenga el servidor de prueba. Cierre y vuelva a abrir Mi CV con su PIN. Mientras siga vigente el acceso local podrá guardar cambios en los módulos habilitados y preparar documentos.
4. Restablezca la conexión. Pulse **Sincronizar** y compruebe en Laravel que se aplicaron los cambios. La lista **Sincronización** conserva los conflictos para revisión.
5. Para probar la recuperación, guarde sus cambios, cierre el espacio local y use **Licencia y respaldo → Crear copia de seguridad cifrada**. La opción **Recuperar un respaldo…** de la lista de cuentas usa el PIN respaldado y el mismo perfil de Windows. Para recuperarla, conserve aparte cualquier copia activa de esa misma cuenta.

Enviar mensajes o correo, recibir formularios públicos y renovar la autorización requieren conexión. La cobertura técnica y las comprobaciones pendientes están en `desktop/RUNTIME-ESTADO.md`.
